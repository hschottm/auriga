<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

$GLOBALS['TL_CONFIG']['auriga']['coverpath'] = 'tl_files/auriga/covers/';
$GLOBALS['TL_CONFIG']['auriga']['fileroot'] = 'tl_files/auriga/recordings/';
$GLOBALS['TL_CONFIG']['auriga']['filedownloadroot'] = 'http://mp3.nasbrill-soft.de/';

/**
 * Front end modules
 */
$GLOBALS['FE_MOD']['auriga'] = array
(
	'adventurelist'     => 'ModuleAdventureList',
	'broadcastlist'     => 'ModuleBroadcastList',
	'broadcast'     => 'ModuleBroadcast',
	'song'     => 'ModuleSong',
	'dpi'     => 'ModuleDPI',
);

$GLOBALS['TL_CTE']['texts']['speciallist'] = 'ContentSpecialMusic';

/**
 * BACK END FORM FIELDS
 */
array_insert($GLOBALS['BE_MOD'], 3, array
(
	"auriga" => array(
			"adventure" => array(
					"tables" => array(
							'tl_adventure', 'tl_broadcast', 'tl_broadcast_song'
						),
					'icon' => 'system/modules/auriga/html/images/adventure.png',
					'stylesheet' => 'system/modules/auriga/html/css/auriga.css',
					'popwelt' => array('AurigaHelper', 'importFromPopwelt'),
					'importbg' => array('AurigaHelper', 'importBackgroundMusic'),
					'import' => array('AurigaHelper', 'importOldDatabase')
				),
			"provider" => array(
				'tables' => array(
						'tl_broadcast_provider'
					),
				'icon' => 'system/modules/auriga/html/images/provider.png'
			),
			"songtype" => array(
				'tables' => array(
						'tl_song_type'
					),
				'icon' => 'system/modules/auriga/html/images/tag_green.png'
			),
		)
	)
);

/**
 * Register hook functions
 */
$GLOBALS['TL_HOOKS']['addCustomRegexp'][] = array('FieldValidator', 'addCustomRegexp');

/**
 * Set the member URL parameter as url keyword
 */
$urlKeywords = trimsplit(',',$GLOBALS['TL_CONFIG']['urlKeywords']);
if (!in_array('adventure', $urlKeywords)) $this->update("\$GLOBALS['TL_CONFIG']['urlKeywords']", $GLOBALS['TL_CONFIG']['urlKeywords'] . (strlen(trim($GLOBALS['TL_CONFIG']['urlKeywords'])) ? ',' : '') . 'adventure');
if (!in_array('broadcast', $urlKeywords)) $this->update("\$GLOBALS['TL_CONFIG']['urlKeywords']", $GLOBALS['TL_CONFIG']['urlKeywords'] . (strlen(trim($GLOBALS['TL_CONFIG']['urlKeywords'])) ? ',' : '') . 'broadcast');
if (!in_array('song', $urlKeywords)) $this->update("\$GLOBALS['TL_CONFIG']['urlKeywords']", $GLOBALS['TL_CONFIG']['urlKeywords'] . (strlen(trim($GLOBALS['TL_CONFIG']['urlKeywords'])) ? ',' : '') . 'song');

?>