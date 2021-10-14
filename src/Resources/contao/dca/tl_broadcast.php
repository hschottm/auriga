<?php

/**
 * Table tl_broadcast
 */
$GLOBALS['TL_DCA']['tl_broadcast'] = array
(

	// Config
	'config' => array
	(
		'dataContainer'               => 'Table',
		'ctable'                      => array('tl_broadcast_song', 'tl_broadcast_file'),
		'ptable'                      => 'tl_adventure',
		'switchToEdit'                => true,
		'enableVersioning'            => true
	),

	// List
	'list' => array
	(
		'sorting' => array
		(
			'mode'                    => 4,
			'filter'                  => true,
			'fields'                  => array('sorting'),
			'panelLayout'             => 'filter;sort,search,limit',
			'headerFields'            => array('title', 'tstamp', 'description'),
			'child_record_callback'   => array('tl_broadcast', 'compilePreview')
		),
		'global_operations' => array
		(
			'popwelt' => array
			(
					'label'               => &$GLOBALS['TL_LANG']['MSC']['import_popwelt'],
					'href'                => 'key=popwelt',
					'class'               => 'header_import',
					'attributes'          => 'onclick="Backend.getScrollOffset();"'
			),
			'all' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['MSC']['all'],
				'href'                => 'act=select',
				'class'               => 'header_edit_all',
				'attributes'          => 'onclick="Backend.getScrollOffset();"'
			)
		),
		'operations' => array
		(
			'edit' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_broadcast']['edit'],
				'href'                => 'act=edit',
				'icon'                => 'edit.gif'
			),
			'songs' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_broadcast']['songs'],
				'href'                => 'table=tl_broadcast_song',
				'icon'                => 'bundles/hschottmauriga/images/songs.png'
			),
			'copy' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_broadcast']['copy'],
				'href'                => 'act=copy',
				'icon'                => 'copy.gif'
			),
			'cut' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_broadcast']['cut'],
				'href'                => 'act=paste&amp;mode=cut',
				'icon'                => 'cut.gif',
				'attributes'          => 'onclick="Backend.getScrollOffset();"'
			),
			'delete' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_broadcast']['delete'],
				'href'                => 'act=delete',
				'icon'                => 'delete.gif',
				'attributes'          => 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\')) return false; Backend.getScrollOffset();"'
			),
			'show' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_broadcast']['show'],
				'href'                => 'act=show',
				'icon'                => 'show.gif'
			)
		)
	),

	// Palettes
	'palettes' => array
	(
		'default'                     => '{title_legend},title,date,hour,chapter;{special_legend},story,special;{recording_legend},length,complete,providers,frequency,samplerate,files',
	),

	// Fields
	'fields' => array
	(
		'title' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_broadcast']['title'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>true, 'maxlength'=>255, 'tl_class'=>'w50')
		),
		'date' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_broadcast']['date'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>true, 'rgxp'=>'date', 'datepicker'=>$this->getDatePickerString(), 'tl_class'=>'w50 wizard')
		),
		'hour' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_broadcast']['hour'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>true, 'rgxp' => 'numeric', 'maxlength'=>2, 'tl_class'=>'w50')
		),
		'chapter' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_broadcast']['chapter'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>true, 'rgxp' => 'numeric', 'maxlength'=>2, 'tl_class'=>'w50')
		),
		'story' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_broadcast']['story'],
			'exclude'                 => true,
			'filter'                  => true,
			'inputType'               => 'checkbox',
			'eval'                    => array('tl_class'=>'w50 m12')
		),
		'special' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_broadcast']['special'],
			'exclude'                 => true,
			'filter'                  => true,
			'inputType'               => 'checkbox',
			'eval'                    => array('tl_class'=>'w50 m12')
		),
		'length' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_broadcast']['length'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>false, 'rgxp' => 'length', 'maxlength'=>5, 'tl_class'=>'w50')
		),
		'complete' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_broadcast']['complete'],
			'default'                 => true,
			'exclude'                 => true,
			'filter'                  => true,
			'inputType'               => 'checkbox',
			'eval'                    => array('tl_class'=>'w50 m12')
		),
		'frequency' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_broadcast']['frequency'],
			'default'                 => 44100,
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>false, 'rgxp' => 'numeric', 'maxlength'=>6, 'tl_class'=>'w50')
		),
		'samplerate' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_broadcast']['samplerate'],
			'default'                 => 128,
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>false, 'rgxp' => 'numeric', 'maxlength'=>6, 'tl_class'=>'w50')
		),
		'providers' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_broadcast']['providers'],
			'exclude'                 => true,
			'inputType'               => 'checkbox',
			'foreignKey'              => 'tl_broadcast_provider.name',
			'eval'                    => array('multiple'=>true)
		),
		'files' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_broadcast']['files'],
			'exclude'                 => true,
			'inputType'               => 'fileTree',
			'eval'                    => array('fieldType'=>'checkbox', 'files'=>true, 'mandatory'=>false, 'extensions'=>'mp3')
		),
	)
);

/**
 * Class tl_broadcast
 *
 * Provide miscellaneous methods that are used by the data configuration array.
 * @copyright  Helmut Schottmüller 2009
 * @author     Helmut Schottmüller <typolight@aurealis.de>
 * @package    Controller
 */
class tl_broadcast extends Backend
{
	/**
	 * Load database object
	 */
	protected function __construct()
	{
		parent::__construct();
	}
	
	/**
	 * Add an image to each record
	 * @param array
	 * @param string
	 * @return string
	 */
	public function addIcon($row, $label)
	{
		return sprintf('<div class="list_icon" style="background-image:url(\'bundles/hschottmauriga/images/broadcast.png\');">%s</div>', $label);
	}
	
	/**
	 * Compile format definitions and return them as string
	 * @param array
	 * @param boolean
	 * @return string
	 */
	public function compilePreview($row, $blnWriteToFile=false)
	{
		$return = "<div>" . $row["chapter"] . ". ";
		if ($row["story"])
		{
			$return .= '<span class="story">';
		}
		$return .= $row["title"];
		if ($row["story"])
		{
			$return .= '</span>';
		}
		$return .= ' (';
		$date = new Date($row['date']);
		$return .= $date->date;
		$return .= ')';
		$return .= "</div>";
		return $return;
	}
}

