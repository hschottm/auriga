<?php

/**
 * Table tl_adventure
 */
$GLOBALS['TL_DCA']['tl_adventure'] = array
(

	// Config
	'config' => array
	(
		'dataContainer'               => 'Table',
		'ctable'                      => array('tl_broadcast'),
		'switchToEdit'                => true,
		'enableVersioning'            => true
	),

	// List
	'list' => array
	(
		'sorting' => array
		(
			'mode'                    => 2,
			'fields'                  => array('title','firstbroadcast'),
			'flag'                    => 11,
			'panelLayout'             => 'filter;sort,search,limit'
		),
		'label' => array
		(
			'fields'                  => array('title'),
			'format'                  => '%s',
			'label_callback'          => array('tl_adventure', 'addIcon')
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
				'label'               => &$GLOBALS['TL_LANG']['tl_adventure']['edit'],
				'href'                => 'table=tl_broadcast',
				'icon'                => 'edit.gif'
			),
			'copy' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_adventure']['copy'],
				'href'                => 'act=copy',
				'icon'                => 'copy.gif'
			),
			'delete' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_adventure']['delete'],
				'href'                => 'act=delete',
				'icon'                => 'delete.gif',
				'attributes'          => 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\')) return false; Backend.getScrollOffset();"'
			),
			'show' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_adventure']['show'],
				'href'                => 'act=show',
				'icon'                => 'show.gif'
			)
		)
	),

	// Palettes
	'palettes' => array
	(
		'default'                     => '{title_legend},numbering,title,firstbroadcast,lastbroadcast,alias,description',
	),

	// Fields
	'fields' => array
	(
		'title' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_adventure']['title'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>true, 'maxlength'=>255, 'tl_class'=>'w50')
		),
		'description' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_adventure']['description'],
			'search'                  => true,
			'inputType'               => 'textarea',
			'eval'                    => array('allowHtml'=>true, 'style'=>'height:80px;','tl_class'=>'clr')
		),
		'alias' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_adventure']['alias'],
			'search'                  => true,
			'inputType'               => 'text',
			'eval'                    => array('maxlength' => 32, 'rgxp'=>'alnum', 'doNotCopy'=>true, 'spaceToUnderscore'=>true, 'maxlength'=>128, 'tl_class'=>'w50'),
			'save_callback' => array
			(
				array('tl_adventure', 'generateAlias')
			)
		),
		'numbering' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_adventure']['numbering'],
			'search'                  => true,
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>5, 'tl_class'=>'w50')
		),
		'firstbroadcast' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_adventure']['firstbroadcast'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 10,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>false, 'rgxp'=>'date', 'datepicker'=>$this->getDatePickerString(), 'tl_class'=>'w50 wizard')
		),
		'lastbroadcast' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_adventure']['lastbroadcast'],
			'exclude'                 => true,
			'search'                  => true,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>false, 'rgxp'=>'date', 'datepicker'=>$this->getDatePickerString(), 'tl_class'=>'w50 wizard')
		),
	)
);

/**
 * Class tl_adventure
 *
 * Provide miscellaneous methods that are used by the data configuration array.
 * @copyright  Helmut Schottmüller 2009
 * @author     Helmut Schottmüller <typolight@aurealis.de>
 * @package    Controller
 */
class tl_adventure extends Backend
{
	/**
	 * Load database object
	 */
	protected function __construct()
	{
		parent::__construct();
		
		// somehow dirty patch to allow going back if someone clicks back on a survey question list
		if (strpos($this->getReferer(ENCODE_AMPERSANDS), 'tl_survey_question'))
		{
			if (preg_match("/id=(\\d+)/", $this->getReferer(ENCODE_AMPERSANDS), $matches))
			{
				$page_id = $matches[1];
				$survey_id = $this->Database->prepare("SELECT pid FROM tl_survey_page WHERE id=?")
					->execute($page_id)
					->fetchEach('pid');
				if ($survey_id[0] > 0)
				{
					$this->redirect($this->addToUrl('table=tl_survey_page&amp;id=' . $survey_id[0]));
				}
			}
		}
	}

	/**
	 * Autogenerate an adventure alias if it has not been set yet
	 * @param mixed
	 * @param object
	 * @return string
	 */
	public function generateAlias($varValue, DataContainer $dc)
	{
		$autoAlias = false;

		// Generate alias if there is none
		if (!strlen($varValue))
		{
			$objTitle = $this->Database->prepare("SELECT title FROM tl_adventure WHERE id=?")
									   ->limit(1)
									   ->execute($dc->id);

			$autoAlias = true;
			$varValue = standardize($objTitle->title);
		}

		$objAlias = $this->Database->prepare("SELECT id FROM tl_adventure WHERE id=? OR alias=?")
								   ->execute($dc->id, $varValue);

		// Check whether the page alias exists
		if ($objAlias->numRows > 1)
		{
			if (!$autoAlias)
			{
				throw new Exception(sprintf($GLOBALS['TL_LANG']['ERR']['aliasExists'], $varValue));
			}

			$varValue .= '.' . $dc->id;
		}

		return $varValue;
	}

	/**
	 * Add an image to each record
	 * @param array
	 * @param string
	 * @return string
	 */
	public function addIcon($row, $label)
	{
		return sprintf('<div class="list_icon" style="background-image:url(\'bundles/hschottmauriga/images/adventure.png\');">%s</div>', $label);
	}
	
}
