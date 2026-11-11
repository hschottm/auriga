<?php

$GLOBALS['TL_DCA']['tl_song_cover'] = [
    'config' => ['dataContainer' => \Contao\DC_Table::class],
    'fields' => [
        'song' => ['sql' => "int(10) unsigned NOT NULL default 0"],
        'cover' => ['sql' => "int(10) unsigned NOT NULL default 0"],
    ],
];
\Hschottm\AurigaBundle\DatabaseSchema::apply('tl_song_cover');
$GLOBALS['TL_DCA']['tl_song_cover']['config']['sql']['keys']['song'] = 'index';
$GLOBALS['TL_DCA']['tl_song_cover']['config']['sql']['keys']['cover'] = 'index';
