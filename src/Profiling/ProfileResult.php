<?php

namespace Psy\Profiling;

/**
 * Represents the result of profiling execution.
 *
 * This class encapsulates normalized profiling data with consistent structure
 * across different profiling engines.
 */
class ProfileResult
{
    private array $entries;
    private int $totalTime;
    private int $totalMemory;

    /**
     * @param array<string, ProfileEntry> $entries Map of function name to ProfileEntry
     */
    public function __construct(array $entries)
    {
        $this->entries = $entries;
        $this->calculateTotals();
    }

    /**
     * Get all profile entries.
     *
     * @return array<string, ProfileEntry>
     */
    public function getEntries(): array
    {
        return $this->entries;
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
     * Get profile entries sorted by time (descending).
     *
     * @return array<string, ProfileEntry>
     */
    public function sortedByTime(): array
    {
        $entries = $this->entries;
        uasort($entries, fn(ProfileEntry $a, ProfileEntry $b) => $b->getTime() <=> $a->getTime());

        return $entries;
    }

    /**
     * Get profile entries sorted by memory (descending).
     *
     * @return array<string, ProfileEntry>
     */
    public function sortedByMemory(): array
    {
        $entries = $this->entries;
        uasort($entries, fn(ProfileEntry $a, ProfileEntry $b) => $b->getMemory() <=> $a->getMemory());

        return $entries;
    }

    /**
     * Filter entries by a predicate function.
     *
     * @param callable $predicate Function that takes ProfileEntry and returns bool
     *
     * @return ProfileResult New ProfileResult with filtered entries
     */
    public function filter(callable $predicate): self
    {
        $filtered = array_filter($this->entries, $predicate);

        return new self($filtered);
    }

    /**
     * Convert to array format for backward compatibility.
     *
     * @return array<string, array>
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->entries as $name => $entry) {
            $result[$name] = $entry->toArray();
        }

        return $result;
    }

    private function calculateTotals(): void
    {
        $this->totalTime = 0;
        $this->totalMemory = 0;

        foreach ($this->entries as $entry) {
            $this->totalTime += $entry->getTime();
            $this->totalMemory += $entry->getMemory();
        }
    }
}
