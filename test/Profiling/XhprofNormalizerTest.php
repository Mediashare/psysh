<?php

namespace Psy\Test\Profiling;

use PHPUnit\Framework\TestCase;

/**
 * Test suite for XHProf data normalization.
 *
 * Tests the normalization logic from ProfileCommand::filterProfileData()
 * which converts XHProf's parent==>child format into aggregated per-function metrics.
 */
class XhprofNormalizerTest extends TestCase
{
    private const PSYSH_NAMESPACES = [
        'Psy\\',
        'PhpParser\\',
        'Symfony\\Component\\Console\\',
        'Symfony\\Component\\VarDumper\\',
    ];

    /**
     * Normalize XHProf profile data by aggregating parent==>child entries.
     *
     * This replicates the logic from ProfileCommand::filterProfileData() and
     * enhanceWithCallGraph() for testing purposes.
     */
    private function normalizeXhprofData(array $xhprofData, bool $showAll = false): array
    {
        $aggregated = [];

        foreach ($xhprofData as $parentChild => $metrics) {
            // Separate parent and child
            if (str_contains($parentChild, '==>')) {
                [$parent, $child] = explode('==>', $parentChild, 2);
            } else {
                $parent = null;
                $child = $parentChild;
            }

            // Skip empty entries
            if (empty($child) || $child === null) {
                continue;
            }

            // Aggregate by child function
            if (!isset($aggregated[$child])) {
                $aggregated[$child] = [
                    'calls' => 0,
                    'time' => 0,
                    'memory' => 0,
                    'peak_memory' => 0,
                    'cpu_time' => 0,
                ];
            }

            $aggregated[$child]['calls'] += $metrics['ct'] ?? 0;
            $aggregated[$child]['time'] += $metrics['wt'] ?? 0;
            $aggregated[$child]['memory'] += $metrics['mu'] ?? 0;
            $aggregated[$child]['peak_memory'] = max(
                $aggregated[$child]['peak_memory'],
                $metrics['pmu'] ?? 0
            );
            $aggregated[$child]['cpu_time'] += $metrics['cpu'] ?? 0;
        }

        // Calculate percentages
        $totalTime = array_sum(array_column($aggregated, 'time'));
        $totalMemory = array_sum(array_column($aggregated, 'memory'));

        foreach ($aggregated as $name => $data) {
            $aggregated[$name]['time_percent'] = $totalTime > 0 ? ($data['time'] / $totalTime) * 100 : 0;
            $aggregated[$name]['memory_percent'] = $totalMemory > 0 ? ($data['memory'] / $totalMemory) * 100 : 0;
            $aggregated[$name]['is_user'] = $this->isUserFunction($name);
        }

        return $aggregated;
    }

    private function isUserFunction(string $name): bool
    {
        // Check if it's a PsySH namespace
        foreach (self::PSYSH_NAMESPACES as $namespace) {
            if (str_starts_with($name, $namespace)) {
                return false;
            }
        }

        // Check if it's an internal PHP function
        if (!str_contains($name, '::') && !str_contains($name, '\\') && function_exists($name)) {
            try {
                $reflection = new \ReflectionFunction($name);
                if ($reflection->isInternal()) {
                    return false;
                }
            } catch (\ReflectionException $e) {
                // Ignore
            }
        }

        return true;
    }

    public function testAggregateParentChildEntries(): void
    {
        $xhprofData = [
            'main()==>foo()' => ['ct' => 1, 'wt' => 1000, 'mu' => 2048, 'pmu' => 3072, 'cpu' => 900],
            'main()==>bar()' => ['ct' => 2, 'wt' => 2000, 'mu' => 1024, 'pmu' => 2048, 'cpu' => 1800],
            'foo()==>baz()' => ['ct' => 1, 'wt' => 500, 'mu' => 512, 'pmu' => 1024, 'cpu' => 450],
        ];

        $result = $this->normalizeXhprofData($xhprofData);

        // Each child function should have aggregated metrics
        $this->assertArrayHasKey('foo()', $result);
        $this->assertArrayHasKey('bar()', $result);
        $this->assertArrayHasKey('baz()', $result);

        // Verify aggregated values
        $this->assertEquals(1, $result['foo()']['calls']);
        $this->assertEquals(1000, $result['foo()']['time']);
        $this->assertEquals(2048, $result['foo()']['memory']);

        $this->assertEquals(2, $result['bar()']['calls']);
        $this->assertEquals(2000, $result['bar()']['time']);

        $this->assertEquals(1, $result['baz()']['calls']);
        $this->assertEquals(500, $result['baz()']['time']);
    }

