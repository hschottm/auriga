<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

/**
 * Table tl_broadcast_song
 */
$GLOBALS['TL_DCA']['tl_broadcast_song'] = array
(

	// Config
	'config' => array
	(
		'dataContainer'               => 'Table',
		'ctable'                      => array(),
		'ptable'                      => 'tl_broadcast',
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
			'headerFields'            => array('artist', 'title', 'album'),
			'child_record_callback'   => array('tl_broadcast_song', 'compilePreview')
		),
		'global_operations' => array
		(
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
				'label'               => &$GLOBALS['TL_LANG']['tl_broadcast_song']['edit'],
				'href'                => 'act=edit',
				'icon'                => 'edit.gif'
			),
			'copy' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_broadcast_song']['copy'],
				'href'                => 'act=copy',
				'icon'                => 'copy.gif'
			),
			'cut' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_broadcast_song']['cut'],
				'href'                => 'act=paste&amp;mode=cut',
				'icon'                => 'cut.gif',
				'attributes'          => 'onclick="Backend.getScrollOffset();"'
			),
			'delete' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_broadcast_song']['delete'],
				'href'                => 'act=delete',
				'icon'                => 'delete.gif',
				'attributes'          => 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\')) return false; Backend.getScrollOffset();"'
			),
			'show' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_broadcast_song']['show'],
				'href'                => 'act=show',
				'icon'                => 'show.gif'
			)
		)
	),

	// Palettes
	'palettes' => array
	(
		'default'                     => '{title_legend},title,artist,album,year,labelcode,songtype,length,composer,sequence;{genre_legend},genres',
	),

	// Fields
	'fields' => array
	(
		'title' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_broadcast_song']['title'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>false, 'maxlength'=>255, 'tl_class'=>'w50')
		),
		'artist' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_broadcast_song']['artist'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>false, 'maxlength'=>255, 'tl_class'=>'w50')
		),
		'album' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_broadcast_song']['album'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>false, 'maxlength'=>255, 'tl_class'=>'w50')
		),
		'year' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_broadcast_song']['year'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>false, 'maxlength'=>4, 'tl_class'=>'w50')
		),
		'labelcode' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_broadcast_song']['labelcode'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>false, 'maxlength'=>100, 'tl_class'=>'w50')
		),
		'genres' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_broadcast_song']['genres'],
			'exclude'                 => true,
			'inputType'               => 'checkbox',
			'foreignKey'              => 'tl_genre.name',
			'eval'                    => array('multiple'=>true, 'tl_class' => 'clr')
		),
		'songtype' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_broadcast_song']['songtype'],
			'exclude'                 => true,
			'filter'                  => true,
			'inputType'               => 'select',
			'foreignKey'              => 'tl_song_type.songtype',
			'eval'                    => array('includeBlankOption' => true, 'multiple'=>false, 'tl_class'=>'w50')
		),
		'length' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_broadcast_song']['length'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>false, 'maxlength'=>6, 'tl_class'=>'w50')
		),
		'composer' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_broadcast_song']['composer'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>false, 'maxlength'=>255, 'tl_class'=>'w50')
		),
		'sequence' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_broadcast_song']['sequence'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>true, 'rgxp' => 'digit', 'maxlength'=>6, 'tl_class'=>'w50')
		),
	)
);

/**
 * Class tl_broadcast_song
 *
 * Provide miscellaneous methods that are used by the data configuration array.
 * @copyright  Helmut Schottmüller 2009
 * @author     Helmut Schottmüller <typolight@aurealis.de>
 * @package    Controller
 */
class tl_broadcast_song extends Backend
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
		return sprintf('<div class="list_icon" style="background-image:url(\'system/modules/auriga/html/images/broadcast.png\');">%s</div>', $label);
	}
	
	/**
	 * Compile format definitions and return them as string
	 * @param array
	 * @param boolean
	 * @return string
	 */
	public function compilePreview($row, $blnWriteToFile=false)
	{
		$return = '<table><tr><td style="width: 2em; vertical-align: top;">' . $row['sequence'] . ".</td><td>";
		$return .= $GLOBALS['TL_LANG']['tl_broadcast_song']['artist'][0] . ": " . $row["artist"] . "<br />" . $GLOBALS['TL_LANG']['tl_broadcast_song']['title'][0] . ": " . $row["title"] . "<br />" . $GLOBALS['TL_LANG']['tl_broadcast_song']['album'][0] . ": " . $row['album'] . " (" . $row['year'] . ")";
		$return .= "</td></tr></table>";
		return $return;
	}
}

?>