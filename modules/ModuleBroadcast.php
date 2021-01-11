<?php 

namespace Contao;

/**
 * Class ModuleBroadcast
 *
 * Front end module "broadcast".
 * @copyright  Helmut Schottmüller 2010
 * @author     Helmut Schottmüller <typolight@aurealis.de>
 * @package    Controller
 */
class ModuleBroadcast extends \ModulePTW
{

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'mod_auriga_broadcast';


	/**
	 * @return string
	 */
	public function generate()
	{
		if (TL_MODE == 'BE')
		{
			$objTemplate = new BackendTemplate('be_wildcard');

			$objTemplate->wildcard = '### BROADCAST ###';
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
		if (strlen($this->jumpTo_search))
		{
			$objPageSearch = $this->Database->prepare("SELECT id, alias FROM tl_page WHERE id=?")
										  ->limit(1)
										  ->execute($this->jumpTo_search);
		}
		else
		{
			$objPageSearch = $objPage;
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
		$this->loadLanguageFile('tl_broadcast_song');
		$broadcastdate = $this->Input->get('broadcast');
		$objAdventure = null;
		if ($broadcastdate < 1) return;
		$this->Session->set('foundimages', '');
		$arrProviders = $this->Database->prepare("SELECT * FROM tl_broadcast_provider")
			->execute()
			->fetchAllAssoc();
		$providers = array();
		foreach ($arrProviders as $provider)
		{
			$providers[$provider['id']] = $provider;
		}

		$arrGenres = $this->Database->prepare("SELECT * FROM tl_genre")
			->execute()
			->fetchAllAssoc();
		$genres = array();
		foreach ($arrGenres as $genre)
		{
			$genres[$genre['id']] = $genre;
		}
		$broadcast_ids = array();
		$arrBroadcasts = array();
		$objBroadcast = $this->Database->prepare("SELECT * FROM tl_broadcast WHERE `date` = ? ORDER BY sorting ASC")
			->execute($broadcastdate);
		while ($objBroadcast->next())
		{
			if (is_null($objAdventure))
			{
				$objAdventure = $this->Database->prepare("SELECT * FROM tl_adventure WHERE id = ?")
					->execute($objBroadcast->pid);
			}
			$broadcast_providers = deserialize($objBroadcast->providers, true);
			$foundproviders = array();
			foreach ($broadcast_providers as $key => $provider)
			{
				if (strlen($provider)) $foundproviders[$providers[$provider]['initials']] = $providers[$provider];
			}
			$arrFiles = $this->Database->prepare("SELECT filename FROM tl_broadcast_file WHERE pid = ? ORDER BY sorting ASC")
				->execute($objBroadcast->id)
				->fetchEach('filename');
			$arrSongs = array();
			$objSong = $this->Database->prepare("SELECT tl_broadcast_song.*, tl_song_cover.cover FROM tl_broadcast_song LEFT JOIN tl_song_cover ON tl_broadcast_song.id = tl_song_cover.song WHERE tl_broadcast_song.pid = ? AND tl_broadcast_song.songtype <> ? ORDER BY sorting ASC")
				->execute($objBroadcast->id, 3);
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
				$arrSongs[] = array
				(
					'title' => specialchars($objSong->title),
					'artist' => specialchars($objSong->artist),
					'album' => specialchars($objSong->album),
					'year' => specialchars($objSong->year),
					'special' => $special,
					'cover' => $cover,
					'idurl' => $this->generateFrontendUrl($objPageSong->row(), '/song/' . $objSong->id),
					'titleurl' => $this->generateFrontendUrl($objPageSearch->row(), '/search/' . specialchars($objSong->title) . '/field/title'),
					'labelurl' => $this->generateFrontendUrl($objPageSearch->row(), '/search/' . specialchars($objSong->labelcode) . '/field/labelcode'),
					'albumurl' => $this->generateFrontendUrl($objPageSearch->row(), '/search/' . specialchars($objSong->album) . '/field/album'),
					'artisturl' => $this->generateFrontendUrl($objPageSearch->row(), '/search/' . specialchars($objSong->artist) . '/field/artist'),
					'labelcode' => specialchars($objSong->labelcode),
					'length' => specialchars($objSong->length),
					'composer' => specialchars($objSong->composer),
					'sequence' => specialchars($objSong->sequence),
					'genres' => $foundgenres,
					'sorting' => specialchars($objSong->sorting),
				);
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
				'files' => $arrFiles,
				'songs' => $arrSongs,
				'providers' => $foundproviders,
				'url' => $this->generateFrontendUrl($objPageBroadcast->row(), '/broadcast/' . $objBroadcast->id),
				'id' => $objBroadcast->id
			);
		}

		$arrTitlesongs = $this->Database->prepare("SELECT tl_broadcast_song.*, tl_broadcast.date, tl_song_cover.cover FROM tl_broadcast, tl_broadcast_song LEFT JOIN tl_song_cover ON tl_broadcast_song.id = tl_song_cover.song WHERE songtype = ? AND tl_broadcast_song.pid = tl_broadcast.id AND tl_broadcast.pid = ? ORDER BY tl_broadcast.date DESC")
			->execute(3, $objBroadcast->pid)
			->fetchAllAssoc();

		if (count($arrTitlesongs))
		{
			foreach ($arrTitlesongs as $titlesong)
			{
				if ($titlesong['date'] <= $objBroadcast->date)
				{
					$foundgenres = array();
					$song_genres = deserialize($titlesong['genres'], true);
					foreach ($song_genres as $key => $genre)
					{
						if (strlen($genre))
						{
							$arrGenre = $this->Database->prepare("SELECT * FROM tl_genre WHERE id = ?")
								->execute($genre)
								->fetchAssoc();
							$foundgenres[] = $arrGenre['name'];
						}
					}
					$titlesong['genres'] = $foundgenres;
					if ($titlesong['cover'] > 0)
					{
						$imagename = $this->getCovername($titlesong['cover']);
						$cover = '<img src="' . $imagename . '" alt="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['cover'][0] . '" title="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['cover'][1] . '" width="22" height="22" />';
					}
					else
					{
						$cover = '<img src="system/modules/auriga/html/images/cd.gif" alt="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['cover'][0] . '" title="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['cover'][1] . '" />';
					}
					$titlesong['cover'] = $cover;
					switch ($titlesong['songtype'])
					{
						case 1:
							$special = '<img src="system/modules/auriga/html/images/jukebox.gif" alt="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['musicbox'][0] . '" title="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['musicbox'][1] . '" />';
							break;
						case 2:
							$special = '<img src="system/modules/auriga/html/images/wunderwerk.gif" alt="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['wunderwerk'][0] . '" title="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['wunderwerk'][1] . '" />';
							break;
						case 3:
							$special = '<img src="system/modules/auriga/html/images/hintergrundmusik.gif" alt="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['hintergrundmusik'][0] . '" title="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['hintergrundmusik'][1] . '" />';
							break;
						default:
							$special = '';
							break;
					}
					$titlesong['titleurl'] = $this->generateFrontendUrl($objPageSong->row(), '/song/' . $titlesong['id']);
					$titlesong['special'] = $special;
					$this->Template->titlesong = $titlesong;
					continue;
				}
			}
		}

		$this->loadLanguageFile('tl_module');
		$this->Template->adventure = $objAdventure->row();
		$this->Template->urlUp = $this->generateFrontendUrl($objPageAdventure->row(), '/adventure/' . $objBroadcast->pid);
		$this->Template->broadcasts = $arrBroadcasts;
		$this->Template->lngBackgroundMusic = $GLOBALS['TL_LANG']['tl_module']['background_music'];
		$this->Template->lngTitle = $GLOBALS['TL_LANG']['tl_module']['song_title'];
		$this->Template->lngArtist = $GLOBALS['TL_LANG']['tl_module']['song_artist'];
		$this->Template->lngAlbum = $GLOBALS['TL_LANG']['tl_module']['song_album'];
		$this->Template->lngYear = $GLOBALS['TL_LANG']['tl_module']['song_year'];
		$this->Template->lngLablecode = $GLOBALS['TL_LANG']['tl_module']['song_labelcode'];
		$this->Template->lngGenres = $GLOBALS['TL_LANG']['tl_module']['song_genres'];
		$this->Template->nav_first_url = '';
		$this->Template->nav_prev_url = '';
		$this->Template->nav_next_url = '';
		$this->Template->nav_last_url = '';
		$firstBroadcast = $this->Database->prepare("SELECT * FROM tl_broadcast WHERE pid = ? ORDER BY sorting ASC")
			->execute($objBroadcast->pid)
			->fetchAssoc();
		if ($firstBroadcast['date'] == $objBroadcast->date)
		{
			$this->Template->nav_first_url = '';
		}
		else
		{
			$this->Template->nav_first_url = $this->generateFrontendUrl($objPage->row(), '/broadcast/' . $firstBroadcast['date']);
		}
		$lastBroadcast = $this->Database->prepare("SELECT * FROM tl_broadcast WHERE pid = ? ORDER BY sorting DESC")
			->execute($objBroadcast->pid)
			->fetchAssoc();
		if ($lastBroadcast['date'] == $objBroadcast->date)
		{
			$this->Template->nav_last_url = '';
		}
		else
		{
			$this->Template->nav_last_url = $this->generateFrontendUrl($objPage->row(), '/broadcast/' . $lastBroadcast['date']);
		}
		$nextBroadcast = $this->Database->prepare("SELECT * FROM tl_broadcast WHERE pid = ? AND `date` > ? ORDER BY sorting ASC")
			->execute($objBroadcast->pid, $objBroadcast->date)
			->fetchAssoc();
		if (!is_array($nextBroadcast) || $objBroadcast->date == $lastBroadcast['date'])
		{
			$this->Template->nav_next_url = '';
		}
		else
		{
			$this->Template->nav_next_url = $this->generateFrontendUrl($objPage->row(), '/broadcast/' . $nextBroadcast['date']);
		}
		$prevBroadcast = $this->Database->prepare("SELECT * FROM tl_broadcast WHERE pid = ? AND `date` < ? ORDER BY sorting DESC")
			->execute($objBroadcast->pid, $objBroadcast->date)
			->fetchAssoc();
		if (!is_array($prevBroadcast) || $objBroadcast->date == $firstBroadcast['date'])
		{
			$this->Template->nav_prev_url = '';
		}
		else
		{
			$this->Template->nav_prev_url = $this->generateFrontendUrl($objPage->row(), '/broadcast/' . $prevBroadcast['date']);
		}
		$this->Template->nav_first_title = $GLOBALS['TL_LANG']['tl_module']['goto_first_broadcast'];
		$this->Template->nav_prev_title = $GLOBALS['TL_LANG']['tl_module']['goto_previous_broadcast'];
		$this->Template->nav_next_title = $GLOBALS['TL_LANG']['tl_module']['goto_next_broadcast'];
		$this->Template->nav_last_title = $GLOBALS['TL_LANG']['tl_module']['goto_last_broadcast'];
	}
}

