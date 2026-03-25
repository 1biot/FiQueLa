<?php

namespace FQL\Results;

use FQL\Query\FileQuery;

/**
 * @phpstan-import-type ExplainResultArray from \FQL\Traits\Explain
 */
class ExplainCollector
{
    /** @var array<int, ExplainResultArray> */
    private array $rows = [];

    /** @var array<int, float> */
    private array $timers = [];

    /** @var array<int, float> */
    private array $accumulatedTime = [];

    public function addPhase(string $phase, string $note, bool $hasInput): int
    {
        $this->rows[] = [
            'phase' => $phase,
            'rows_in' => $hasInput ? 0 : null,
            'rows_out' => 0,
            'filtered' => null,
            'time_ms' => 0.0,
            'duration_pct' => null,
            'mem_peak_kb' => null,
            'note' => $note,
        ];

        return array_key_last($this->rows);
    }

    public function incrementIn(int $index): void
    {
        $this->rows[$index]['rows_in']++;
    }

    public function incrementOut(int $index): void
    {
        $this->rows[$index]['rows_out']++;
    }

    public function setIncrementIn(int $index, int $value): void
    {
        $this->rows[$index]['rows_in'] = $value;
    }

    public function setIncrementOut(int $index, int $value): void
    {
        $this->rows[$index]['rows_out'] = $value;
    }

    public function startTimer(int $index): void
    {
        $this->timers[$index] = microtime(true);
    }

    public function stopTimer(int $index): void
    {
        if (isset($this->timers[$index])) {
            $elapsed = (microtime(true) - $this->timers[$index]) * 1000;
            $this->rows[$index]['time_ms'] = (float) $this->rows[$index]['time_ms'] + $elapsed;
            $this->rows[$index]['mem_peak_kb'] = round(memory_get_peak_usage() / 1024, 1);
            unset($this->timers[$index]);
        }
    }

    public function addTime(int $index, float $ms): void
    {
        $this->rows[$index]['time_ms'] = (float) $this->rows[$index]['time_ms'] + $ms;
    }

    public function recordMemPeak(int $index): void
    {
        $this->rows[$index]['mem_peak_kb'] = round(memory_get_peak_usage() / 1024, 1);
    }

    public function startAccumulator(int $index): void
    {
        $this->accumulatedTime[$index] = microtime(true);
    }

    public function stopAccumulator(int $index): void
    {
        if (isset($this->accumulatedTime[$index])) {
            $elapsed = (microtime(true) - $this->accumulatedTime[$index]) * 1000;
            $this->rows[$index]['time_ms'] = (float) $this->rows[$index]['time_ms'] + $elapsed;
            $this->rows[$index]['mem_peak_kb'] = round(memory_get_peak_usage() / 1024, 1);
            unset($this->accumulatedTime[$index]);
        }
    }

    /**
     * @return array<int, ExplainResultArray>
     */
    public function finalize(): array
    {
        $totalTime = 0.0;
        foreach ($this->rows as $row) {
            if ($row['time_ms'] !== null) {
                $totalTime += (float) $row['time_ms'];
            }
        }

        foreach ($this->rows as $index => $row) {
            if ($row['rows_in'] !== null && $row['rows_out'] !== null) {
                $this->rows[$index]['filtered'] = $row['rows_in'] - $row['rows_out'];
            }

            if ($row['time_ms'] !== null) {
                $duration = $totalTime > 0.0
                    ? ((float) $row['time_ms'] / $totalTime) * 100
                    : 0.0;
                $this->rows[$index]['duration_pct'] = round($duration, 3);
            }

            if ($row['time_ms'] !== null) {
                $this->rows[$index]['time_ms'] = round((float) $row['time_ms'], 3);
            }
        }

        return $this->rows;
    }

    /**
     * @param string[] $joinNotes
     * @param array<int, array{type: string, query: mixed}> $unions
     * @return array<int, ExplainResultArray>
     */
    public function buildPlan(
        string $streamNote,
        bool $hasJoin,
        array $joinNotes,
        bool $hasWhere,
        string $whereNote,
        bool $isGroupable,
        string $groupNote,
        bool $hasHaving,
        string $havingNote,
        bool $isSortable,
        string $sortNote,
        bool $isLimitable,
        string $limitNote,
        array $unions,
        ?FileQuery $into = null
    ): array {
        $rows = [];
        $rows[] = $this->createPlanRow('stream', $streamNote);

        if ($hasJoin) {
            foreach ($joinNotes as $joinNote) {
                $rows[] = $this->createPlanRow('join', $joinNote);
            }
        }

        if ($hasWhere) {
            $rows[] = $this->createPlanRow('where', $whereNote);
        }

        if ($isGroupable) {
            $rows[] = $this->createPlanRow('group', $groupNote);
        }

        if ($hasHaving) {
            $rows[] = $this->createPlanRow('having', $havingNote);
        }

        if ($isSortable) {
            $rows[] = $this->createPlanRow('sort', $sortNote);
        }

        if ($isLimitable) {
            $rows[] = $this->createPlanRow('limit', $limitNote);
        }

        $unionCount = count($unions);
        foreach ($unions as $index => $union) {
            $prefix = $unionCount === 1 ? 'union' : 'union_' . ($index + 1);
            $rows[] = $this->createPlanRow($prefix, $union['type']);
        }

        if ($into !== null) {
            $rows[] = $this->createPlanRow('into', sprintf('write to %s', (string) $into));
        }

        return $rows;
    }

    /**
     * @return ExplainResultArray
     */
    private function createPlanRow(string $phase, string $note): array
    {
        return [
            'phase' => $phase,
            'rows_in' => null,
            'rows_out' => null,
            'filtered' => null,
            'time_ms' => null,
            'duration_pct' => null,
            'mem_peak_kb' => null,
            'note' => $note,
        ];
    }
}
