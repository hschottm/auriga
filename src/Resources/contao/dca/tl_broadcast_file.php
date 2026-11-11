<?php

$GLOBALS['TL_DCA']['tl_broadcast_file'] = [
    'config' => ['dataContainer' => \Contao\DC_Table::class],
    'fields' => [
        'filename' => ['sql' => "varchar(1024) NOT NULL default ''"],
    ],
];
\Hschottm\AurigaBundle\DatabaseSchema::apply('tl_broadcast_file');
