<?php

namespace Psy\Test\Profiling;

use Psy\Profiling\XdebugInProcessEngine;
use Psy\Test\TestCase;

class XdebugParserTest extends TestCase
{
    public function testParseXdebugTrace()
    {
        $engine = new XdebugInProcessEngine();
        $method = new \ReflectionMethod($engine, 'parseXdebugTrace');
        $method->setAccessible(true);

        $traceFile = __DIR__ . '/../fixtures/xdebug_v3_trace_sample.txt';
        $result = $method->invoke($engine, $traceFile);

        $expectedFile = __DIR__ . '/../fixtures/expected_normalized_output.json';
        $expected = json_decode(file_get_contents($expectedFile), true);

        // Sort for comparison
        ksort($result);
        ksort($expected);

        $this->assertEquals($expected, $result);
    }
}