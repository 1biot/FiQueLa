<?php

namespace FQL\Stream;

use FQL\Exception;
use FQL\Exception\UnexpectedValueException;
use FQL\Interface;
use FQL\Query;
use FQL\Sql;

/**
 * @phpstan-import-type StreamProviderArrayIterator from ArrayStreamProvider
 */
abstract class AbstractStream implements Interface\Stream
{
    /**
     * @param StreamProviderArrayIterator $data
     * @param array<string, mixed> $settings
     * @throws Exception\NotImplementedException
     */
    public static function write(string $fileName, \Traversable $data, array $settings = []): void
    {
        throw new Exception\NotImplementedException([static::class, __FUNCTION__]);
    }

    /**
     * @param array<string, mixed> $settings
     * @param string[] $allowed
     * @throws UnexpectedValueException
     */
    protected static function assertAllowedSettings(array $settings, array $allowed, string $context): void
    {
        $unknown = array_diff(array_keys($settings), $allowed);
        if ($unknown === []) {
            return;
        }

        throw new UnexpectedValueException(
            sprintf('Unexpected %s settings: %s', $context, implode(', ', $unknown))
        );
    }
    /**
     * @return Query\Query
     */
    public function query(): Interface\Query
    {
        return new Query\Query($this);
    }

    /**
     * @throws Exception\InvalidFormatException
     * @throws Exception\FileNotFoundException
     */
    public function fql(string $sql): Interface\Results
    {
        return (new Sql\Sql(trim($sql)))
            ->parseWithQuery($this->query())
            ->execute();
    }
}
