<?php

declare(strict_types=1);

namespace Hschottm\AurigaBundle;

/** Build a migration without changing the database or crawling the archive. */
final class RecordingMigration
{
    private array $byUuid = [];
    private array $byPath = [];
    private array $byArchiveName = [];

    public function __construct(array $files, private ?\Closure $missingFile = null)
    {
        foreach ($files as $file) {
            $uuid = self::uuid($file['uuid']);
            $path = $file['path'];
            if ($uuid === null || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'mp3') continue;
            $this->byUuid[bin2hex($uuid)] = $file;
            $this->byPath[$path][] = $uuid;
            if (preg_match('~(?:^|/)ptw/(\d+)/([^/]+)$~', $path, $match)) {
                $this->byArchiveName[(int) $match[1].'/'.$match[2]][] = $uuid;
            }
        }
    }

    public static function uuid(mixed $value): ?string
    {
        if (!is_string($value)) return null;
        if (strlen($value) === 16) return $value;
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD', $value)) {
            return hex2bin(str_replace('-', '', $value));
        }
        return null;
    }

    public function plan(mixed $stored, array $legacy, mixed $adventure): array
    {
        $references = [];
        if ($stored !== null && $stored !== '') {
            // Never silently discard malformed or unknown existing selections.
            $decoded = is_string($stored) ? @unserialize($stored, ['allowed_classes' => false]) : false;
            if (is_array($decoded)) $references = array_values($decoded);
            elseif (is_string($stored) && (self::uuid($stored) !== null || !preg_match('/^[aObisdN]:/', $stored))) $references = [$stored];
            else return ['status' => 'skipped', 'issues' => ['Ungültiges Format in tl_broadcast.files.']];
        }
        $references = array_merge($references, $legacy);
        $uuids = [];
        $paths = [];
        $issues = [];
        $registrations = [];
        foreach ($references as $reference) {
            if (!is_string($reference) || $reference === '') {
                $issues[] = 'Leere oder ungültige Dateireferenz.';
                continue;
            }
            $uuid = self::uuid($reference);
            // A historic MP3 path can also happen to contain exactly 16 bytes.
            if ($uuid !== null && !isset($this->byUuid[bin2hex($uuid)]) && str_ends_with(strtolower($reference), '.mp3')) $uuid = null;
            if ($uuid !== null) {
                $matches = isset($this->byUuid[bin2hex($uuid)]) ? [$uuid] : [];
                $label = 'UUID '.bin2hex($uuid);
            } else {
                $path = preg_match('~^https?://~i', $reference) ? rawurldecode((string) parse_url($reference, PHP_URL_PATH)) : $reference;
                $path = ltrim($path, '/');
                $matches = $this->byPath[$path] ?? [];
                if (!$matches && preg_match('/^\d+$/D', (string) $adventure)) {
                    $matches = $this->byArchiveName[(int) $adventure.'/'.basename($path)] ?? [];
                }
                $label = $reference;
                if (!$matches && $this->missingFile !== null) {
                    $candidate = ($this->missingFile)($path, $adventure);
                    if ($candidate !== null) {
                        // Path placeholders are replaced with Contao UUIDs only during apply.
                        $uuids[$candidate] = $candidate;
                        $paths[$candidate] = $candidate;
                        $registrations[$candidate] = $candidate;
                        continue;
                    }
                }
            }
            $matches = array_values(array_unique($matches));
            if (count($matches) !== 1) {
                $issues[] = $label.': '.(count($matches) ? 'mehrdeutig' : 'nicht in tl_files gefunden');
                continue;
            }
            $uuid = $matches[0];
            $uuids[bin2hex($uuid)] = $uuid;
            $paths[bin2hex($uuid)] = $this->byUuid[bin2hex($uuid)]['path'];
        }
        if ($issues) return ['status' => 'skipped', 'issues' => $issues];
        $value = $uuids ? serialize(array_values($uuids)) : $stored;
        return ['status' => $value === $stored && !$registrations ? 'unchanged' : 'change', 'value' => $value, 'paths' => array_values($paths), 'issues' => [], 'registrations' => array_values($registrations)];
    }
}
