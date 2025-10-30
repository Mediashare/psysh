<?php

namespace Psy\Profiling;

/**
 * Profiler result data structure.
 *
 * Contains normalized profiling metrics in a standard format
 * that can be consumed by reporting/display logic.
 */
class ProfilerResult
{
    /**
     * @var array<string, array{
     *   calls: int,
     *   time: int,
     *   memory: int,
     *   peak_memory: int,
     *   cpu_time: int,
     *   time_percent: float,
     *   memory_percent: float,
     *   is_user: bool,
     *   exclusive_time?: int
     * }>
     */
    private array $functions;

    /**
     * @var int Total execution time in microseconds
     */
    private int $totalTime;

    /**
     * @var int Total memory usage in bytes
     */
    private int $totalMemory;

    /**
     * @param array<string, array> $functions Profiling data per function
     * @param int                  $totalTime Total execution time in microseconds
     * @param int                  $totalMemory Total memory usage in bytes
     */
    public function __construct(array $functions, int $totalTime = 0, int $totalMemory = 0)
    {
        $this->functions = $functions;
        $this->totalTime = $totalTime ?: (int) array_sum(array_column($functions, 'time'));
        $this->totalMemory = $totalMemory ?: (int) array_sum(array_column($functions, 'memory'));
    }

    /**
     * Get all function profiling data.
     *
     * @return array<string, array>
     */
    public function getFunctions(): array
    {
        return $this->functions;
    }

    /**
     * Get total execution time in microseconds.
     *
     * @return int
     */
    public function getTotalTime(): int
    {
        return $this->totalTime;
    }

    /**
     * Get total memory usage in bytes.
     *
     * @return int
     */
    public function getTotalMemory(): int
    {
        return $this->totalMemory;
    }

    /**
     * Convert to array format for backwards compatibility.
     *
     * @return array<string, array>
     */
    public function toArray(): array
    {
        return $this->functions;
    }
}
