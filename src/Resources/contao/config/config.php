<?php

// use Hschottm\AurigaBundle\AurigaHelper;
use Hschottm\AurigaBundle\FieldValidator;
use Hschottm\AurigaBundle\ContentSpecialMusic;
use Hschottm\AurigaBundle\ModulePTW;
use Hschottm\AurigaBundle\ModuleAdventureList;
use Hschottm\AurigaBundle\ModuleBroadcast;
use Hschottm\AurigaBundle\ModuleBroadcastList;
use Hschottm\AurigaBundle\ModuleDPI;
use Hschottm\AurigaBundle\ModuleSong;
use Hschottm\AurigaBundle\EventListener\AddCustomRegexpListener;

$GLOBALS['TL_CONFIG']['auriga']['coverpath'] = 'auriga/covers/';
$GLOBALS['TL_CONFIG']['auriga']['fileroot'] = 'auriga/recordings/';
$GLOBALS['TL_CONFIG']['auriga']['filedownloadroot'] = 'https://mp3.nasbrill-soft.de/';

/**
 * Front end modules
 */
$GLOBALS['FE_MOD']['auriga'] = array
(
	'adventurelist'     => ModuleAdventureList::class,
	'broadcastlist'		=> ModuleBroadcastList::class,
	'broadcast'    		=> ModuleBroadcast::class,
	'song'     			=> ModuleSong::class,
	'dpi'     			=> ModuleDPI::class
);

$GLOBALS['TL_CTE']['texts']['speciallist'] = ContentSpecialMusic::class;

/**
 * BACK END FORM FIELDS
 */
\Contao\ArrayUtil::arrayInsert($GLOBALS['BE_MOD'], 3, array
(
	"auriga" => array(
			"adventure" => array(
					"tables" => array(
							'tl_adventure', 'tl_broadcast', 'tl_broadcast_song'
						),
					'icon' => 'bundles/hschottmauriga/images/adventure.png',
					'stylesheet' => 'bundles/hschottmauriga/css/auriga.css',
// 					'popwelt' => array(AurigaHelper::class, 'importFromPopwelt'),
// 					'importbg' => array(AurigaHelper::class, 'importBackgroundMusic'),
// 					'import' => array(AurigaHelper::class, 'importOldDatabase')
				),
			"provider" => array(
				'tables' => array(
						'tl_broadcast_provider'
					),
				'icon' => 'bundles/hschottmauriga/images/provider.png'
			),
			"songtype" => array(
				'tables' => array(
						'tl_song_type'
					),
				'icon' => 'bundles/hschottmauriga/images/tag_green.png'
			),
		)
	)
);

/**
 * Register hook functions
 */
$GLOBALS['TL_HOOKS']['addCustomRegexp'][] = array('auriga.listener.addcustomregexp', 'addCustomRegexp');

