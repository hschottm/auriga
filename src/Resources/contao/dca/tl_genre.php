<?php

$GLOBALS['TL_DCA']['tl_genre'] = [
    'config' => ['dataContainer' => \Contao\DC_Table::class],
    'fields' => [
        'name' => ['sql' => "varchar(255) NOT NULL default ''"],
    ],
];
\Hschottm\AurigaBundle\DatabaseSchema::apply('tl_genre');
