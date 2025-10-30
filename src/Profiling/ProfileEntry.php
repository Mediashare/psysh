<?php

namespace Psy\Profiling;

/**
 * Represents a single function/method entry in profiling data.
 *
 * This class normalizes profiling data from different engines into a consistent
 * structure with computed percentages and metadata.
 */
class ProfileEntry
{
    private string $name;
    private int $calls;
    private int $time;
    private int $memory;
    private int $peakMemory;
    private int $cpuTime;
    private float $timePercent;
    private float $memoryPercent;
    private bool $isUser;

    public function __construct(
        string $name,
        int $calls,
        int $time,
        int $memory,
        int $peakMemory,
        int $cpuTime,
        float $timePercent = 0.0,
        float $memoryPercent = 0.0,
        bool $isUser = true
    ) {
        $this->name = $name;
        $this->calls = $calls;
        $this->time = $time;
        $this->memory = $memory;
        $this->peakMemory = $peakMemory;
        $this->cpuTime = $cpuTime;
        $this->timePercent = $timePercent;
        $this->memoryPercent = $memoryPercent;
        $this->isUser = $isUser;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCalls(): int
    {
        return $this->calls;
    }

    public function getTime(): int
    {
        return $this->time;
    }

    public function getMemory(): int
    {
        return $this->memory;
    }

    public function getPeakMemory(): int
    {
        return $this->peakMemory;
    }

    public function getCpuTime(): int
    {
        return $this->cpuTime;
    }

    public function getTimePercent(): float
    {
        return $this->timePercent;
    }

    public function getMemoryPercent(): float
    {
        return $this->memoryPercent;
    }

    public function isUser(): bool
    {
        return $this->isUser;
    }

    /**
     * Update percentage calculations based on totals.
     */
    public function setPercentages(int $totalTime, int $totalMemory): void
    {
        $this->timePercent = $totalTime > 0 ? ($this->time / $totalTime) * 100 : 0.0;
        $this->memoryPercent = $totalMemory > 0 ? ($this->memory / $totalMemory) * 100 : 0.0;
    }

    /**
     * Convert to array format for backward compatibility.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'calls' => $this->calls,
            'time' => $this->time,
            'memory' => $this->memory,
            'peak_memory' => $this->peakMemory,
            'cpu_time' => $this->cpuTime,
            'time_percent' => $this->timePercent,
            'memory_percent' => $this->memoryPercent,
            'is_user' => $this->isUser,
        ];
    }
}
