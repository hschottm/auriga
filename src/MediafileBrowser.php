<?php

namespace Hschottm\AurigaBundle;

/** Filesystem operations always stay below the configured browser root. */
final class MediafileBrowser
{
    private string $root;
    private array $extensions;

    public function __construct(string $filesRoot, string $root, string $extensions = 'mp3')
    {
        $filesRoot = realpath($filesRoot);
        $resolved = realpath($root);
        if ($filesRoot === false || $resolved === false || !is_dir($resolved) ||
            ($resolved !== $filesRoot && !str_starts_with($resolved, $filesRoot.'/'))) {
            throw new \InvalidArgumentException('Invalid browser root');
        }
        $this->root = $resolved;
        $this->extensions = array_values(array_filter(array_map(static fn ($value) => strtolower(ltrim(trim($value), '.')), explode(',', $extensions))));
    }

    public function resolve(string $relative): string
    {
        if ($relative !== '') {
            foreach (explode('/', $relative) as $part) {
                if ($part === '' || str_starts_with($part, '.') || str_contains($part, '\\') || str_contains($part, "\0")) {
                    throw new \InvalidArgumentException('Invalid browser path');
                }
            }
        }
        $candidate = $this->root;
        foreach ($relative === '' ? [] : explode('/', $relative) as $part) {
            $candidate .= '/'.$part;
            if (str_starts_with($part, '@') && is_dir($candidate)) throw new \InvalidArgumentException('System directories are not browsable');
            if (is_link($candidate)) throw new \InvalidArgumentException('Symlinks are not browsable');
        }
        $path = realpath($this->root.($relative !== '' ? '/'.$relative : ''));
        if ($path === false || ($path !== $this->root && !str_starts_with($path, $this->root.'/')) || !is_readable($path)) {
            throw new \InvalidArgumentException('Unavailable browser path');
        }
        return $path;
    }

    public function allows(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $this->extensions, true);
    }

    public function entries(string $relative): array
    {
        $directory = $this->resolve($relative);
        if (!is_dir($directory)) throw new \InvalidArgumentException('Not a directory');
        $entries = [];
        foreach (new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS) as $file) {
            // Do not expose hidden files or follow directory aliases.
            if (str_starts_with($file->getFilename(), '.') || $file->isLink() || !$file->isReadable() || (!$file->isDir() && !$file->isFile())) continue;
            if ($file->isDir() && str_starts_with($file->getFilename(), '@')) continue;
            if ($file->isFile() && !$this->allows($file->getFilename())) continue;
            $entries[] = [
                'name' => $file->getFilename(),
                'path' => ($relative !== '' ? $relative.'/' : '').$file->getFilename(),
                'directory' => $file->isDir(),
                'modified' => $file->getMTime(),
                'title' => $file->isFile() && strtolower($file->getExtension()) === 'mp3' ? Mp3Title::read($file->getPathname()) : '',
                'size' => $file->isDir() ? null : $file->getSize(),
            ];
        }
        usort($entries, static fn ($a, $b) => ($b['directory'] <=> $a['directory']) ?: strnatcasecmp($a['name'], $b['name']));
        return $entries;
    }
}
