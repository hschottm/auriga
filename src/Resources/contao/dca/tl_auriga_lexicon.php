<?php

use Hschottm\AurigaBundle\LexiconAlias;

$GLOBALS['TL_DCA']['tl_auriga_lexicon'] = [
    'config' => [
        'dataContainer' => Contao\DC_Table::class,
        'enableVersioning' => true,
        'sql' => ['keys' => ['id' => 'primary', 'alias' => 'unique']],
    ],
    'list' => [
        'sorting' => ['mode' => 1, 'fields' => ['term'], 'flag' => 1, 'panelLayout' => 'filter;search,limit'],
        'label' => ['fields' => ['term'], 'format' => '%s'],
        'global_operations' => ['all' => ['href' => 'act=select', 'class' => 'header_edit_all']],
        'operations' => [
            'edit' => ['href' => 'act=edit', 'icon' => 'edit.svg'],
            'copy' => ['href' => 'act=copy', 'icon' => 'copy.svg'],
            'delete' => ['href' => 'act=delete', 'icon' => 'delete.svg'],
            'show' => ['href' => 'act=show', 'icon' => 'show.svg'],
        ],
    ],
    'palettes' => ['default' => '{term_legend},term,alias;{description_legend},description;{publish_legend},published'],
    'fields' => [
        'id' => ['sql' => 'int(10) unsigned NOT NULL auto_increment'],
        'tstamp' => ['sql' => 'int(10) unsigned NOT NULL default 0'],
        'term' => [
            'inputType' => 'text', 'search' => true, 'sorting' => true,
            'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'alias' => [
            'inputType' => 'text', 'search' => true,
            'eval' => ['maxlength' => 128, 'doNotCopy' => true, 'tl_class' => 'w50'],
            'save_callback' => [[LexiconAlias::class, 'save']],
            'sql' => 'varchar(128) DEFAULT NULL',
        ],
        'description' => [
            'inputType' => 'textarea', 'search' => true,
            'eval' => ['rows' => 20, 'preserveTags' => true, 'tl_class' => 'clr'],
            'sql' => 'longtext NULL',
        ],
        'published' => [
            'inputType' => 'checkbox', 'filter' => true,
            'eval' => ['doNotCopy' => true],
            'sql' => "char(1) NOT NULL default ''",
        ],
    ],
];
foreach (['term', 'alias', 'description', 'published'] as $field) {
    $GLOBALS['TL_DCA']['tl_auriga_lexicon']['fields'][$field]['label'] = &$GLOBALS['TL_LANG']['tl_auriga_lexicon'][$field];
}