    public function testSumCallsFromMultipleParents(): void
    {
        $xhprofData = [
            'main()==>helper()' => ['ct' => 2, 'wt' => 1000, 'mu' => 1024, 'pmu' => 2048, 'cpu' => 900],
            'foo()==>helper()' => ['ct' => 3, 'wt' => 1500, 'mu' => 1536, 'pmu' => 2048, 'cpu' => 1350],
            'bar()==>helper()' => ['ct' => 1, 'wt' => 500, 'mu' => 512, 'pmu' => 1024, 'cpu' => 450],
        ];

        $result = $this->normalizeXhprofData($xhprofData);

        $this->assertArrayHasKey('helper()', $result);

        // Calls should be summed: 2 + 3 + 1 = 6
        $this->assertEquals(6, $result['helper()']['calls']);

        // Time should be summed: 1000 + 1500 + 500 = 3000
        $this->assertEquals(3000, $result['helper()']['time']);

        // Memory should be summed: 1024 + 1536 + 512 = 3072
        $this->assertEquals(3072, $result['helper()']['memory']);

        // CPU time should be summed: 900 + 1350 + 450 = 2700
        $this->assertEquals(2700, $result['helper()']['cpu_time']);
    }

    public function testCalculatePercentagesCorrectly(): void
    {
        $xhprofData = [
            'main()==>foo()' => ['ct' => 1, 'wt' => 4000, 'mu' => 4096, 'pmu' => 5120, 'cpu' => 3600],
            'main()==>bar()' => ['ct' => 1, 'wt' => 6000, 'mu' => 6144, 'pmu' => 7168, 'cpu' => 5400],
        ];

        $result = $this->normalizeXhprofData($xhprofData);

        // Total time: 4000 + 6000 = 10000
        // foo() time percentage: 4000 / 10000 = 40%
        $this->assertEquals(40.0, $result['foo()']['time_percent']);

        // bar() time percentage: 6000 / 10000 = 60%
        $this->assertEquals(60.0, $result['bar()']['time_percent']);

        // Total memory: 4096 + 6144 = 10240
        // foo() memory percentage: 4096 / 10240 = 40%
        $this->assertEquals(40.0, $result['foo()']['memory_percent']);

        // bar() memory percentage: 6144 / 10240 = 60%
        $this->assertEquals(60.0, $result['bar()']['memory_percent']);
    }

    public function testDetermineIsUserFlag(): void
    {
        $xhprofData = [
            'main()==>MyApp\\Service::process()' => ['ct' => 1, 'wt' => 1000, 'mu' => 1024, 'pmu' => 2048, 'cpu' => 900],
            'main()==>Psy\\Shell::execute()' => ['ct' => 1, 'wt' => 2000, 'mu' => 2048, 'pmu' => 3072, 'cpu' => 1800],
            'main()==>Symfony\\Component\\Console\\Output::write()' => ['ct' => 1, 'wt' => 500, 'mu' => 512, 'pmu' => 1024, 'cpu' => 450],
        ];

        $result = $this->normalizeXhprofData($xhprofData);

        // User code should be marked as is_user = true
        $this->assertTrue($result['MyApp\\Service::process()']['is_user']);

        // PsySH code should be marked as is_user = false
        $this->assertFalse($result['Psy\\Shell::execute()']['is_user']);

        // Symfony Console code should be marked as is_user = false
        $this->assertFalse($result['Symfony\\Component\\Console\\Output::write()']['is_user']);
    }

    public function testHandleMainAndSpecialEntries(): void
    {
        $xhprofData = [
            'main()' => ['ct' => 1, 'wt' => 10000, 'mu' => 10240, 'pmu' => 12288, 'cpu' => 9000],
            'main()==>foo()' => ['ct' => 1, 'wt' => 1000, 'mu' => 1024, 'pmu' => 2048, 'cpu' => 900],
        ];

        $result = $this->normalizeXhprofData($xhprofData);

        // main() without parent should be included
        $this->assertArrayHasKey('main()', $result);
        $this->assertEquals(1, $result['main()']['calls']);
        $this->assertEquals(10000, $result['main()']['time']);

        // foo() should also be included
        $this->assertArrayHasKey('foo()', $result);
    }

    public function testHandleEmptyData(): void
    {
        $result = $this->normalizeXhprofData([]);
        $this->assertEmpty($result);
    }

    public function testHandleMissingMetrics(): void
    {
        $xhprofData = [
            'main()==>foo()' => ['ct' => 1], // Missing wt, mu, pmu, cpu
            'main()==>bar()' => ['wt' => 1000], // Missing ct, mu, pmu, cpu
        ];

        $result = $this->normalizeXhprofData($xhprofData);

        // Should handle missing metrics gracefully with defaults
        $this->assertArrayHasKey('foo()', $result);
        $this->assertEquals(1, $result['foo()']['calls']);
        $this->assertEquals(0, $result['foo()']['time']); // Default to 0
        $this->assertEquals(0, $result['foo()']['memory']); // Default to 0

        $this->assertArrayHasKey('bar()', $result);
        $this->assertEquals(0, $result['bar()']['calls']); // Default to 0
        $this->assertEquals(1000, $result['bar()']['time']);
    }

