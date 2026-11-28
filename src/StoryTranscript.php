<?php

declare(strict_types=1);

namespace Hschottm\AurigaBundle;

/** Read narrative sections from the Markdown sidecars of assigned recordings. */
final class StoryTranscript
{
    public static function extract(string $markdown): string
    {
        $result = [];
        $section = [];
        $include = false;
        foreach (preg_split('/\R/u', (str_starts_with($markdown, "\xEF\xBB\xBF") ? substr($markdown, 3) : $markdown)) ?: [] as $line) {
            if (preg_match('/^#{1,6}\s+(.+)$/u', $line, $heading)) {
                if ($include && count($section) > 1 && trim(implode("\n", array_slice($section, 1))) !== '') $result[] = trim(implode("\n", $section));
                $section = [];
                $include = false;
                if (preg_match('/^(\d{2,}:\d{2}(?::\d{2})?(?:\s*[–—-]\s*\d{2,}:\d{2}(?::\d{2})?)?)(.*)$/u', $heading[1], $time)) {
                    $label = trim($time[2], " \t·:–—-");
                    // Unlabelled DocBook sections and narrative/recap sections only.
                    $include = $label === '' || preg_match('/^(Abenteuer|Geschichte|Erzählung|Rückblick)(?:\s|$)/u', $label) === 1;
                    if ($include) $section[] = '### '.$time[1];
                }
                continue;
            }
            if ($include) $section[] = $line;
        }
        if ($include && count($section) > 1 && trim(implode("\n", array_slice($section, 1))) !== '') $result[] = trim(implode("\n", $section));
        return implode("\n\n", $result);
    }

    /** Null means no readable sidecar; an empty sidecar deliberately yields no story. */
    public static function load(string $projectDir, string $uploadPath, array $paths, ?callable $onLoaded = null): ?string
    {
        $root = realpath($projectDir.'/'.$uploadPath);
        if ($root === false) return null;
        $texts = [];
        $seen = [];
        foreach ($paths as $path) {
            if (!is_string($path) || !str_starts_with($path, $uploadPath.'/') || preg_match('~(?:^|/)\.\.?(/|$)|[\x00\\\\]~', $path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'mp3') continue;
            $sidecar = $projectDir.'/'.substr($path, 0, -4).'.md';
            $real = realpath($sidecar);
            if ($real === false || !str_starts_with($real, $root.'/') || isset($seen[$real]) || !is_file($real) || !is_readable($real)) continue;
            // Bound reads even when an incorrectly named file is assigned.
            $text = @file_get_contents($real, false, null, 0, 2 * 1024 * 1024 + 1);
            if ($text === false || strlen($text) > 2 * 1024 * 1024 || !mb_check_encoding($text, 'UTF-8')) continue;
            $seen[$real] = true;
            $extracted = self::extract($text);
            $texts[] = $extracted;
            if ($onLoaded !== null) $onLoaded($extracted, substr($path, 0, -4).'.md');
        }
        return $texts === [] ? null : implode("\n\n", array_filter($texts, static fn ($text) => $text !== ''));
    }
}
