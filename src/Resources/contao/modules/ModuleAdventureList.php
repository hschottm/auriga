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
		if ($this->isBackendRequest())
		{
			$objTemplate = new \Contao\BackendTemplate('be_wildcard');

			$objTemplate->wildcard = '### ADVENTURE LIST ###';
			$objTemplate->title = $this->headline;
			$objTemplate->id = $this->id;
			$objTemplate->link = $this->name;
			$objTemplate->href = \Contao\System::getContainer()->get('router')->generate('contao_backend', ['do' => 'themes', 'table' => 'tl_module', 'act' => 'edit', 'id' => $this->id]);

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
		$objPageAdventure = ModulePTW::resolvePage($this->jumpTo_adventure);
		while ($objAdventure->next())
		{
			$arrAdventures[] = array
			(
				'title' => \Contao\StringUtil::specialchars($objAdventure->title),
				'description' => \Contao\StringUtil::specialchars($objAdventure->description),
				'alias' => $objAdventure->alias,
				'numbering' => $objAdventure->numbering,
				'url' => $objPageAdventure->getFrontendUrl('/adventure/' . $objAdventure->id),    
				'id' => $objAdventure->id
			);
		}

		$this->Template->adventures = $arrAdventures;
	}
}

