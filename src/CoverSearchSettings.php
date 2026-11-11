<?php

namespace Hschottm\AurigaBundle;

use Contao\Config;

final class CoverSearchSettings
{
    public static function hideToken($value): string
    {
        return '';
    }

    public static function saveToken($value): string
    {
        $value = trim((string) $value);
        if ($value !== '') {
            if (!preg_match('/^[A-Za-z0-9_-]+$/D', $value)) {
                throw new \InvalidArgumentException($GLOBALS['TL_LANG']['tl_settings']['aurigaInvalidToken'] ?? 'Invalid Discogs API token.');
            }
            // DC_File normally logs text-field values. Persist the secret here and
            // return the empty display value so DC_File neither logs nor renders it.
            Config::persist('aurigaDiscogsToken', $value);
            Config::set('aurigaDiscogsToken', $value);
        }
        return '';
    }

    public static function deleteToken($value): string
    {
        if ($value) {
            Config::persist('aurigaDiscogsToken', '');
            Config::set('aurigaDiscogsToken', '');
        }
        return '';
    }
}
