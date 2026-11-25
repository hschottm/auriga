<?php

declare(strict_types=1);
namespace Hschottm\AurigaBundle;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Dbafs;

final class ContaoRecordingRegistrar implements RecordingRegistrar
{
    public function __construct(private ContaoFramework $framework) {}

    public function register(string $path): string
    {
        $this->framework->initialize();
        // Only add this file and its ancestors, without hashing sibling directories.
        $file = Dbafs::addResource($path, false);
        if ($file === null || $file->type !== 'file' || strlen((string) $file->uuid) !== 16) {
            throw new \RuntimeException('Datei konnte nicht registriert werden: '.$path);
        }
        return $file->uuid;
    }
}
