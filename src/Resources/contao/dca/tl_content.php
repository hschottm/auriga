<?php

/**
 * TYPOlight Open Source CMS
 * Copyright (C) 2005-2010 Leo Feyer
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  Leo Feyer 2005-2010
 * @author     Leo Feyer <http://www.typolight.org>
 * @package    Backend
 * @license    LGPL
 * @filesource
 */


/**
 * Table tl_content
 */
$GLOBALS['TL_DCA']['tl_content']['palettes']['speciallist'] = '{type_legend},type,headline;{special_legend},special_type;{template_legend:hide},aurigaTpl;{redirect_legend},jumpTo_song;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';

$GLOBALS['TL_DCA']['tl_content']['fields']['jumpTo_song'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_content']['jumpTo_song'],
	'exclude'                 => true,
	'inputType'               => 'pageTree',
	'eval'                    => array('fieldType'=>'radio')
);

$GLOBALS['TL_DCA']['tl_content']['fields']['special_type'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_content']['special_type'],
	'exclude'                 => true,
	'filter'                  => true,
	'inputType'               => 'select',
	'foreignKey'              => 'tl_song_type.songtype',
	'eval'                    => array('includeBlankOption'=>true, 'tl_class'=>'w50')
);

$GLOBALS['TL_DCA']['tl_content']['fields']['aurigaTpl'] = array
(
	'exclude'                 => true,
	'inputType'               => 'select',
	'options_callback' => static function ()
	{
		return Controller::getTemplateGroup('ce_');
	},
	'eval'                    => array('includeBlankOption'=>true, 'chosen'=>true, 'tl_class'=>'w50'),
	'sql'                     => "varchar(64) NOT NULL default ''"
);
