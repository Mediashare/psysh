<?php

namespace Psy\Test\Profiling;

use Psy\Profiling\XhprofEngine;
use Psy\Test\TestCase;

class XhprofNormalizationTest extends TestCase
{
    public function testNormalizeXhprofData()
    {
        $engine = new XhprofEngine();
        $method = new \ReflectionMethod($engine, 'normalizeXhprofData');
        $method->setAccessible(true);

        $xhprofData = [
            'main()==>foo' => [
                'ct' => 1,
                'wt' => 100,
                'cpu' => 100,
                'mu' => 1024,
                'pmu' => 2048,
            ],
            'foo==>bar' => [
                'ct' => 2,
                'wt' => 50,
                'cpu' => 50,
                'mu' => 512,
                'pmu' => 1024,
            ],
            'main()==>strlen' => [
                'ct' => 1,
                'wt' => 10,
                'cpu' => 10,
                'mu' => 256,
                'pmu' => 256,
            ],
        ];

        $result = $method->invoke($engine, $xhprofData);

        $expected = [
            'foo' => [
                'calls' => 1,
                'time' => 100,
                'exclusive_time' => 100,
                'memory' => 1024,
                'peak_memory' => 2048,
                'cpu_time' => 100,
                'is_user' => true,
                'params' => null,
                'time_percent' => 100.0 / 160.0 * 100.0,
                'memory_percent' => 1024.0 / 1792.0 * 100.0,
            ],
            'bar' => [
                'calls' => 2,
                'time' => 50,
                'exclusive_time' => 50,
                'memory' => 512,
                'peak_memory' => 1024,
                'cpu_time' => 50,
                'is_user' => true,
                'params' => null,
                'time_percent' => 50.0 / 160.0 * 100.0,
                'memory_percent' => 512.0 / 1792.0 * 100.0,
            ],
            'strlen' => [
                'calls' => 1,
                'time' => 10,
                'exclusive_time' => 10,
                'memory' => 256,
                'peak_memory' => 256,
                'cpu_time' => 10,
                'is_user' => false,
                'params' => null,
                'time_percent' => 10.0 / 160.0 * 100.0,
                'memory_percent' => 256.0 / 1792.0 * 100.0,
            ],
        ];

        // Sort for comparison
        ksort($result);
        ksort($expected);

        $this->assertEquals($expected, $result);
    }
}
