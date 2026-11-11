<?php

use Hschottm\AurigaBundle\CoverSearchSettings;

$GLOBALS['TL_DCA']['tl_settings']['palettes']['default'] .= ';{auriga_legend},aurigaCoverProvider,aurigaDiscogsToken,aurigaDeleteDiscogsToken';
$GLOBALS['TL_DCA']['tl_settings']['fields']['aurigaCoverProvider'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_settings']['aurigaCoverProvider'],
    'inputType' => 'select',
    'options' => ['musicbrainz', 'discogs'],
    'default' => 'musicbrainz',
    'reference' => &$GLOBALS['TL_LANG']['tl_settings']['aurigaCoverProviders'],
    'eval' => ['tl_class' => 'w50'],
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['aurigaDiscogsToken'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_settings']['aurigaDiscogsToken'],
    'inputType' => 'text',
    'eval' => ['hideInput' => true, 'doNotSaveEmpty' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
    'load_callback' => [[CoverSearchSettings::class, 'hideToken']],
    'save_callback' => [[CoverSearchSettings::class, 'saveToken']],
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['aurigaDeleteDiscogsToken'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_settings']['aurigaDeleteDiscogsToken'],
    'inputType' => 'checkbox',
    'eval' => ['doNotSaveEmpty' => true, 'tl_class' => 'clr'],
    'load_callback' => [[CoverSearchSettings::class, 'hideToken']],
    'save_callback' => [[CoverSearchSettings::class, 'deleteToken']],
];
