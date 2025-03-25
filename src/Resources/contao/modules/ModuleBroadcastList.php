<?php 

namespace Hschottm\AurigaBundle;

use Contao\PageModel;

/**
 * Class ModuleBroadcastList
 *
 * Front end module "broadcast list".
 * @copyright  Helmut Schottmüller 2010
 * @author     Helmut Schottmüller <typolight@aurealis.de>
 * @package    Controller
 */
class ModuleBroadcastList extends ModulePTW
{

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'mod_auriga_broadcastlist';


	/**
	 * @return string
	 */
	public function generate()
	{
		if (TL_MODE == 'BE')
		{
			$objTemplate = new \BackendTemplate('be_wildcard');

			$objTemplate->wildcard = '### BROADCAST LIST ###';
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
		
		// Redirect to jumpTo page
		if (strlen($this->jumpTo_broadcast))
		{
			$objPageBroadcast = PageModel::findById($this->jumpTo_broadcast);
		}
		else
		{
			$objPageBroadcast = $objPage;
		}
		$adventure = ($this->adventure_source > 0) ? $this->adventure_source : $this->Input->get('adventure');
		if ($adventure < 1) return;
		$objAdventure = $this->Database->prepare("SELECT * FROM tl_adventure WHERE id = ?")
			->execute($adventure);
		$arrProviders = $this->Database->prepare("SELECT * FROM tl_broadcast_provider")
			->execute()
			->fetchAllAssoc();
		$providers = array();
		foreach ($arrProviders as $provider)
		{
			$providers[$provider['id']] = $provider;
		}
		$arrBroadcasts = array();
		$objBroadcast = $this->Database->prepare("SELECT * FROM tl_broadcast WHERE pid = ? ORDER BY sorting ASC")
			->execute($adventure);
		$allproviders = array();
		while ($objBroadcast->next())
		{
			$broadcast_providers = deserialize($objBroadcast->providers, true);
			$foundproviders = array();
			foreach ($broadcast_providers as $key => $provider)
			{
				if (strlen($provider))
				{
					$foundproviders[$providers[$provider]['initials']] = $providers[$provider];
					$allproviders[$providers[$provider]['initials']] = $providers[$provider];
				}
			}
			$files = deserialize($objBroadcast->files, true);
			foreach ($files as $idx => $file)
			{
				$files[$idx] = str_replace($GLOBALS['TL_CONFIG']['auriga']['fileroot'], $GLOBALS['TL_CONFIG']['auriga']['filedownloadroot'], $file);
			}
			$arrBroadcasts[] = array
			(
				'title' => specialchars($objBroadcast->title),
				'sorting' => specialchars($objBroadcast->sorting),
				'date' => $this->parseDate($GLOBALS['TL_CONFIG']['dateFormat'], $objBroadcast->date),
				'hour' => $objBroadcast->hour,
				'chapter' => $objBroadcast->chapter,
				'isStory' => ($objBroadcast->story) ? true : false,
				'isSpecial' => ($objBroadcast->special) ? true : false,
				'isComplete' => ($objBroadcast->complete) ? true : false,
				'length' => $objBroadcast->length,
				'frequency' => $objBroadcast->frequency,
				'samplerate' => $objBroadcast->samplerate,
				'files' => $files,
				'providers' => $foundproviders,
				'url' => $objPageBroadcast->getFrontendUrl('/broadcast/' . $objBroadcast->date),
				'id' => $objBroadcast->id
			);
		}

		$this->loadLanguageFile('tl_module');
		ksort($allproviders);
		$this->Template->providers = $allproviders;
		$this->Template->adventure = $objAdventure->row();
		$this->Template->broadcasts = $arrBroadcasts;
		$this->Template->lngBroadcast = $GLOBALS['TL_LANG']['tl_module']['broadcast'];
		$this->Template->lngDate = $GLOBALS['TL_LANG']['tl_module']['broadcast_date'];
		$this->Template->lngLength = $GLOBALS['TL_LANG']['tl_module']['broadcast_length'];
		$this->Template->lngFrequency = $GLOBALS['TL_LANG']['tl_module']['broadcast_frequency'];
		$this->Template->lngSamplerate = $GLOBALS['TL_LANG']['tl_module']['broadcast_samplerate'];
		$this->Template->lngProvider = $GLOBALS['TL_LANG']['tl_module']['broadcast_provider'];
		$this->Template->lngProviders = $GLOBALS['TL_LANG']['tl_module']['providers'];
	}
}

