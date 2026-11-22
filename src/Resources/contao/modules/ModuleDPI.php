<?php 

namespace Hschottm\AurigaBundle;

use Contao\PageModel;

/**
 * Class ModuleDPI
 *
 * Front end module "DPI".
 * @copyright  Helmut Schottmüller 2010
 * @author     Helmut Schottmüller <typolight@aurealis.de>
 * @package    Controller
 */
class ModuleDPI extends ModulePTW
{

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'mod_auriga_dpi';


	/**
	 * @return string
	 */
	public function generate()
	{
		if ($this->isBackendRequest())
		{
			$objTemplate = new \Contao\BackendTemplate('be_wildcard');

			$objTemplate->wildcard = '### DPI ###';
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

		// DPI can be rendered without a song module loading these labels first.
		$this->loadLanguageFile('tl_broadcast_song');
		
		// Redirect to jumpTo page
		$objPageAdventure = ModulePTW::resolvePage($this->jumpTo_adventure);
		$objPageBroadcast = ModulePTW::resolvePage($this->jumpTo_broadcast);
		$objPageSong = ModulePTW::resolvePage($this->jumpTo_song);
		$objPageSearch = $objPage;
		$arrGenres = $this->Database->prepare("SELECT * FROM tl_genre")
			->execute()
			->fetchAllAssoc();
		$genres = array();
		foreach ($arrGenres as $genre)
		{
			$genres[$genre['id']] = $genre;
		}
		
        $GLOBALS['TL_CSS'][] = 'bundles/hschottmauriga/css/search-results.css|static';
        $this->Template->class = trim((string) $this->Template->class . ' auriga-search');

        $options = array('title', 'artist', 'album', 'year', 'labelcode');
        $arrResults = array(10, 20, 50, 100, 1000);
        $scalar = static function ($value) { return is_scalar($value) ? (string) $value : ''; };
        $submitted = $scalar(\Contao\Input::post('FORM_SUBMIT')) === 'dpi';
        $field = $scalar($submitted ? \Contao\Input::post('field') : \Contao\Input::get('field'));
        $search = trim($scalar($submitted ? \Contao\Input::post('search') : \Contao\Input::get('search')));
        $resultsValue = $submitted ? \Contao\Input::post('results') : \Contao\Input::get('results');

        // Upgrade old pagination URLs once. New links carry the complete search state.
        if (!$submitted && \Contao\Input::get('search') === null && \Contao\Input::get('page') !== null) {
            $search = $scalar($this->session()->get('dpi_search'));
            $field = $scalar($this->session()->get('dpi_field'));
        }
        if (!in_array($field, $options, true)) $field = 'title';
        if ($resultsValue === null) $resultsValue = $this->session()->get('dpi_results');
        $results = (int) $scalar($resultsValue);
        if (!in_array($results, $arrResults, true)) $results = 10;
        $page = filter_var(\Contao\Input::get('page'), FILTER_VALIDATE_INT, array(
            'options' => array('min_range' => 1, 'max_range' => intdiv(PHP_INT_MAX, $results) - 1)
        ));
        if ($page === false || $submitted) $page = 1;

        $this->session()->set('dpi_field', $field);
        $this->session()->set('dpi_search', $search);
        $this->session()->set('dpi_results', $results);

        // POST/Redirect/GET avoids resubmission and makes browser Back/Forward reliable.
        // Also normalize old /search/.../field/... links before paginating them.
        $request = \Contao\System::getContainer()->get('request_stack')->getCurrentRequest();
        if ($submitted || ($search !== '' && (!$request->query->has('search') || !$request->query->has('results')))) {
            $this->redirect(self::searchUrl($objPageSearch, $search, $field, $results, $page), 303);
        }
		$arrFoundSongs = array();
		$foundResults = false;
		$totalcount = 0;

		
		if (strlen($search))
		{
			$objSong = $this->Database->prepare("SELECT tl_broadcast_song.id FROM tl_adventure, tl_broadcast, tl_broadcast_song LEFT JOIN tl_song_cover ON tl_broadcast_song.id = tl_song_cover.song WHERE tl_broadcast.id = tl_broadcast_song.pid AND tl_broadcast_song.$field LIKE ? AND tl_adventure.id = tl_broadcast.pid ORDER BY cast(tl_broadcast.date AS UNSIGNED) DESC")
				->execute('%' . $search . '%');
			$totalcount = $objSong->numRows;
            $lastPage = max(1, (int) ceil($totalcount / $results));
            if ($page > $lastPage) {
                $this->redirect(self::searchUrl($objPageSearch, $search, $field, $results, $lastPage), 303);
            }

			$objSong = $this->Database->prepare("SELECT tl_broadcast_song.*, tl_song_cover.cover, tl_adventure.id adventureid, tl_adventure.title adventuretitle, tl_adventure.numbering FROM tl_adventure, tl_broadcast, tl_broadcast_song LEFT JOIN tl_song_cover ON tl_broadcast_song.id = tl_song_cover.song WHERE tl_broadcast.id = tl_broadcast_song.pid AND tl_broadcast_song.$field LIKE ? AND tl_adventure.id = tl_broadcast.pid ORDER BY tl_broadcast_song.artist ASC, cast(tl_broadcast.date AS UNSIGNED) DESC, tl_broadcast_song.id ASC")
				->limit($results, ($page-1)*$results)
				->execute('%' . $search . '%');
				
			while ($objSong->next())
			{
				$song_genres = \Contao\StringUtil::deserialize($objSong->genres, true);
				$foundgenres = array();
				foreach ($song_genres as $key => $genre)
				{
					if (is_scalar($genre) && isset($genres[$genre]['name'])) $foundgenres[] = $genres[$genre]['name'];
				}
				switch ($objSong->songtype)
				{
					case 1:
						$special = '<img src="bundles/hschottmauriga/images/jukebox.gif" alt="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['musicbox'][0] . '" title="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['musicbox'][1] . '" />';
						break;
					case 2:
						$special = '<img src="bundles/hschottmauriga/images/wunderwerk.gif" alt="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['wunderwerk'][0] . '" title="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['wunderwerk'][1] . '" />';
						break;
					default:
						$special = '';
						break;
				}

				if ($objSong->cover > 0)
				{
					$imagename = $this->getCovername($objSong->cover);
					$cover = '<img src="' . htmlspecialchars((string) $imagename, ENT_QUOTES, 'UTF-8') . '" alt="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['cover'][0] . '" title="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['cover'][1] . '" width="64" height="64" />';
				}
				else
				{
					$cover = '<img src="bundles/hschottmauriga/images/cover-placeholder.svg" alt="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['cover'][0] . '" title="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['cover'][1] . '" />';
				}
				array_push($arrFoundSongs, array(
					'adventure' => \Contao\StringUtil::specialchars($objSong->numbering) . '. ' . \Contao\StringUtil::specialchars($objSong->adventuretitle),
					'title' => \Contao\StringUtil::specialchars($objSong->title),
					'artist' => \Contao\StringUtil::specialchars($objSong->artist),
					'album' => \Contao\StringUtil::specialchars($objSong->album),
					'year' => \Contao\StringUtil::specialchars($objSong->year),
					'special' => $special,
					'cover' => $cover,
					'idurl' => $objPageSong->getFrontendUrl('/song/' . $objSong->id),
					'titleurl' => self::searchUrl($objPageSearch, (string) $objSong->title, 'title', $results),
					'advurl' => $objPageAdventure->getFrontendUrl('/adventure/' . $objSong->adventureid),
					'labelurl' => self::searchUrl($objPageSearch, (string) $objSong->labelcode, 'labelcode', $results),
					'albumurl' => self::searchUrl($objPageSearch, (string) $objSong->album, 'album', $results),
					'artisturl' => self::searchUrl($objPageSearch, (string) $objSong->artist, 'artist', $results),
					'labelcode' => \Contao\StringUtil::specialchars($objSong->labelcode),
					'length' => \Contao\StringUtil::specialchars($objSong->length),
					'composer' => \Contao\StringUtil::specialchars($objSong->composer),
					'sequence' => \Contao\StringUtil::specialchars($objSong->sequence),
					'genres' => $foundgenres,
					'sorting' => \Contao\StringUtil::specialchars($objSong->sorting),
				));
				$foundResults = true;
			}
		}
		
		// Pagination
		$objPagination = new \Contao\Pagination($totalcount, $results);
		$this->Template->pagination = $objPagination->generate("\n  ");

		$this->loadLanguageFile('tl_module');
		$this->Template->savedSearch = $search;
		$this->Template->savedField = $field;
		$this->Template->savedResults = $results;
		$this->Template->options = $options;
		$this->Template->results = $arrResults;
		$this->Template->formaction = $objPage->getFrontendUrl('');
		$this->Template->foundResults = $foundResults;
        $this->Template->searchPerformed = $search !== '';
        $this->Template->noResults = $GLOBALS['TL_LANG']['tl_module']['auriga_no_results'];
		$this->Template->foundsongs = $arrFoundSongs;
		$this->Template->resFrom = $totalcount > 0 ? ($page-1)*$results+1 : 0;
		$until = ($page-1)*$results+$results;
		$this->Template->resTo = ($until > $totalcount) ? $totalcount : $until;
		$this->Template->resTotal = $totalcount;
		$this->Template->searchResult = $GLOBALS['TL_LANG']['tl_module']['search_result'];
		$this->Template->of = $GLOBALS['TL_LANG']['tl_module']['of'];
		$this->Template->adventure = $GLOBALS['TL_LANG']['tl_module']['adventure'];
		$this->Template->genres = $GLOBALS['TL_LANG']['tl_module']['song_genres'];
		$this->Template->resultsperpage = $GLOBALS['TL_LANG']['tl_module']['resultsperpage'];
		$this->Template->lngIn = $GLOBALS['TL_LANG']['tl_module']['in'];
		$this->Template->lngSearch = $GLOBALS['TL_LANG']['tl_module']['search'];
		$this->Template->lngSearchButton = $GLOBALS['TL_LANG']['tl_module']['searchbutton'];
		foreach ($options as $option)
		{
			$this->Template->$option = $GLOBALS['TL_LANG']['tl_module']['song_' . $option];
		}
	}
    private static function searchUrl(PageModel $page, string $search, string $field, int $results, int $number = 1): string
    {
        $url = $page->getFrontendUrl();
        $parameters = ['search' => $search, 'field' => $field, 'results' => $results];
        if ($number > 1) $parameters['page'] = $number;
        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }

}

