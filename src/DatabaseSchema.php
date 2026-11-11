<?php

namespace Hschottm\AurigaBundle;

/** Schema for existing Auriga data and clean Contao 5 installations. */
final class DatabaseSchema
{
    public static function apply(string $table): void
    {
        $dca = &$GLOBALS['TL_DCA'][$table];
        $integer = "int(10) unsigned NOT NULL default 0";
        $text = "varchar(255) NOT NULL default ''";
        if ($table === 'tl_module' || $table === 'tl_content') {
            foreach (['jumpTo_adventure', 'jumpTo_broadcast', 'jumpTo_song', 'jumpTo_search', 'adventure_source', 'special_type'] as $field) {
                if (isset($dca['fields'][$field])) $dca['fields'][$field]['sql'] = $integer;
            }
            if (isset($dca['fields']['admingroups'])) $dca['fields']['admingroups']['sql'] = 'blob NULL';
            return;
        }

        $dca['config']['dataContainer'] = \Contao\DC_Table::class;
        $dca['config']['sql']['keys']['id'] = 'primary';
        $dca['fields']['id']['sql'] = 'int(10) unsigned NOT NULL auto_increment';
        $dca['fields']['tstamp']['sql'] = $integer;
        if (in_array($table, ['tl_broadcast', 'tl_broadcast_song', 'tl_broadcast_file'], true)) {
            $dca['fields']['pid']['sql'] = $integer;
            $dca['fields']['sorting']['sql'] = $integer;
            $dca['config']['sql']['keys']['pid'] = 'index';
        }
        foreach ($dca['fields'] as $name => &$field) {
            if (isset($field['sql'])) continue;
            if (!empty($field['eval']['multiple']) || in_array($name, ['genres', 'providers', 'files'], true)) {
                $field['sql'] = 'blob NULL';
            } elseif (($field['inputType'] ?? '') === 'checkbox') {
                $field['sql'] = "char(1) NOT NULL default ''";
            } elseif (($field['inputType'] ?? '') === 'textarea') {
                $field['sql'] = 'text NULL';
            } elseif (in_array($name, ['date', 'firstbroadcast', 'lastbroadcast'], true)) {
                // Historic broadcasts may predate 1970.
                $field['sql'] = "bigint(20) NOT NULL default 0";
            } else {
                $field['sql'] = $text;
            }
        }
        unset($field);
        if ($table === 'tl_broadcast_song') {
            $dca['fields']['songtype']['sql'] = $integer;
            $dca['fields']['sequence']['sql'] = $integer;
        }
    }
}
