#!/usr/bin/env php
<?php

/**
 * Benchmark Comparison Tool
 *
 * Compares two benchmark results and shows improvements/regressions
 */

if ($argc < 3) {
    echo "Usage: {$argv[0]} <baseline.json> <current.json>\n";
    exit(1);
}

$baselineFile = $argv[1];
$currentFile = $argv[2];

if (!file_exists($baselineFile) || !file_exists($currentFile)) {
    echo "Error: Both files must exist\n";
    exit(1);
}

$baseline = json_decode(file_get_contents($baselineFile), true);
$current = json_decode(file_get_contents($currentFile), true);

echo "📊 Benchmark Comparison Report\n";
echo str_repeat('=', 70) . "\n\n";

echo "Baseline: {$baseline['timestamp']} (Score: {$baseline['score']})\n";
echo "Current:  {$current['timestamp']} (Score: {$current['score']})\n\n";

// Compare scores
$scoreDelta = $current['score'] - $baseline['score'];
$scoreChange = $scoreDelta >= 0 ? "✅ +" : "❌ ";
echo "Score Change: {$scoreChange}{$scoreDelta} points\n";
echo str_repeat('=', 70) . "\n\n";

// Startup Time Comparison
echo "🚀 Startup Time:\n";
compareMetric(
    'Average',
    $baseline['results']['startup']['avg'],
    $current['results']['startup']['avg'],
    'ms',
    false
);
compareMetric(
    'P95',
    $baseline['results']['startup']['p95'],
    $current['results']['startup']['p95'],
    'ms',
    false
);
compareMetric(
    'P99',
    $baseline['results']['startup']['p99'],
    $current['results']['startup']['p99'],
    'ms',
    false
);
echo "\n";

// Memory Comparison
echo "💾 Memory Usage:\n";
compareMetric(
    'Used',
    $baseline['results']['memory']['used_mb'],
    $current['results']['memory']['used_mb'],
    'MB',
    false
);
compareMetric(
    'Peak',
    $baseline['results']['memory']['peak_mb'],
    $current['results']['memory']['peak_mb'],
    'MB',
    false
);
echo "\n";

// Autoloading Comparison
echo "⚡ Autoloading:\n";
compareMetric(
    'Average',
    $baseline['results']['autoloading']['avg_us'],
    $current['results']['autoloading']['avg_us'],
    'μs',
    false
);
echo "\n";

// Overall Assessment
echo str_repeat('=', 70) . "\n";
echo "📈 Overall Assessment:\n\n";

$improvements = [];
$regressions = [];

// Check each metric
if ($current['results']['startup']['avg'] < $baseline['results']['startup']['avg']) {
    $pct = round((1 - $current['results']['startup']['avg'] / $baseline['results']['startup']['avg']) * 100, 1);
    $improvements[] = "Startup time improved by {$pct}%";
} elseif ($current['results']['startup']['avg'] > $baseline['results']['startup']['avg']) {
    $pct = round(($current['results']['startup']['avg'] / $baseline['results']['startup']['avg'] - 1) * 100, 1);
    $regressions[] = "Startup time regressed by {$pct}%";
}

if ($current['results']['memory']['used_mb'] < $baseline['results']['memory']['used_mb']) {
    $pct = round((1 - $current['results']['memory']['used_mb'] / $baseline['results']['memory']['used_mb']) * 100, 1);
    $improvements[] = "Memory usage improved by {$pct}%";
} elseif ($current['results']['memory']['used_mb'] > $baseline['results']['memory']['used_mb']) {
    $pct = round(($current['results']['memory']['used_mb'] / $baseline['results']['memory']['used_mb'] - 1) * 100, 1);
    $regressions[] = "Memory usage regressed by {$pct}%";
}

if (!empty($improvements)) {
    echo "✅ Improvements:\n";
    foreach ($improvements as $imp) {
        echo "  - {$imp}\n";
    }
    echo "\n";
}

if (!empty($regressions)) {
    echo "❌ Regressions:\n";
    foreach ($regressions as $reg) {
        echo "  - {$reg}\n";
    }
    echo "\n";
}

if (empty($improvements) && empty($regressions)) {
    echo "➡️  No significant changes detected\n\n";
}

// Recommendation
if ($scoreDelta >= 10) {
    echo "🎉 Excellent improvement! Performance is significantly better.\n";
} elseif ($scoreDelta >= 5) {
    echo "👍 Good improvement. Continue optimizing.\n";
} elseif ($scoreDelta <= -10) {
    echo "⚠️  Significant regression detected. Review recent changes.\n";
} elseif ($scoreDelta <= -5) {
    echo "⚠️  Performance regression. Investigation recommended.\n";
} else {
    echo "➡️  Performance is stable.\n";
}

function compareMetric(string $name, float $baseline, float $current, string $unit, bool $higherIsBetter): void
{
    $delta = $current - $baseline;
    $pct = $baseline != 0 ? (($delta / $baseline) * 100) : 0;

    $improved = $higherIsBetter ? ($delta > 0) : ($delta < 0);

    $status = $improved ? '✅' : '❌';
    if (abs($pct) < 1) {
        $status = '➡️';  // No significant change
    }

    printf(
        "  %-12s %8.2f %s → %8.2f %s  (%+6.2f%%) %s\n",
        $name . ':',
        $baseline,
        $unit,
        $current,
        $unit,
        $pct,
        $status
    );
}
