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
		if ($this->isBackendRequest())
		{
			$objTemplate = new \Contao\BackendTemplate('be_wildcard');

			$objTemplate->wildcard = '### BROADCAST LIST ###';
			$objTemplate->title = $this->headline;
			$objTemplate->id = $this->id;
			$objTemplate->link = $this->name;
			$objTemplate->href = \Contao\System::getContainer()->get('router')->generate('contao_backend', ['do' => 'themes', 'table' => 'tl_module', 'act' => 'edit', 'id' => $this->id]);

			return $objTemplate->parse();
		}

		return parent::generate();
	}


    protected function mayPlayArchive(): bool
    {
        $groups = \Contao\StringUtil::deserialize($this->aurigaArchiveGroups, true);
        $container = \Contao\System::getContainer();
        return $groups !== [] && $container->get('contao.security.token_checker')->hasFrontendUser()
            && $container->get('security.authorization_checker')->isGranted(
                \Contao\CoreBundle\Security\ContaoCorePermissions::MEMBER_IN_GROUPS, $groups);
    }

	/**
	 * Generate module
	 */
	protected function compile()
	{
		global $objPage;
		
        $container = \Contao\System::getContainer();
        $request = $container->get('request_stack')->getCurrentRequest();
        $params = $request->query->all();
        $playKey = 'auriga_archive_'.$this->id;
        $play = $params[$playKey] ?? null;
        $allowed = $this->mayPlayArchive();
        $this->Template->showArchivePlayer = $allowed;
        if ($objPage) { $objPage->cache = 0; $objPage->clientCache = 0; }
        if ($play !== null && !$allowed) throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Member access required');
        if ($play !== null && (!is_string($play) || !preg_match('/^[1-9][0-9]*:[0-9]+$/D', $play))) throw new \Contao\CoreBundle\Exception\PageNotFoundException();
        $archive = $allowed && $this->aurigaArchiveRoot ? \Contao\FilesModel::findByUuid($this->aurigaArchiveRoot) : null;

		// Redirect to jumpTo page
		$objPageBroadcast = ModulePTW::resolvePage($this->jumpTo_broadcast);
		$adventure = ($this->adventure_source > 0) ? $this->adventure_source : \Contao\Input::get('adventure');
		if ($adventure === null || $adventure === '') return;
		$adventure = $this->requireRecordId($adventure);
		$objAdventure = $this->Database->prepare("SELECT * FROM tl_adventure WHERE id = ?")
			->execute($adventure);
		if (!$objAdventure->numRows) throw new \Contao\CoreBundle\Exception\PageNotFoundException('Auriga adventure not found');
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
			$broadcast_providers = \Contao\StringUtil::deserialize($objBroadcast->providers, true);
			$foundproviders = array();
			foreach ($broadcast_providers as $key => $provider)
			{
				if (is_scalar($provider) && isset($providers[$provider]['initials']))
				{
					$foundproviders[$providers[$provider]['initials']] = $providers[$provider];
					$allproviders[$providers[$provider]['initials']] = $providers[$provider];
				}
			}
            $audio = [];
            if ($archive && $archive->type === 'folder') {
                $files = ArchiveAudio::files(self::projectDir().'/'.$container->getParameter('contao.upload_path'), self::projectDir().'/'.$archive->path, $objAdventure->numbering, $objBroadcast->files);
                foreach ($files as $index => $file) {
                    $selection = $objBroadcast->id.':'.$index;
                    if ($play === $selection) {
                        $response = new \Symfony\Component\HttpFoundation\BinaryFileResponse($file);
                        $response->setContentDisposition(\Symfony\Component\HttpFoundation\ResponseHeaderBag::DISPOSITION_INLINE, basename($file));
                        $response->headers->set('Content-Type', 'audio/mpeg');
                        $response->headers->set('Cache-Control', 'private, no-store');
                        $response->headers->set('X-Content-Type-Options', 'nosniff');
                        throw new \Contao\CoreBundle\Exception\ResponseException($response);
                    }
                    $query = $params;
                    $query[$playKey] = $selection;
                    $audio[] = $request->getPathInfo().'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
                }
            }
			$arrBroadcasts[] = array
			(
				'title' => \Contao\StringUtil::specialchars($objBroadcast->title),
				'sorting' => \Contao\StringUtil::specialchars($objBroadcast->sorting),
				'date' => \Contao\Date::parse($GLOBALS['TL_CONFIG']['dateFormat'], $objBroadcast->date),
				'hour' => $objBroadcast->hour,
				'chapter' => $objBroadcast->chapter,
				'isStory' => ($objBroadcast->story) ? true : false,
				'isSpecial' => ($objBroadcast->special) ? true : false,
				'isComplete' => ($objBroadcast->complete) ? true : false,
				'length' => $objBroadcast->length,
				'frequency' => $objBroadcast->frequency,
				'samplerate' => $objBroadcast->samplerate,
				'files' => [], // No direct archive URLs, including in old overrides.
                'audio' => $audio,
				'providers' => $foundproviders,
				'url' => $objPageBroadcast->getFrontendUrl('/broadcast/' . $objBroadcast->date),
				'id' => $objBroadcast->id
			);
		}

        if ($play !== null) throw new \Contao\CoreBundle\Exception\PageNotFoundException('Recording not found');
		$this->loadLanguageFile('tl_module');
        $this->Template->lngArchivePlayer = $GLOBALS['TL_LANG']['tl_module']['aurigaArchivePlayer'];
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