    public function testPeakMemoryUseMaximum(): void
    {
        $xhprofData = [
            'main()==>foo()' => ['ct' => 1, 'wt' => 1000, 'mu' => 1024, 'pmu' => 2048, 'cpu' => 900],
            'bar()==>foo()' => ['ct' => 1, 'wt' => 1000, 'mu' => 1024, 'pmu' => 3072, 'cpu' => 900],
            'baz()==>foo()' => ['ct' => 1, 'wt' => 1000, 'mu' => 1024, 'pmu' => 1536, 'cpu' => 900],
        ];

        $result = $this->normalizeXhprofData($xhprofData);

        // Peak memory should be the maximum across all calls: max(2048, 3072, 1536) = 3072
        $this->assertEquals(3072, $result['foo()']['peak_memory']);
    }

    public function testIgnoreNullOrEmptyChildren(): void
    {
        $xhprofData = [
            'main()==>' => ['ct' => 1, 'wt' => 1000, 'mu' => 1024, 'pmu' => 2048, 'cpu' => 900],
            '' => ['ct' => 1, 'wt' => 1000, 'mu' => 1024, 'pmu' => 2048, 'cpu' => 900],
            'main()==>foo()' => ['ct' => 1, 'wt' => 1000, 'mu' => 1024, 'pmu' => 2048, 'cpu' => 900],
        ];

        $result = $this->normalizeXhprofData($xhprofData);

        // Should only contain valid entries
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('foo()', $result);
        $this->assertArrayNotHasKey('', $result);
    }

    public function testComplexCallGraph(): void
    {
        $xhprofData = [
            'main()==>A()' => ['ct' => 1, 'wt' => 5000, 'mu' => 5120, 'pmu' => 6144, 'cpu' => 4500],
            'A()==>B()' => ['ct' => 2, 'wt' => 2000, 'mu' => 2048, 'pmu' => 3072, 'cpu' => 1800],
            'A()==>C()' => ['ct' => 1, 'wt' => 1000, 'mu' => 1024, 'pmu' => 2048, 'cpu' => 900],
            'B()==>D()' => ['ct' => 1, 'wt' => 500, 'mu' => 512, 'pmu' => 1024, 'cpu' => 450],
            'C()==>D()' => ['ct' => 1, 'wt' => 300, 'mu' => 256, 'pmu' => 512, 'cpu' => 270],
        ];

        $result = $this->normalizeXhprofData($xhprofData);

        // All functions should be present
        $this->assertArrayHasKey('A()', $result);
        $this->assertArrayHasKey('B()', $result);
        $this->assertArrayHasKey('C()', $result);
        $this->assertArrayHasKey('D()', $result);

        // D() called from two different parents: calls should be summed (1 + 1 = 2)
        $this->assertEquals(2, $result['D()']['calls']);

        // D() time should be summed: 500 + 300 = 800
        $this->assertEquals(800, $result['D()']['time']);

        // D() memory should be summed: 512 + 256 = 768
        $this->assertEquals(768, $result['D()']['memory']);
    }

    public function testZeroTotalsDoNotCauseDivisionByZero(): void
    {
        $xhprofData = [
            'main()==>foo()' => ['ct' => 0, 'wt' => 0, 'mu' => 0, 'pmu' => 0, 'cpu' => 0],
        ];

        $result = $this->normalizeXhprofData($xhprofData);

        // Should not cause division by zero
        $this->assertEquals(0.0, $result['foo()']['time_percent']);
        $this->assertEquals(0.0, $result['foo()']['memory_percent']);
    }

    public function testInternalPhpFunctionsMarkedCorrectly(): void
    {
        $xhprofData = [
            'main()==>array_map' => ['ct' => 5, 'wt' => 500, 'mu' => 512, 'pmu' => 1024, 'cpu' => 450],
            'main()==>strlen' => ['ct' => 10, 'wt' => 100, 'mu' => 64, 'pmu' => 128, 'cpu' => 90],
            'main()==>MyFunction()' => ['ct' => 1, 'wt' => 1000, 'mu' => 1024, 'pmu' => 2048, 'cpu' => 900],
        ];

        $result = $this->normalizeXhprofData($xhprofData);

        // Internal PHP functions should be marked as not user code (without parentheses in xhprof)
        if (function_exists('array_map')) {
            $this->assertFalse($result['array_map']['is_user']);
        }
        if (function_exists('strlen')) {
            $this->assertFalse($result['strlen']['is_user']);
        }

        // User functions should be marked as user code
        $this->assertTrue($result['MyFunction()']['is_user']);
    }
}
