<?php 

namespace Contao;


/**
 * Class ModuleDPI
 *
 * Front end module "DPI".
 * @copyright  Helmut Schottmüller 2010
 * @author     Helmut Schottmüller <typolight@aurealis.de>
 * @package    Controller
 */
class ModuleDPI extends \ModulePTW
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
			$objTemplate = new BackendTemplate('be_wildcard');

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
		if (strlen($this->jumpTo_adventure))
		{
			$objPageAdventure = $this->Database->prepare("SELECT id, alias FROM tl_page WHERE id=?")
										  ->limit(1)
										  ->execute($this->jumpTo_adventure);
		}
		else
		{
			$objPageAdventure = $objPage;
		}
		if (strlen($this->jumpTo_broadcast))
		{
			$objPageBroadcast = $this->Database->prepare("SELECT id, alias FROM tl_page WHERE id=?")
										  ->limit(1)
										  ->execute($this->jumpTo_broadcast);
		}
		else
		{
			$objPageBroadcast = $objPage;
		}
		if (strlen($this->jumpTo_song))
		{
			$objPageSong = $this->Database->prepare("SELECT id, alias FROM tl_page WHERE id=?")
										  ->limit(1)
										  ->execute($this->jumpTo_song);
		}
		else
		{
			$objPageSong = $objPage;
		}
		$arrGenres = $this->Database->prepare("SELECT * FROM tl_genre")
			->execute()
			->fetchAllAssoc();
		$genres = array();
		foreach ($arrGenres as $genre)
		{
			$genres[$genre['id']] = $genre;
		}
		
		$savedField = strlen($this->Session->get('dpi_field')) ? $this->Session->get('dpi_field') : '';
		$savedSearch = strlen($this->Session->get('dpi_search')) ? $this->Session->get('dpi_search') : '';
		if (!strlen($this->Input->get('page'))) $savedSearch = '';
		$savedResults = strlen($this->Session->get('dpi_results')) ? $this->Session->get('dpi_results') : '';
		$field = strlen($this->Input->post('field')) ? $this->Input->post('field') : ((strlen($this->Input->get('field'))) ? $this->Input->get('field') : $savedField);
		$search = strlen($this->Input->post('search')) ? $this->Input->post('search') : ((strlen($this->Input->get('search'))) ? $this->Input->get('search') : $savedSearch);
		$results = strlen($this->Input->post('results')) ? $this->Input->post('results') : $savedResults;
		$start = 0;
		
		$this->Session->set('dpi_field', $field);
		$this->Session->set('dpi_search', $search);
		$this->Session->set('dpi_results', $results);
		$arrFoundSongs = array();
		$foundResults = false;
		$totalcount = 0;
		$page = (strlen($this->Input->get('page'))) ? $this->Input->get('page') : 1;
		
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
					if (strlen($genre)) $foundgenres[] = $genres[$genre]['name'];
				}
				switch ($objSong->songtype)
				{
					case 1:
						$special = '<img src="system/modules/auriga/html/images/jukebox.gif" alt="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['musicbox'][0] . '" title="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['musicbox'][1] . '" />';
						break;
					case 2:
						$special = '<img src="system/modules/auriga/html/images/wunderwerk.gif" alt="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['wunderwerk'][0] . '" title="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['wunderwerk'][1] . '" />';
						break;
					default:
						$special = '';
						break;
				}

				if ($objSong->cover > 0)
				{
					$imagename = $this->getCovername($objSong->cover);
					$cover = '<img src="' . $imagename . '" alt="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['cover'][0] . '" title="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['cover'][1] . '" width="22" height="22" />';
				}
				else
				{
					$cover = '<img src="system/modules/auriga/html/images/cd.gif" alt="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['cover'][0] . '" title="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['cover'][1] . '" />';
				}
				array_push($arrFoundSongs, array(
					'adventure' => specialchars($objSong->numbering) . '. ' . specialchars($objSong->adventuretitle),
					'title' => specialchars($objSong->title),
					'artist' => specialchars($objSong->artist),
					'album' => specialchars($objSong->album),
					'year' => specialchars($objSong->year),
					'special' => $special,
					'cover' => $cover,
					'idurl' => $this->generateFrontendUrl($objPageSong->row(), '/song/' . $objSong->id),
					'titleurl' => $this->generateFrontendUrl($objPage->row(), '/search/' . specialchars($objSong->title) . '/field/title'),
					'advurl' => $this->generateFrontendUrl($objPageAdventure->row(), '/adventure/' . $objSong->adventureid),
					'labelurl' => $this->generateFrontendUrl($objPage->row(), '/search/' . specialchars($objSong->labelcode) . '/field/labelcode'),
					'albumurl' => $this->generateFrontendUrl($objPage->row(), '/search/' . specialchars($objSong->album) . '/field/album'),
					'artisturl' => $this->generateFrontendUrl($objPage->row(), '/search/' . specialchars($objSong->artist) . '/field/artist'),
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
		
		$options = array(
			'title',
			'artist',
			'album',
			'year',
			'labelcode'
		);
		$arrResults = array(
			10,
			20,
			50,
			100,
			1000
		);

		// Pagination
		$objPagination = new Pagination($totalcount, $results);
		$this->Template->pagination = $objPagination->generate("\n  ");

		$this->loadLanguageFile('tl_module');
		$this->Template->savedSearch = $search;
		$this->Template->savedField = $field;
		$this->Template->savedResults = $results;
		$this->Template->options = $options;
		$this->Template->results = $arrResults;
		$this->Template->formaction = $this->generateFrontendUrl($objPage->row());
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

