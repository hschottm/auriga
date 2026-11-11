<?php 

namespace Hschottm\AurigaBundle;

/**
 * TYPOlight webCMS
 * Copyright (C) 2005-2009 Leo Feyer
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 2.1 of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at http://www.gnu.org/licenses/.
 *
 * PHP version 5
 * @copyright  Leo Feyer 2005-2009
 * @author     Leo Feyer <leo@typolight.org>
 * @package    Frontend
 * @license    LGPL
 * @filesource
 */

use Contao\PageModel;

/**
 * Class ContentBackgroundMusic
 *
 * Front end content element "list".
 * @copyright  Leo Feyer 2005-2009
 * @author     Leo Feyer <leo@typolight.org>
 * @package    Controller
 */
class ContentSpecialMusic extends \Contao\ContentElement
{

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'ce_specialmusic';

	public function generate()
	{
		if ($this->aurigaTpl)
		{
			$this->strTemplate = $this->aurigaTpl;
		}
	
		return parent::generate();
	}

	/**
	 * Generate content element
	 */
	protected function compile()
	{
		global $objPage;
		
		$this->loadLanguageFile('tl_module');
		$this->loadLanguageFile('tl_broadcast_song');
		$arrGenres = $this->Database->prepare("SELECT * FROM tl_genre")
			->execute()
			->fetchAllAssoc();
		$genres = array();
		foreach ($arrGenres as $genre)
		{
			$genres[$genre['id']] = $genre;
		}

		$objPageSong = ModulePTW::resolvePage($this->jumpTo_song);

		$objSong = $this->Database->prepare("SELECT tl_broadcast_song.*, tl_song_cover.cover, tl_adventure.title adventuretitle, tl_adventure.numbering FROM tl_adventure, tl_broadcast, tl_broadcast_song LEFT JOIN tl_song_cover ON tl_broadcast_song.id = tl_song_cover.song WHERE tl_broadcast.id = tl_broadcast_song.pid AND tl_adventure.id = tl_broadcast.pid AND tl_broadcast_song.songtype = ? ORDER BY cast(tl_broadcast.date AS UNSIGNED) DESC")
			->execute($this->special_type);
		$arrSongs = array();
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
				$imagename = ModulePTW::_getCovername($objSong->cover);
				$cover = '<img src="' . htmlspecialchars((string) $imagename, ENT_QUOTES, 'UTF-8') . '" alt="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['cover'][0] . '" title="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['cover'][1] . '" width="22" height="22" />';
			}
			else
			{
				$cover = '<img src="bundles/hschottmauriga/images/cd.gif" alt="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['cover'][0] . '" title="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['cover'][1] . '" />';
			}
			$arrSongs[] = array
			(
				'adventure' => \Contao\StringUtil::specialchars($objSong->numbering) . '. ' . \Contao\StringUtil::specialchars($objSong->adventuretitle),
				'title' => \Contao\StringUtil::specialchars($objSong->title),
				'artist' => \Contao\StringUtil::specialchars($objSong->artist),
				'album' => \Contao\StringUtil::specialchars($objSong->album),
				'year' => \Contao\StringUtil::specialchars($objSong->year),
				'special' => $special,
				'cover' => $cover,
				'titleurl' => $objPageSong->getFrontendUrl('/song/' . $objSong->id),
				'labelcode' => \Contao\StringUtil::specialchars($objSong->labelcode),
				'length' => \Contao\StringUtil::specialchars($objSong->length),
				'composer' => \Contao\StringUtil::specialchars($objSong->composer),
				'sequence' => \Contao\StringUtil::specialchars($objSong->sequence),
				'genres' => $foundgenres,
				'sorting' => \Contao\StringUtil::specialchars($objSong->sorting),
			);
		}
		$this->Template->songs = $arrSongs;
		$this->Template->lngTitle = $GLOBALS['TL_LANG']['tl_module']['song_title'];
		$this->Template->lngArtist = $GLOBALS['TL_LANG']['tl_module']['song_artist'];
		$this->Template->lngAlbum = $GLOBALS['TL_LANG']['tl_module']['song_album'];
		$this->Template->lngYear = $GLOBALS['TL_LANG']['tl_module']['song_year'];
		$this->Template->lngLabelcode = $GLOBALS['TL_LANG']['tl_module']['song_labelcode'];
		$this->Template->lngSongtype = $GLOBALS['TL_LANG']['tl_module']['song_songtype'];
		$this->Template->lngLength = $GLOBALS['TL_LANG']['tl_module']['song_length'];
		$this->Template->lngComposer = $GLOBALS['TL_LANG']['tl_module']['song_composer'];
		$this->Template->lngCover = $GLOBALS['TL_LANG']['tl_module']['song_cover'];
		$this->Template->lngGenres = $GLOBALS['TL_LANG']['tl_module']['song_genres'];
		$this->Template->lngAdventure = $GLOBALS['TL_LANG']['tl_module']['adventure'];
	}
}
