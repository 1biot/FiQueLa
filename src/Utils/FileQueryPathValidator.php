<?php

namespace FQL\Utils;

use FQL\Exception\InvalidFormatException;
use FQL\Query\FileQuery;

class FileQueryPathValidator
{
    /**
     * @throws InvalidFormatException
     */
    public static function validate(FileQuery $fileQuery, string $basePath, bool $mustExist = true): FileQuery
    {
        if ($fileQuery->file === null) {
            return $fileQuery;
        }

        $resolvedBasePath = realpath($basePath);
        if ($resolvedBasePath === false) {
            throw new InvalidFormatException('Invalid path of file');
        }

        $normalizedBasePath = self::normalizePath($basePath);
        $requestedPath = self::normalizePath($normalizedBasePath . DIRECTORY_SEPARATOR . $fileQuery->file);

        $validationPath = $mustExist
            ? realpath($requestedPath)
            : self::resolveNearestExistingPath($requestedPath);

        if ($validationPath === false || !self::isPathWithinBase($validationPath, $resolvedBasePath)) {
            throw new InvalidFormatException('Invalid path of file');
        }

        if ($mustExist) {
            return $fileQuery->withFile($validationPath);
        }

        $relativeRequestedPath = ltrim(
            substr($requestedPath, strlen(rtrim($normalizedBasePath, DIRECTORY_SEPARATOR))),
            DIRECTORY_SEPARATOR
        );

        $resolvedTargetPath = rtrim($resolvedBasePath, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . $relativeRequestedPath;

        return $fileQuery->withFile($resolvedTargetPath);
    }

    private static function resolveNearestExistingPath(string $path): string|false
    {
        $candidate = $path;

        while (!file_exists($candidate)) {
            $parent = dirname($candidate);
            if ($parent === $candidate) {
                return false;
            }

            $candidate = $parent;
        }

        return realpath($candidate);
    }

    private static function isPathWithinBase(string $path, string $basePath): bool
    {
        $normalizedBasePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        $basePrefix = $normalizedBasePath . DIRECTORY_SEPARATOR;

        return $path === $normalizedBasePath || str_starts_with($path, $basePrefix);
    }

    private static function normalizePath(string $path): string
    {
        $isAbsolute = false;
        $prefix = '';

        if (preg_match('#^[A-Za-z]:[\\/]#', $path) === 1) {
            $isAbsolute = true;
            $prefix = strtoupper($path[0]) . ':' . DIRECTORY_SEPARATOR;
            $path = substr($path, 3);
        } elseif (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            $isAbsolute = true;
            $prefix = DIRECTORY_SEPARATOR;
            $path = ltrim($path, "\\/");
        }

        $parts = preg_split('#[\\\\/]+#', $path) ?: [];
        $normalizedParts = [];

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                if ($normalizedParts !== [] && end($normalizedParts) !== '..') {
                    array_pop($normalizedParts);
                } elseif (!$isAbsolute) {
                    $normalizedParts[] = '..';
                }

                continue;
            }

            $normalizedParts[] = $part;
        }

        return $prefix . implode(DIRECTORY_SEPARATOR, $normalizedParts);
    }
}
