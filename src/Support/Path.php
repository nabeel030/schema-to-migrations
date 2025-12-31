<?php

namespace Nabeel030\SchemaToMigrations\Support;

class Path
{
    public static function absolute(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $path)) {
            return $path;
        }
        return base_path($path);
    }

    public static function join(string ...$parts): string
    {
        $p = array_shift($parts);
        foreach ($parts as $part) {
            $p = rtrim($p, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($part, DIRECTORY_SEPARATOR);
        }
        return $p;
    }

    public static function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }
}
