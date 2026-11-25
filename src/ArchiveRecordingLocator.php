<?php

declare(strict_types=1);

namespace Hschottm\AurigaBundle;

/** Only probes the named recording; never enumerates a directory. */
final class ArchiveRecordingLocator
{
    private string $root;

    public function __construct(private string $projectDir, private string $archive)
    {
        $this->archive = rtrim($archive, '/');
        if (!str_starts_with($this->archive, 'files/') || str_contains($this->archive, "\0") || str_contains($this->archive, '\\') || array_intersect(['', '.', '..'], explode('/', $this->archive))) {
            throw new \InvalidArgumentException('--archive-root muss ein relativer Pfad unter files/ ohne Punktsegmente sein.');
        }
        $root = realpath($projectDir.'/'.$this->archive);
        if ($root === false || !is_dir($root)) throw new \InvalidArgumentException('Archivverzeichnis nicht erreichbar: '.$this->archive);
        $this->root = $root;
    }

    public function __invoke(string $oldPath, mixed $adventure): ?string
    {
        if (!preg_match('/^\d+$/D', (string) $adventure)) return null;
        $name = basename($oldPath);
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'mp3' || str_contains($name, "\0") || str_contains($name, '\\')) return null;
        $path = $this->archive.'/ptw/'.sprintf('%03d', (int) $adventure).'/'.$name;
        return $this->contains($path) ? $path : null;
    }

    public function contains(string $path): bool
    {
        if (!str_starts_with($path, $this->archive.'/')) return false;
        $absolute = $this->projectDir.'/'.$path;
        clearstatcache(true, $absolute);
        $real = realpath($absolute);
        return $real !== false && str_starts_with($real, $this->root.'/') && is_file($absolute) && is_readable($absolute);
    }
}
