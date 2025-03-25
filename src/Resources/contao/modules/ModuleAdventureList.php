<?php

namespace Hschottm\AurigaBundle;

use Contao\PageModel;


/**
 * Class ModuleAdventureList
 *
 * Front end module "adventure list".
 * @copyright  Helmut Schottmüller 2010
 * @author     Helmut Schottmüller <typolight@aurealis.de>
 * @package    Controller
 */
class ModuleAdventureList extends ModulePTW
{

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'mod_auriga_adventurelist';


	/**
	 * @return string
	 */
	public function generate()
	{
		if (TL_MODE == 'BE')
		{
			$objTemplate = new \BackendTemplate('be_wildcard');

			$objTemplate->wildcard = '### ADVENTURE LIST ###';
			$objTemplate->title = $this->headline;
			$objTemplate->id = $this->id;
			$objTemplate->link = $this->name;
			$objTemplate->href = 'typolight/main.php?do=modules&amp;act=edit&amp;id=' . $this->id;

			return $objTemplate->parse();
		}

		return parent::generate();
	}


	/**
	 * Generate module
	 */
	protected function compile()
	{
		global $objPage;
		
		$arrAdventures = array();
		$objAdventure = $this->Database->prepare("SELECT * FROM tl_adventure ORDER BY firstbroadcast DESC")
			->execute();

		// Redirect to jumpTo page
		if (strlen($this->jumpTo_adventure))
		{
			$objPageAdventure = PageModel::findById($this->jumpTo_adventure);
		}
		else
		{
			$objPageAdventure = $objPage;
		}
		while ($objAdventure->next())
		{
			$arrAdventures[] = array
			(
				'title' => specialchars($objAdventure->title),
				'description' => specialchars($objAdventure->description),
				'alias' => $objAdventure->alias,
				'numbering' => $objAdventure->numbering,
				'url' => $objPageAdventure->getFrontendUrl('/adventure/' . $objAdventure->id),    
				'id' => $objAdventure->id
			);
		}

		$this->Template->adventures = $arrAdventures;
	}
}

