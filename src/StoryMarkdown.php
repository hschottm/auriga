<?php

namespace Hschottm\AurigaBundle;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;

final class StoryMarkdown
{
    /** Resolver returns null for an unresolved local Markdown reference (render as text). */
    public static function render(string $text, ?callable $resolve = null): string
    {
        if (trim($text) === '') return '';
        $environment = new Environment([
            'html_input' => 'escape', 'allow_unsafe_links' => false, 'max_nesting_level' => 50,
            'renderer' => ['soft_break' => "<br>\n"],
            'heading_permalink' => ['insert' => 'none', 'apply_id_to_heading' => true, 'id_prefix' => '', 'fragment_prefix' => ''],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new HeadingPermalinkExtension());
        if ($resolve !== null) $environment->addEventListener(DocumentParsedEvent::class, static function (DocumentParsedEvent $event) use ($resolve): void {
            $links = [];
            foreach ($event->getDocument()->iterator() as $node) if ($node instanceof Link) $links[] = $node;
            foreach ($links as $link) {
                $url = $resolve($link->getUrl());
                if ($url !== null) $link->setUrl($url);
                else {
                    while ($child = $link->firstChild()) $link->insertBefore($child);
                    $link->detach();
                }
            }
        });
        return (new MarkdownConverter($environment))->convert($text)->getContent();
    }
}
