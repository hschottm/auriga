<?php

namespace Hschottm\AurigaBundle;

/** Optional, bounded LRC sidecars for already authorized audio files. */
final class AudioChapters
{
    public static function read(string $audio): array
    {
        $path = substr($audio, 0, -strlen(pathinfo($audio, PATHINFO_EXTENSION))).'lrc';
        if (is_link($path) || !is_file($path) || !is_readable($path)) return [];
        $text = @file_get_contents($path, false, null, 0, 262145);
        if ($text === false || strlen($text) > 262144 || !preg_match('//u', $text)) return [];
        return self::parse($text);
    }

    public static function parse(string $text): array
    {
        $chapters = [];
        $offset = 0;
        if (preg_match('/^\[offset:([+-]?\d+)\]\s*$/mi', $text, $match)) $offset = (int) $match[1] / 1000;
        foreach (preg_split('/\R/u', ltrim($text, "\xEF\xBB\xBF")) as $line) {
            if (!preg_match('/^\s*((?:\[\d+:[0-5]\d(?:\.\d{1,3})?\])+)(.*)$/u', $line, $match)) continue;
            $title = trim($match[2]);
            if ($title === '') continue;
            preg_match_all('/\[(\d+):([0-5]\d(?:\.\d{1,3})?)\]/', $match[1], $times, PREG_SET_ORDER);
            foreach ($times as $time) {
                $start = max(0, (int) $time[1] * 60 + (float) $time[2] - $offset);
                $chapters[(string) $start] = ['start' => $start, 'title' => $title, 'time' => sprintf('%02d:%02d', (int) ($start / 60), (int) $start % 60)];
            }
        }
        $chapters = array_values($chapters);
        usort($chapters, static fn ($a, $b) => $a['start'] <=> $b['start']);
        return $chapters;
    }

    /** Story ranges are chronological, non-overlapping and validated as a whole. */
    public static function story(string $audio): array
    {
        $path = substr($audio, 0, -strlen(pathinfo($audio, PATHINFO_EXTENSION))).'story';
        if (is_link($path) || !is_file($path) || !is_readable($path)) return [];
        $text = @file_get_contents($path, false, null, 0, 262145);
        return $text === false || strlen($text) > 262144 ? [] : self::parseStory($text);
    }

    public static function parseStory(string $text): array
    {
        try { $rows = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', $text), true, 32, JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { return []; }
        if (!is_array($rows) || !array_is_list($rows) || count($rows) > 2000) return [];
        $seconds = static function ($value): ?float {
            if (!is_string($value) || !preg_match('/^(?:(\d+):)?(\d+):([0-5]\d(?:\.\d{1,3})?)$/D', $value, $m)) return null;
            if ($m[1] !== '' && (int) $m[2] > 59) return null;
            return ($m[1] !== '' ? (int) $m[1] * 3600 : 0) + (int) $m[2] * 60 + (float) $m[3];
        };
        $result = [];
        $lastEnd = 0;
        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['title'] ?? null) || trim($row['title']) === '') return [];
            $start = $seconds($row['start'] ?? null);
            $end = $seconds($row['end'] ?? null);
            if ($start === null || $end === null || !is_finite($end) || $start < $lastEnd || $end <= $start) return [];
            $result[] = ['start' => $start, 'end' => $end, 'title' => trim($row['title']), 'time' => sprintf('%02d:%02d', (int) ($start / 60), (int) $start % 60)];
            $lastEnd = $end;
        }
        return $result;
    }

    public static function assets(): void
    {
        $GLOBALS['TL_CSS']['auriga_audio_chapters'] = 'bundles/hschottmauriga/css/audio-chapters.css|static';
        $GLOBALS['TL_JAVASCRIPT']['auriga_audio_chapters'] = 'bundles/hschottmauriga/js/audio-chapters.js|static';
    }

    public static function labels(): array
    {
        \Contao\System::loadLanguageFile('tl_content');
        return $GLOBALS['TL_LANG']['tl_content']['aurigaAudioChapters'];
    }
}
