<?php

/**
 * Table tl_module
 */
$GLOBALS['TL_DCA']['tl_module']['palettes']['adventurelist'] = '{title_legend},name,headline,type;{config_legend},list_sort,perPage;{redirect_legend},jumpTo_adventure,jumpTo_broadcast,jumpTo_song;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';
$GLOBALS['TL_DCA']['tl_module']['palettes']['broadcastlist'] = '{title_legend},name,headline,type;{config_legend},adventure_source,perPage;{redirect_legend},jumpTo_adventure,jumpTo_broadcast,jumpTo_song;{protected_legend:hide},protected;{expert_legend:hide},admingroups,guests,cssID,space';
$GLOBALS['TL_DCA']['tl_module']['palettes']['broadcast'] = '{title_legend},name,headline,type;{config_legend},perPage;{redirect_legend},jumpTo_adventure,jumpTo_broadcast,jumpTo_song,jumpTo_search;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';
$GLOBALS['TL_DCA']['tl_module']['palettes']['song'] = '{title_legend},name,headline,type;{redirect_legend},jumpTo_adventure,jumpTo_broadcast,jumpTo_song;{protected_legend:hide},protected;{expert_legend:hide},admingroups,guests,cssID,space';
$GLOBALS['TL_DCA']['tl_module']['palettes']['dpi'] = '{title_legend},name,headline,type;{redirect_legend},jumpTo_adventure,jumpTo_broadcast,jumpTo_song;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';

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

