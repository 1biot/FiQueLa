<?php

namespace FQL\Traits;

use FQL\Exception;
use FQL\Interface\Query;

trait Limit
{
    private ?int $limit = null;
    private ?int $offset = null;
    private bool $limitBlocked = false;

    public function blockLimitable(): void
    {
        $this->limitBlocked = true;
    }

    public function isLimitableEmpty(): bool
    {
        return $this->limit === null && $this->offset === null;
    }

    public function limit(int $limit, ?int $offset = null): Query
    {
        if ($this->limitBlocked) {
            throw new Exception\QueryLogicException('LIMIT is not allowed in DESCRIBE mode');
        }

        $this->limit = $limit;
        if ($offset !== null) {
            $this->offset($offset);
        }
        return $this;
    }

    public function offset(int $offset): Query
    {
        if ($this->limitBlocked) {
            throw new Exception\QueryLogicException('OFFSET is not allowed in DESCRIBE mode');
        }

        $this->offset = $offset;
        return $this;
    }

    public function page(int $page, int $perPage = Query::PER_PAGE_DEFAULT): Query
    {
        if ($this->limitBlocked) {
            throw new Exception\QueryLogicException('LIMIT is not allowed in DESCRIBE mode');
        }

        return $this->offset(($page - 1) * $perPage)
            ->limit($perPage);
    }

    private function getLimit(): ?int
    {
        return $this->limit;
    }

    private function getOffset(): ?int
    {
        return $this->offset;
    }

    private function limitToString(): string
    {
        return $this->limit ? PHP_EOL . Query::LIMIT . ' ' . $this->limit : '';
    }

    private function offsetToString(): string
    {
        return $this->offset ? PHP_EOL .  Query::OFFSET . ' ' . $this->offset : '';
    }
}
