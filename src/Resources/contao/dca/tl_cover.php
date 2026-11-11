<?php

$GLOBALS['TL_DCA']['tl_cover'] = [
    'config' => ['dataContainer' => \Contao\DC_Table::class],
    'fields' => [
    ],
];
\Hschottm\AurigaBundle\DatabaseSchema::apply('tl_cover');
