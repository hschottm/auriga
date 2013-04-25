<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

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


/**
 * Class ContentBackgroundMusic
 *
 * Front end content element "list".
 * @copyright  Leo Feyer 2005-2009
 * @author     Leo Feyer <leo@typolight.org>
 * @package    Controller
 */
class ContentBackgroundMusic extends ContentElement
{

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'ce_backgroundmusic';

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

		$objSong = $this->Database->prepare("SELECT tl_broadcast_song.*, tl_song_cover.cover, tl_adventure.title adventuretitle, tl_adventure.numbering FROM tl_adventure, tl_broadcast, tl_broadcast_song LEFT JOIN tl_song_cover ON tl_broadcast_song.id = tl_song_cover.song WHERE tl_broadcast.id = tl_broadcast_song.pid AND tl_adventure.id = tl_broadcast.pid AND tl_broadcast_song.songtype = ? ORDER BY cast(tl_broadcast.date AS UNSIGNED) DESC")
			->execute(3);
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
				$imagename = ModulePTW::_getCovername($objSong->cover);
				$cover = '<img src="' . $imagename . '" alt="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['cover'][0] . '" title="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['cover'][1] . '" width="22" height="22" />';
			}
			else
			{
				$cover = '<img src="system/modules/auriga/html/images/cd.gif" alt="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['cover'][0] . '" title="' . $GLOBALS['TL_LANG']['tl_broadcast_song']['cover'][1] . '" />';
			}
			$arrSongs[] = array
			(
				'adventure' => specialchars($objSong->numbering) . '. ' . specialchars($objSong->adventuretitle),
				'title' => specialchars($objSong->title),
				'artist' => specialchars($objSong->artist),
				'album' => specialchars($objSong->album),
				'year' => specialchars($objSong->year),
				'special' => $special,
				'cover' => $cover,
				'titleurl' => $this->generateFrontendUrl($objPageSong->row(), '/song/' . $objSong->id),
				'labelcode' => specialchars($objSong->labelcode),
				'length' => specialchars($objSong->length),
				'composer' => specialchars($objSong->composer),
				'sequence' => specialchars($objSong->sequence),
				'genres' => $foundgenres,
				'sorting' => specialchars($objSong->sorting),
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

?>