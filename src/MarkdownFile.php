<?php

declare(strict_types=1);

namespace Hschottm\AurigaBundle;

final class MarkdownFile
{
    public static function read(string $projectDir, string $uploadPath, string $path): ?string
    {
        if (!str_starts_with($path, $uploadPath.'/') || preg_match('~(?:^|/)\.\.?(/|$)|[\x00\\\\]~', $path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'md') return null;
        $root = realpath($projectDir.'/'.$uploadPath);
        $real = realpath($projectDir.'/'.$path);
        if ($root === false || $real === false || !str_starts_with($real, $root.'/') || !is_file($real) || !is_readable($real)) return null;
        $text = @file_get_contents($real, false, null, 0, 2 * 1024 * 1024 + 1);
        if ($text === false || strlen($text) > 2 * 1024 * 1024 || !mb_check_encoding($text, 'UTF-8')) return null;
        return str_starts_with($text, "\xEF\xBB\xBF") ? substr($text, 3) : $text;
    }
}
