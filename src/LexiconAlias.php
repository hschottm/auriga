<?php

namespace Hschottm\AurigaBundle;

use Contao\DataContainer;
use Contao\StringUtil;
use Contao\System;

final class LexiconAlias
{
    public function save($value, DataContainer $dc): string
    {
        $db = System::getContainer()->get('database_connection');
        $id = (int) $dc->id;
        $automatic = trim((string) $value) === '';
        $alias = $automatic
            ? StringUtil::standardize((string) $db->fetchOne('SELECT term FROM tl_auriga_lexicon WHERE id = ?', [$id]))
            : trim((string) $value);
        if ($automatic) $alias = substr($alias ?: 'entry-'.$id, 0, 128);
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,127}$/D', $alias)) {
            throw new \InvalidArgumentException($GLOBALS['TL_LANG']['tl_auriga_lexicon']['invalidAlias']);
        }
        $base = $alias;
        $suffix = 1;
        while ($db->fetchOne('SELECT id FROM tl_auriga_lexicon WHERE alias = ? AND id != ?', [$alias, $id]) !== false) {
            if (!$automatic) throw new \InvalidArgumentException($GLOBALS['TL_LANG']['tl_auriga_lexicon']['duplicateAlias']);
            $tail = '-'.$id.($suffix > 1 ? '-'.$suffix : '');
            $alias = substr($base, 0, 128-strlen($tail)).$tail;
            ++$suffix;
        }
        return $alias;
    }
}
