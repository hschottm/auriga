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
