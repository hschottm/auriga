<?php

declare(strict_types=1);

namespace Hschottm\AurigaBundle;

use Contao\PageModel;
use Contao\StringUtil;
use Contao\Validator;
use Doctrine\DBAL\Connection;

/** Resolves logical file paths, never scans the archive or guesses from basenames. */
final class MarkdownLinkResolver
{
    private array $targets = [];
    private ?array $recordings = null;

    public function __construct(private Connection $db, private string $uploadPath, private ?PageModel $lexiconPage, private ?PageModel $broadcastPage) {}

    public function resolve(string $url, string $source): ?string
    {
        // Normal web links, root-relative site URLs and local anchors are unchanged.
        if ($url === '' || preg_match('~^(?:[a-z][a-z0-9+.-]*:|/|\#|\?)~i', $url)) return $url;
        $parts = parse_url($url);
        if ($parts === false) return null;
        $path = rawurldecode($parts['path'] ?? '');
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'md') return $url;
        $target = self::relativePath($source, $path, $this->uploadPath);
        if ($target === null) return null;
        if (!array_key_exists($target, $this->targets)) $this->targets[$target] = $this->findTarget($target);
        $result = $this->targets[$target];
        if ($result === null) return null;
        // Queries on local source files are deliberately not forwarded to the CMS.
        return $result.(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }

    public static function relativePath(string $source, string $target, string $root): ?string
    {
        if ($source === '' || preg_match('~[\x00-\x1f\\\\]~', $target) || str_starts_with($target, '/')) return null;
        $segments = [];
        foreach (explode('/', dirname($source).'/'.$target) as $segment) {
            if ($segment === '' || $segment === '.') continue;
            if ($segment === '..') { if (!$segments) return null; array_pop($segments); }
            else $segments[] = $segment;
        }
        $path = implode('/', $segments);
        return str_starts_with($path, $root.'/') ? $path : null;
    }

    private function findTarget(string $path): ?string
    {
        // A published entry is addressed by its actual assigned file and current alias.
        $entries = $this->db->fetchFirstColumn("SELECT l.alias FROM tl_auriga_lexicon l JOIN tl_files f ON f.uuid=l.markdownFile WHERE f.path=? AND l.published='1' AND l.alias IS NOT NULL AND l.alias<>''", [$path]);
        if (count($entries) > 0) return count($entries) === 1 && $this->lexiconPage ? $this->lexiconPage->getFrontendUrl('/lexicon/'.rawurlencode($entries[0])) : null;
        if (!$this->broadcastPage) return null;
        $mp3 = substr($path, 0, -3).'.mp3';
        $uuid = $this->db->fetchOne('SELECT uuid FROM tl_files WHERE path=?', [$mp3]);
        if ($this->recordings === null) {
            $this->recordings = [];
            foreach ($this->db->fetchAllAssociative('SELECT `date`, files FROM tl_broadcast') as $row) {
                foreach (StringUtil::deserialize($row['files'], true) as $reference) $this->addRecording($reference, $row['date']);
            }
            foreach ($this->db->fetchAllAssociative('SELECT b.`date`, f.filename FROM tl_broadcast_file f JOIN tl_broadcast b ON b.id=f.pid') as $row) $this->addRecording($row['filename'], $row['date']);
        }
        $dates = $this->recordings[$mp3] ?? [];
        if (is_string($uuid)) $dates += $this->recordings[$uuid] ?? [];
        // The broadcast module routes by broadcast DATE, not tl_broadcast.id.
        return count($dates) === 1 ? $this->broadcastPage->getFrontendUrl('/broadcast/'.rawurlencode((string) array_key_first($dates))) : null;
    }

    private function addRecording(mixed $reference, mixed $date): void
    {
        if (!is_string($reference) || $reference === '' || !is_numeric($date)) return;
        if (Validator::isStringUuid($reference)) $reference = StringUtil::uuidToBin($reference);
        $this->recordings[$reference][(string) $date] = true;
    }
}
