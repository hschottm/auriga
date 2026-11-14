<?php 

namespace Hschottm\AurigaBundle;

use Contao\PageModel;

/**
 * Class ModuleBroadcast
 *
 * Front end module "broadcast".
 * @copyright  Helmut Schottmüller 2010
 * @author     Helmut Schottmüller <typolight@aurealis.de>
 * @package    Controller
 */
class ModuleBroadcast extends ModulePTW
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
		if ($this->isBackendRequest())
		{
			$objTemplate = new \Contao\BackendTemplate('be_wildcard');

			$objTemplate->wildcard = '### BROADCAST ###';
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
		
		// Redirect to jumpTo page
		$objPageAdventure = ModulePTW::resolvePage($this->jumpTo_adventure);
		$objPageBroadcast = ModulePTW::resolvePage($this->jumpTo_broadcast);
		$objPageSearch = ModulePTW::resolvePage($this->jumpTo_search);
		$objPageSong = ModulePTW::resolvePage($this->jumpTo_song);
		$this->loadLanguageFile('tl_broadcast_song');
		$broadcastdate = \Contao\Input::get('broadcast');
		$objAdventure = null;
		if ($broadcastdate === null || $broadcastdate === '') return;
		$broadcastdate = filter_var($broadcastdate, FILTER_VALIDATE_INT);
		if ($broadcastdate === false) throw new \Contao\CoreBundle\Exception\PageNotFoundException('Invalid Auriga broadcast date');
		$this->Template->titlesong = null;
		$this->session()->set('foundimages', '');
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
		if (!$objBroadcast->numRows) throw new \Contao\CoreBundle\Exception\PageNotFoundException('Auriga broadcast not found');
		while ($objBroadcast->next())
		{
			if (is_null($objAdventure))
			{
				$objAdventure = $this->Database->prepare("SELECT * FROM tl_adventure WHERE id = ?")
					->execute($objBroadcast->pid);
				if (!$objAdventure->numRows) throw new \Contao\CoreBundle\Exception\PageNotFoundException('Auriga adventure not found');
			}
			$broadcast_providers = \Contao\StringUtil::deserialize($objBroadcast->providers, true);
			$foundproviders = array();
			foreach ($broadcast_providers as $key => $provider)
			{
				if (is_scalar($provider) && isset($providers[$provider]['initials'])) $foundproviders[$providers[$provider]['initials']] = $providers[$provider];
			}
			$arrFiles = $this->Database->prepare("SELECT filename FROM tl_broadcast_file WHERE pid = ? ORDER BY sorting ASC")
				->execute($objBroadcast->id)
				->fetchEach('filename');
			$arrFiles = self::recordingUrls(array_merge($arrFiles, \Contao\StringUtil::deserialize($objBroadcast->files, true)));
			$arrSongs = array();
			$objSong = $this->Database->prepare("SELECT tl_broadcast_song.*, tl_song_cover.cover FROM tl_broadcast_song LEFT JOIN tl_song_cover ON tl_broadcast_song.id = tl_song_cover.song WHERE tl_broadcast_song.pid = ? AND tl_broadcast_song.songtype <> ? ORDER BY sorting ASC")
				->execute($objBroadcast->id, 3);
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
					$cover = '<img src="' . htmlspecialchars((string) $imagename, ENT_QUOTES, 'UTF-8') . '" alt="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['cover'][0] . '" title="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['cover'][1] . '" width="22" height="22" />';
				}
				else
				{
					$cover = '<img src="bundles/hschottmauriga/images/cover-placeholder.svg" alt="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['cover'][0] . '" title="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['cover'][1] . '" />';
				}
				$arrSongs[] = array
				(
					'title' => \Contao\StringUtil::specialchars($objSong->title),
					'artist' => \Contao\StringUtil::specialchars($objSong->artist),
					'album' => \Contao\StringUtil::specialchars($objSong->album),
					'year' => \Contao\StringUtil::specialchars($objSong->year),
					'special' => $special,
					'cover' => $cover,
					'idurl' => $objPageSong->getFrontendUrl('/song/' . $objSong->id),
					'titleurl' => $objPageSearch->getFrontendUrl('/search/' . \Contao\StringUtil::specialchars($objSong->title) . '/field/title'),
					'labelurl' => $objPageSearch->getFrontendUrl('/search/' . \Contao\StringUtil::specialchars($objSong->labelcode) . '/field/labelcode'),
					'albumurl' => $objPageSearch->getFrontendUrl('/search/' . \Contao\StringUtil::specialchars($objSong->album) . '/field/album'),
					'artisturl' => $objPageSearch->getFrontendUrl('/search/' . \Contao\StringUtil::specialchars($objSong->artist) . '/field/artist'),
					'labelcode' => \Contao\StringUtil::specialchars($objSong->labelcode),
					'length' => \Contao\StringUtil::specialchars($objSong->length),
					'composer' => \Contao\StringUtil::specialchars($objSong->composer),
					'sequence' => \Contao\StringUtil::specialchars($objSong->sequence),
					'genres' => $foundgenres,
					'sorting' => \Contao\StringUtil::specialchars($objSong->sorting),
				);
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
				'files' => $arrFiles,
				'songs' => $arrSongs,
				'providers' => $foundproviders,
				'url' => $objPageBroadcast->getFrontendUrl('/broadcast/' . $objBroadcast->id),
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
					$song_genres = \Contao\StringUtil::deserialize($titlesong['genres'], true);
					foreach ($song_genres as $key => $genre)
					{
						if (is_scalar($genre) && (string) $genre !== '')
						{
							$arrGenre = $this->Database->prepare("SELECT * FROM tl_genre WHERE id = ?")
								->execute($genre)
								->fetchAssoc();
							if (isset($arrGenre['name'])) $foundgenres[] = $arrGenre['name'];
						}
					}
					$titlesong['genres'] = $foundgenres;
					if ($titlesong['cover'] > 0)
					{
						$imagename = $this->getCovername($titlesong['cover']);
						$cover = '<img src="' . htmlspecialchars((string) $imagename, ENT_QUOTES, 'UTF-8') . '" alt="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['cover'][0] . '" title="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['cover'][1] . '" width="22" height="22" />';
					}
					else
					{
						$cover = '<img src="bundles/hschottmauriga/images/cover-placeholder.svg" alt="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['cover'][0] . '" title="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['cover'][1] . '" />';
					}
					$titlesong['cover'] = $cover;
					switch ($titlesong['songtype'])
					{
						case 1:
							$special = '<img src="bundles/hschottmauriga/images/jukebox.gif" alt="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['musicbox'][0] . '" title="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['musicbox'][1] . '" />';
							break;
						case 2:
							$special = '<img src="bundles/hschottmauriga/images/wunderwerk.gif" alt="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['wunderwerk'][0] . '" title="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['wunderwerk'][1] . '" />';
							break;
						case 3:
							$special = '<img src="bundles/hschottmauriga/images/hintergrundmusik.gif" alt="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['hintergrundmusik'][0] . '" title="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['hintergrundmusik'][1] . '" />';
							break;
						default:
							$special = '';
							break;
					}
					$titlesong['titleurl'] = $objPageSong->getFrontendUrl('/song/' . $titlesong['id']);
					$titlesong['special'] = $special;
					$this->Template->titlesong = $titlesong;
					continue;
				}
			}
		}

		$this->loadLanguageFile('tl_module');
		$this->Template->adventure = $objAdventure->row();
		$this->Template->urlUp = $objPageAdventure->getFrontendUrl('/adventure/' . $objBroadcast->pid);
		$this->Template->broadcasts = $arrBroadcasts;
		$this->Template->lngBackgroundMusic = $GLOBALS['TL_LANG']['tl_module']['background_music'];
		$this->Template->lngTitle = $GLOBALS['TL_LANG']['tl_module']['song_title'];
		$this->Template->lngArtist = $GLOBALS['TL_LANG']['tl_module']['song_artist'];
		$this->Template->lngAlbum = $GLOBALS['TL_LANG']['tl_module']['song_album'];
		$this->Template->lngYear = $GLOBALS['TL_LANG']['tl_module']['song_year'];
		$this->Template->lngLabelcode = $GLOBALS['TL_LANG']['tl_module']['song_labelcode'];
		$this->Template->lngGenres = $GLOBALS['TL_LANG']['tl_module']['song_genres'];
		$this->Template->nav_first_url = '';
		$this->Template->nav_prev_url = '';
		$this->Template->nav_next_url = '';
		$this->Template->nav_last_url = '';
		$firstBroadcast = $this->Database->prepare("SELECT * FROM tl_broadcast WHERE pid = ? ORDER BY sorting ASC")
			->execute($objBroadcast->pid)
			->fetchAssoc();
		if (!isset($firstBroadcast['date']) || $firstBroadcast['date'] == $objBroadcast->date)
		{
			$this->Template->nav_first_url = '';
		}
		else
		{
			$this->Template->nav_first_url = $objPageBroadcast->getFrontendUrl('/broadcast/' . $firstBroadcast['date']);
		}
		$lastBroadcast = $this->Database->prepare("SELECT * FROM tl_broadcast WHERE pid = ? ORDER BY sorting DESC")
			->execute($objBroadcast->pid)
			->fetchAssoc();
		if (!isset($lastBroadcast['date']) || $lastBroadcast['date'] == $objBroadcast->date)
		{
			$this->Template->nav_last_url = '';
		}
		else
		{
			$this->Template->nav_last_url = $objPageBroadcast->getFrontendUrl('/broadcast/' . $lastBroadcast['date']);
		}
		$nextBroadcast = $this->Database->prepare("SELECT * FROM tl_broadcast WHERE pid = ? AND `date` > ? ORDER BY sorting ASC")
			->execute($objBroadcast->pid, $objBroadcast->date)
			->fetchAssoc();
		if (!isset($nextBroadcast['date']) || $objBroadcast->date == ($lastBroadcast['date'] ?? null))
		{
			$this->Template->nav_next_url = '';
		}
		else
		{
			$this->Template->nav_next_url = $objPageBroadcast->getFrontendUrl('/broadcast/' . $nextBroadcast['date']);
		}
		$prevBroadcast = $this->Database->prepare("SELECT * FROM tl_broadcast WHERE pid = ? AND `date` < ? ORDER BY sorting DESC")
			->execute($objBroadcast->pid, $objBroadcast->date)
			->fetchAssoc();
		if (!isset($prevBroadcast['date']) || $objBroadcast->date == ($firstBroadcast['date'] ?? null))
		{
			$this->Template->nav_prev_url = '';
		}
		else
		{
			$this->Template->nav_prev_url = $objPageBroadcast->getFrontendUrl('/broadcast/' . $prevBroadcast['date']);
		}
		$this->Template->nav_first_title = $GLOBALS['TL_LANG']['tl_module']['goto_first_broadcast'];
		$this->Template->nav_prev_title = $GLOBALS['TL_LANG']['tl_module']['goto_previous_broadcast'];
		$this->Template->nav_next_title = $GLOBALS['TL_LANG']['tl_module']['goto_next_broadcast'];
		$this->Template->nav_last_title = $GLOBALS['TL_LANG']['tl_module']['goto_last_broadcast'];
	}
}

