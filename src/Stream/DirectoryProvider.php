<?php

namespace FQL\Stream;

use FilesystemIterator;

abstract class DirectoryProvider extends AbstractStream
{
    protected function __construct(private readonly string $path)
    {
    }

    public function getStream(?string $query): \ArrayIterator
    {
        return new \ArrayIterator(iterator_to_array($this->getStreamGenerator($query)));
    }

    public function getStreamGenerator(?string $query): \Generator
    {
        $baseIterator = new \RecursiveDirectoryIterator(
            $this->path,
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS
        );

        $iterator = ($query === null || $query === '*')
            ? new \RecursiveIteratorIterator($baseIterator, \RecursiveIteratorIterator::CHILD_FIRST)
            : new \IteratorIterator($baseIterator);

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile() && !$file->isDir()) {
                continue;
            }

            yield [
                'name' => $file->getBasename(),
                'path' => $file->getPath(),
                'realpath' => $file->getRealPath(),
                'size_B' => $file->getSize(),
                'size_KB' => round($file->getSize() / 1024, 2),
                'size_MB' => round($file->getSize() / 1024 / 1024, 2),
                'extension' => $file->getExtension(),
                'created_at' => new \DateTimeImmutable(date('c', $file->getCTime())),
                'modified_at' => new \DateTimeImmutable(date('c', $file->getMTime())),
                'permissions_octal' => sprintf("%04o", $file->getPerms() & 0777),
                'permissions_string' => $this->getPermissionString($file->getPerms()),
                'owner' => function_exists('posix_getpwuid')
                    ? (posix_getpwuid($file->getOwner())['name'] ?? $file->getOwner())
                    : $file->getOwner(),
                'group' => function_exists('posix_getgrgid')
                    ? (posix_getgrgid($file->getGroup())['name'] ?? $file->getGroup())
                    : $file->getGroup(),
                'is_dir' => $file->isDir(),
                'is_link' => $file->isLink(),
                'mime_type' => mime_content_type($file->getRealPath()),
                'hash_md5' => $file->isDir() ? null : md5_file($file->getRealPath()),
            ];
        }
    }

    public function provideSource(): string
    {
        return sprintf('[dir](%s)', $this->path);
    }

    private function getPermissionString(int $perms): string
    {
        $info = '';

        // File type
        $info .= ($perms & 0x4000) ? 'd' : '-';

        // Owner
        $info .= ($perms & 0x0100) ? 'r' : '-';
        $info .= ($perms & 0x0080) ? 'w' : '-';
        $info .= ($perms & 0x0040) ? (($perms & 0x0800) ? 's' : 'x') : (($perms & 0x0800) ? 'S' : '-');

        // Group
        $info .= ($perms & 0x0020) ? 'r' : '-';
        $info .= ($perms & 0x0010) ? 'w' : '-';
        $info .= ($perms & 0x0008) ? (($perms & 0x0400) ? 's' : 'x') : (($perms & 0x0400) ? 'S' : '-');

        // World
        $info .= ($perms & 0x0004) ? 'r' : '-';
        $info .= ($perms & 0x0002) ? 'w' : '-';
        $info .= ($perms & 0x0001) ? (($perms & 0x0200) ? 't' : 'x') : (($perms & 0x0200) ? 'T' : '-');

        return $info;
    }
}
