<?php

/**
 * Register the classes
 */
ClassLoader::addClasses(array
(
	// Classes
	'Contao\AurigaHelper'            => 'system/modules/auriga/classes/AurigaHelper.php',
	'Contao\FieldValidator'          => 'system/modules/auriga/classes/FieldValidator.php',

	// Elements
	'Contao\ContentSpecialMusic'     => 'system/modules/auriga/elements/ContentSpecialMusic.php',

	// Modules
	'Contao\ModuleAdventureList'      => 'system/modules/auriga/modules/ModuleAdventureList.php',
	'Contao\ModuleBroadcast'          => 'system/modules/auriga/modules/ModuleBroadcast.php',
	'Contao\ModuleBroadcastList'      => 'system/modules/auriga/modules/ModuleBroadcastList.php',
	'Contao\ModuleDPI'                => 'system/modules/auriga/modules/ModuleDPI.php',
	'Contao\ModulePTW'                => 'system/modules/auriga/modules/ModulePTW.php',
	'Contao\ModuleSong'               => 'system/modules/auriga/modules/ModuleSong.php',
));


/**
 * Register the templates
 */
TemplateLoader::addFiles(array
(
	'be_import_popwelt'          => 'system/modules/auriga/templates/backend',
	'ce_specialmusic'            => 'system/modules/auriga/templates/content',
	'mod_auriga_adventurelist'   => 'system/modules/auriga/templates/modules',
	'mod_auriga_broadcast'       => 'system/modules/auriga/templates/modules',
	'mod_auriga_broadcastlist'   => 'system/modules/auriga/templates/modules',
	'mod_auriga_dpi'             => 'system/modules/auriga/templates/modules',
	'mod_auriga_song'            => 'system/modules/auriga/templates/modules',
));