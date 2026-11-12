<?php

/**
 * Table tl_module
 */
$GLOBALS['TL_DCA']['tl_module']['palettes']['adventurelist'] = '{title_legend},name,headline,type;{config_legend},list_sort,perPage;{redirect_legend},jumpTo_adventure,jumpTo_broadcast,jumpTo_song;{template_legend:hide},aurigaTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';
$GLOBALS['TL_DCA']['tl_module']['palettes']['broadcastlist'] = '{title_legend},name,headline,type;{config_legend},adventure_source,perPage;{auriga_archive_legend},aurigaArchiveRoot,aurigaArchiveGroups;{redirect_legend},jumpTo_adventure,jumpTo_broadcast,jumpTo_song;{template_legend:hide},aurigaTpl;{protected_legend:hide},protected;{expert_legend:hide},admingroups,guests,cssID,space';
$GLOBALS['TL_DCA']['tl_module']['palettes']['broadcast'] = '{title_legend},name,headline,type;{config_legend},perPage;{redirect_legend},jumpTo_adventure,jumpTo_broadcast,jumpTo_song,jumpTo_search;{template_legend:hide},aurigaTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';
$GLOBALS['TL_DCA']['tl_module']['palettes']['song'] = '{title_legend},name,headline,type;{redirect_legend},jumpTo_adventure,jumpTo_broadcast,jumpTo_song;{template_legend:hide},aurigaTpl;{protected_legend:hide},protected;{expert_legend:hide},admingroups,guests,cssID,space';
$GLOBALS['TL_DCA']['tl_module']['palettes']['dpi'] = '{title_legend},name,headline,type;{redirect_legend},jumpTo_adventure,jumpTo_broadcast,jumpTo_song;{template_legend:hide},aurigaTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';

$GLOBALS['TL_DCA']['tl_module']['fields']['aurigaTpl'] = array
(
	'exclude'                 => true,
	'inputType'               => 'select',
	'options_callback' => static function ()
	{
		return \Contao\Controller::getTemplateGroup('mod_auriga_');
	},
	'eval'                    => array('includeBlankOption'=>true, 'chosen'=>true, 'tl_class'=>'w50'),
	'sql'                     => "varchar(64) NOT NULL default ''"
);
$GLOBALS['TL_DCA']['tl_module']['fields']['admingroups'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_module']['admingroups'],
	'exclude'                 => true,
	'inputType'               => 'checkbox',
	'foreignKey'              => 'tl_member_group.name',
	'eval'                    => array('mandatory'=>false, 'multiple'=>true)
);

$GLOBALS['TL_DCA']['tl_module']['fields']['adventure_source'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_module']['adventure_source'],
	'exclude'                 => true,
	'filter'                  => true,
	'inputType'               => 'select',
	'foreignKey'              => 'tl_adventure.title',
	'eval'                    => array('includeBlankOption'=>true, 'tl_class'=>'w50')
);

$GLOBALS['TL_DCA']['tl_module']['fields']['jumpTo_adventure'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_module']['jumpTo_adventure'],
	'exclude'                 => true,
	'inputType'               => 'pageTree',
	'eval'                    => array('fieldType'=>'radio')
);

$GLOBALS['TL_DCA']['tl_module']['fields']['jumpTo_broadcast'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_module']['jumpTo_broadcast'],
	'exclude'                 => true,
	'inputType'               => 'pageTree',
	'eval'                    => array('fieldType'=>'radio')
);

$GLOBALS['TL_DCA']['tl_module']['fields']['jumpTo_search'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_module']['jumpTo_search'],
	'exclude'                 => true,
	'inputType'               => 'pageTree',
	'eval'                    => array('fieldType'=>'radio')
);

$GLOBALS['TL_DCA']['tl_module']['fields']['jumpTo_song'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_module']['jumpTo_song'],
	'exclude'                 => true,
	'inputType'               => 'pageTree',
	'eval'                    => array('fieldType'=>'radio')
);


\Hschottm\AurigaBundle\DatabaseSchema::apply('tl_module');

$GLOBALS['TL_DCA']['tl_module']['fields']['list_sort'] = [
    'inputType' => 'select',
    'options' => ['title', 'numbering', 'firstbroadcast'],
    'eval' => ['tl_class' => 'w50'],
    'sql' => "varchar(32) NOT NULL default 'title'",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['aurigaArchiveRoot'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['aurigaArchiveRoot'],
    'exclude' => true, 'inputType' => 'fileTree',
    'eval' => ['fieldType' => 'radio', 'files' => false], 'sql' => 'binary(16) NULL',
];
$GLOBALS['TL_DCA']['tl_module']['fields']['aurigaArchiveGroups'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['aurigaArchiveGroups'],
    'exclude' => true, 'inputType' => 'checkbox', 'foreignKey' => 'tl_member_group.name',
    'eval' => ['multiple' => true], 'sql' => 'blob NULL',
];
