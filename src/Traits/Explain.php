<?php

namespace FQL\Traits;

use FQL\Interface;

/**
 * @phpstan-type ExplainResultArray array{
 *      phase: string,
 *      rows_in: int|null,
 *      rows_out: int|null,
 *      filtered: int|null,
 *      time_ms: float|null,
 *      duration_pct: float|null,
 *      mem_peak_kb: float|null,
 *      note: string
 *  }
 */
trait Explain
{
    private bool $explain = false;
    private bool $explainAnalyze = false;

    public function explain(): Interface\Query
    {
        $this->explain = true;
        $this->explainAnalyze = false;
        return $this;
    }

    public function explainAnalyze(): Interface\Query
    {
        $this->explain = true;
        $this->explainAnalyze = true;
        return $this;
    }

    private function explainToString(): string
    {
        if (!$this->explain) {
            return '';
        }
        return 'EXPLAIN' . ($this->explainAnalyze ? (PHP_EOL . ' ANALYZE') : '') . PHP_EOL;
    }
}
