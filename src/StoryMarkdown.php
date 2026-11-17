<?php

namespace Hschottm\AurigaBundle;

use League\CommonMark\CommonMarkConverter;

/** Render editor-authored Markdown while preserving existing plain-text transcripts. */
final class StoryMarkdown
{
    public static function render(string $text): string
    {
        static $converter;
        $converter ??= new CommonMarkConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 50,
            'renderer' => ['soft_break' => "<br>\n"],
        ]);

        return trim($text) === '' ? '' : $converter->convert($text)->getContent();
    }
}
