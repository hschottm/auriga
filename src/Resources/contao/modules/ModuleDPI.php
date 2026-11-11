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
		if (TL_MODE == 'BE')
		{
			$objTemplate = new \BackendTemplate('be_wildcard');

			$objTemplate->wildcard = '### DPI ###';
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
		
		// Request/session values may be null or arrays; pagination requires integers.
		$options = array('title', 'artist', 'album', 'year', 'labelcode');
		$arrResults = array(10, 20, 50, 100, 1000);
		$scalar = static function ($value) { return is_scalar($value) ? (string) $value : ''; };
		$savedField = $scalar($this->Session->get('dpi_field'));
		$savedSearch = $scalar($this->Session->get('dpi_search'));
		if ($scalar($this->Input->get('page')) === '') $savedSearch = '';
		$field = $scalar($this->Input->post('field'));
		if ($field === '') $field = $scalar($this->Input->get('field'));
		if ($field === '') $field = $savedField;
		if (!in_array($field, $options, true)) $field = 'title';
		$search = $scalar($this->Input->post('search'));
		if ($search === '') $search = $scalar($this->Input->get('search'));
		if ($search === '') $search = $savedSearch;
		$results = $scalar($this->Input->post('results'));
		if ($results === '') $results = $scalar($this->Session->get('dpi_results'));
		$results = (int) $results;
		if (!in_array($results, $arrResults, true)) $results = 10;
		$page = filter_var($this->Input->get('page'), FILTER_VALIDATE_INT, array(
			'options' => array('min_range' => 1, 'max_range' => intdiv(PHP_INT_MAX, $results) - 1)
		));
		if ($page === false) $page = 1;

		$this->Session->set('dpi_field', $field);
		$this->Session->set('dpi_search', $search);
		$this->Session->set('dpi_results', $results);
		$arrFoundSongs = array();
		$foundResults = false;
		$totalcount = 0;

		
		if (strlen($search))
		{
			$objSong = $this->Database->prepare("SELECT tl_broadcast_song.id FROM tl_adventure, tl_broadcast, tl_broadcast_song LEFT JOIN tl_song_cover ON tl_broadcast_song.id = tl_song_cover.song WHERE tl_broadcast.id = tl_broadcast_song.pid AND tl_broadcast_song.$field LIKE ? AND tl_adventure.id = tl_broadcast.pid ORDER BY cast(tl_broadcast.date AS UNSIGNED) DESC")
				->execute('%' . $search . '%');
			$totalcount = $objSong->numRows;

			$objSong = $this->Database->prepare("SELECT tl_broadcast_song.*, tl_song_cover.cover, tl_adventure.id adventureid, tl_adventure.title adventuretitle, tl_adventure.numbering FROM tl_adventure, tl_broadcast, tl_broadcast_song LEFT JOIN tl_song_cover ON tl_broadcast_song.id = tl_song_cover.song WHERE tl_broadcast.id = tl_broadcast_song.pid AND tl_broadcast_song.$field LIKE ? AND tl_adventure.id = tl_broadcast.pid ORDER BY tl_broadcast_song.artist ASC, cast(tl_broadcast.date AS UNSIGNED) DESC")
				->limit($results, ($page-1)*$results)
				->execute('%' . $search . '%');
				
			while ($objSong->next())
			{
				$song_genres = deserialize($objSong->genres, true);
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
					$cover = '<img src="' . htmlspecialchars((string) $imagename, ENT_QUOTES, 'UTF-8') . '" alt="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['cover'][0] . '" title="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['cover'][1] . '" width="22" height="22" />';
				}
				else
				{
					$cover = '<img src="bundles/hschottmauriga/images/cd.gif" alt="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['cover'][0] . '" title="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['cover'][1] . '" />';
				}
				array_push($arrFoundSongs, array(
					'adventure' => specialchars($objSong->numbering) . '. ' . specialchars($objSong->adventuretitle),
					'title' => specialchars($objSong->title),
					'artist' => specialchars($objSong->artist),
					'album' => specialchars($objSong->album),
					'year' => specialchars($objSong->year),
					'special' => $special,
					'cover' => $cover,
					'idurl' => $objPageSong->getFrontendUrl('/song/' . $objSong->id),
					'titleurl' => $objPageSearch->getFrontendUrl('/search/' . specialchars($objSong->title) . '/field/title'),
					'advurl' => $objPageAdventure->getFrontendUrl('/adventure/' . $objSong->adventureid),
					'labelurl' => $objPageSearch->getFrontendUrl('/search/' . specialchars($objSong->labelcode) . '/field/labelcode'),
					'albumurl' => $objPageSearch->getFrontendUrl('/search/' . specialchars($objSong->album) . '/field/album'),
					'artisturl' => $objPageSearch->getFrontendUrl('/search/' . specialchars($objSong->artist) . '/field/artist'),
					'labelcode' => specialchars($objSong->labelcode),
					'length' => specialchars($objSong->length),
					'composer' => specialchars($objSong->composer),
					'sequence' => specialchars($objSong->sequence),
					'genres' => $foundgenres,
					'sorting' => specialchars($objSong->sorting),
				));
				$foundResults = true;
			}
		}
		
		// Pagination
		$objPagination = new \Pagination($totalcount, $results);
		$this->Template->pagination = $objPagination->generate("\n  ");

		$this->loadLanguageFile('tl_module');
		$this->Template->savedSearch = $search;
		$this->Template->savedField = $field;
		$this->Template->savedResults = $results;
		$this->Template->options = $options;
		$this->Template->results = $arrResults;
		$this->Template->formaction = $objPage->getFrontendUrl('');
		$this->Template->foundResults = $foundResults;
		$this->Template->foundsongs = $arrFoundSongs;
		$this->Template->resFrom = ($page-1)*$results+1;
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
}

