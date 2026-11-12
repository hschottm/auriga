<?php

namespace Hschottm\AurigaBundle;

use Contao\FilesModel;
use Contao\StringUtil;
use Contao\Validator;

final class ArchiveAudio
{
    /** Preserve imported filenames, replacing only their old archive location. */
    public static function files(string $filesRoot, string $archive, $number, $references): array
    {
        if (!preg_match('/^\d+$/D', (string) $number)) return [];
        try { $browser = new MediafileBrowser($filesRoot, $archive); }
        catch (\InvalidArgumentException $e) { return []; }
        $found = [];
        foreach (StringUtil::deserialize($references, true) as $reference) {
            if (!is_string($reference) || $reference === '') continue;
            if (strlen($reference) === 16 || Validator::isUuid($reference)) {
                $model = FilesModel::findByUuid($reference);
                if (!$model) continue;
                $reference = $model->path;
            }
            $path = preg_match('~^https?://~i', $reference) ? parse_url($reference, PHP_URL_PATH) : $reference;
            if (!is_string($path)) continue;
            $name = basename($path);
            if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'mp3') continue;
            $relative = 'ptw/'.sprintf('%03d', (int) $number).'/'.$name;
            try {
                $file = $browser->resolve($relative);
                if (is_file($file)) $found[$file] = $file;
            } catch (\InvalidArgumentException $e) {
                // Missing or inaccessible recordings do not get a player.
            }
        }
        return array_values($found);
    }
}
