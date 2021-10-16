<?php

namespace Hschottm\AurigaBundle;


/**
 * Class ModulePTW
 *
 * Front end module "PTW". Base class of all ptw related modules
 * @copyright  Helmut Schottmüller 2010
 * @author     Helmut Schottmüller <typolight@aurealis.de>
 * @package    Controller
 */
class ModulePTW extends \Module
{
	public function generate()
	{
		if ($this->aurigaTpl)
		{
			$this->strTemplate = $this->aurigaTpl;
		}
	
		return parent::generate();
	}

	/**
	 * Generate module
	 */
	protected function compile()
	{
	}
	
	public static function _getCovername($cover_id)
	{
		$uploadTypes = trimsplit(',', 'jpg,jpeg,png,gif');
		$foundimage = false;
		$info = ModulePTW::distributedPathInfo($cover_id);
		foreach ($uploadTypes as $extension)
		{
			$imagename = $GLOBALS['TL_CONFIG']['auriga']['coverpath'] . $info['fullpath'] . '/' . $cover_id . '.' . $extension;
			if (@file_exists($imagename))
			{
				$foundimage = true;
				break;
			}
		}
		return ($foundimage) ? $imagename : '';
	}
	
	public static function distributedPathInfo($song_id)
	{
		$filename = md5($song_id);
		$part1 = substr($filename, 0, 2);
		$part2 = substr($filename, 2, 2);
		$part3 = substr($filename, 4, 2);
		$res = array(
			'filename' => $song_id,
			'path' => array($part1, $part2, $part3),
			'fullpath' => $part1 . '/' . $part2 . '/' . $part3
		);
		return $res;
	}
	
	protected function getCovername($cover_id)
	{
		return ModulePTW::_getCovername($cover_id);
	}
	
	protected function getAdventureForSong($song_id)
	{
		$arrAdventure = $this->Database->prepare("SELECT tl_adventure.id FROM tl_adventure, tl_broadcast, tl_broadcast_song WHERE tl_broadcast_song.pid = tl_broadcast.id AND tl_broadcast.pid = tl_adventure.id AND tl_broadcast_song.id = ?")
			->execute($song_id)
			->fetchAssoc();
		return $arrAdventure['id'];
	}
	
	protected function getPathForSong($song_id)
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
		
		$arrBroadcast = $this->Database->prepare("SELECT tl_broadcast.* FROM tl_broadcast, tl_broadcast_song WHERE tl_broadcast.id = tl_broadcast_song.pid AND tl_broadcast_song.id = ?")
			->execute($song_id)
			->fetchAssoc();
		$arrAdventure = $this->Database->prepare("SELECT tl_adventure.* FROM tl_adventure, tl_broadcast WHERE tl_adventure.id = tl_broadcast.pid AND tl_broadcast.id = ?")
			->execute($arrBroadcast['id'])
			->fetchAssoc();
		return array(
			array('title' => $arrAdventure['numbering'] . '. ' . $arrAdventure['title'], 'url' => $this->generateFrontendUrl($objPageAdventure->row(), '/adventure/' . $arrAdventure['id'])),
			array('title' => $arrBroadcast['chapter'] . '. ' . $arrBroadcast['title'], 'url' => $this->generateFrontendUrl($objPageBroadcast->row(), '/broadcast/' . $arrBroadcast['date']))
		);
	}

	protected function createPaths($path, $array)
	{
		if (@is_dir($path))
		{
			foreach ($array as $subpath)
			{
				if (!@is_dir($path . '/' . $subpath))
				{
					@mkdir($path . '/' . $subpath);
				}
				$path = $path . '/' . $subpath;
			}
		}
	}
	
	protected function checkCoverForSong($song_id)
	{
		$arrSong = $this->Database->prepare("SELECT * FROM tl_broadcast_song WHERE id = ?")
			->execute($song_id)
			->fetchAssoc();
		$objCover = $this->Database->prepare("SELECT * FROM tl_song_cover WHERE song = ?")
			->execute($song_id);
	if (is_array($arrSong) && $objCover->numRows == 0)
		{
			// change identical entries and add the cover too
			if (strlen($arrSong['labelcode']))
			{
				$sameLC = $this->Database->prepare("SELECT id FROM tl_broadcast_song WHERE labelcode = ?")
					->execute($arrSong['labelcode'])
					->fetchEach('id');
			}
			else
			{
				$sameLC = array();
			}
			if (strlen($arrSong['album'].$arrSong['artist']))
			{
				$sameAlbum = $this->Database->prepare("SELECT id FROM tl_broadcast_song WHERE album = ? and artist = ?")
					->execute($arrSong['album'], $arrSong['artist'])
					->fetchEach('id');
			}
			else
			{
				$sameAlbum = array();
			}	
			$samefound = array_unique(array_merge($sameLC, $sameAlbum));
			if (count($samefound))
			{
				$arrCover = $this->Database->prepare("SELECT * FROM tl_song_cover WHERE song IN (" . join($samefound, ',') . ")")
					->execute()
					->fetchAssoc();
				if (is_array($arrCover))
				{
					foreach ($samefound as $foundid)
					{
						$objCover = $this->Database->prepare("SELECT * FROM tl_song_cover WHERE song = ?")
							->execute($foundid);
						if ($objCover->numRows == 0)
						{
							$objResult = $this->Database->prepare("INSERT INTO tl_song_cover (tstamp, song, cover) VALUES (?, ?, ?)")
								->execute(
									time(),
									$foundid,
									$arrCover['cover']
								);
						}
					}
				}
			}
		}
	}
}

