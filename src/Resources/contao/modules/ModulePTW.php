<?php

namespace Hschottm\AurigaBundle;

use Contao\PageModel;

/**
 * Class ModulePTW
 *
 * Front end module "PTW". Base class of all ptw related modules
 * @copyright  Helmut Schottmüller 2010
 * @author     Helmut Schottmüller <typolight@aurealis.de>
 * @package    Controller
 */
class ModulePTW extends \Contao\Module
{
    protected function isBackendRequest(): bool
    {
        $container = \Contao\System::getContainer();
        $request = $container->get('request_stack')->getCurrentRequest();
        return $request !== null && $container->get('contao.routing.scope_matcher')->isBackendRequest($request);
    }

    protected function session(): \Symfony\Component\HttpFoundation\Session\SessionInterface
    {
        return \Contao\System::getContainer()->get('request_stack')->getSession();
    }

    protected static function projectDir(): string
    {
        return \Contao\System::getContainer()->getParameter('kernel.project_dir');
    }

    protected static function filesRoot(): string
    {
        return trim((string) \Contao\System::getContainer()->getParameter('contao.upload_path'), '/');
    }

    /** Resolve an Auriga directory relative to Contao's configured file directory. */
    protected static function aurigaFilePath(string $setting): string
    {
        $path = trim((string) $GLOBALS['TL_CONFIG']['auriga'][$setting], '/');
        // Accept old project-relative overrides during upgrades as well.
        foreach (array_unique([self::filesRoot(), 'files']) as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                $path = ltrim(substr($path, strlen($prefix)), '/');
                break;
            }
        }
        return self::filesRoot().($path !== '' ? '/'.$path : '');
    }

    public static function recordingUrls($references): array
    {
        $urls = [];
        foreach (\Contao\StringUtil::deserialize($references, true) as $reference) {
            if (!is_string($reference) || $reference === '') continue;
            if (strlen($reference) === 16 || \Contao\Validator::isUuid($reference)) {
                $file = \Contao\FilesModel::findByUuid($reference);
                if ($file === null) continue;
                $reference = $file->path;
            }
            $prefix = self::aurigaFilePath('fileroot').'/';
            if (str_starts_with($reference, $prefix)) {
                $reference = $GLOBALS['TL_CONFIG']['auriga']['filedownloadroot'].substr($reference, strlen($prefix));
            }
            if (preg_match('~^https?://~i', $reference) || str_starts_with($reference, self::filesRoot().'/')) $urls[] = $reference;
        }
        return array_values(array_unique($urls));
    }

	public static function resolvePage($id)
	{
		global $objPage;

		$id = filter_var($id, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
		return ($id !== false ? PageModel::findById($id) : null) ?? $objPage;
	}

	protected function requireRecordId($value)
	{
		$id = filter_var($value, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
		if ($id === false) throw new \Contao\CoreBundle\Exception\PageNotFoundException('Invalid Auriga record ID');
		return $id;
	}

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
		$uploadTypes = \Contao\StringUtil::trimsplit(',', 'jpg,jpeg,png,gif');
		$foundimage = false;
		$info = ModulePTW::distributedPathInfo($cover_id);
		foreach ($uploadTypes as $extension)
		{
			$imagename = self::aurigaFilePath('coverpath') . '/' . $info['fullpath'] . '/' . $cover_id . '.' . $extension;
			if (is_file(self::projectDir().'/'.$imagename) && is_readable(self::projectDir().'/'.$imagename))
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
		return $arrAdventure['id'] ?? null;
	}
	
	protected function getPathForSong($song_id)
	{
		global $objPage;
		
		// Redirect to jumpTo page
		$objPageAdventure = ModulePTW::resolvePage($this->jumpTo_adventure);
		$objPageBroadcast = ModulePTW::resolvePage($this->jumpTo_broadcast);
		$objPageSong = ModulePTW::resolvePage($this->jumpTo_song);
		
		$arrBroadcast = $this->Database->prepare("SELECT tl_broadcast.* FROM tl_broadcast, tl_broadcast_song WHERE tl_broadcast.id = tl_broadcast_song.pid AND tl_broadcast_song.id = ?")
			->execute($song_id)
			->fetchAssoc();
		if (empty($arrBroadcast['id'])) return array();
		$arrAdventure = $this->Database->prepare("SELECT tl_adventure.* FROM tl_adventure, tl_broadcast WHERE tl_adventure.id = tl_broadcast.pid AND tl_broadcast.id = ?")
			->execute($arrBroadcast['id'])
			->fetchAssoc();
		$path = array();
		if (!empty($arrAdventure['id']))
		{
			$path[] = array('title' => $arrAdventure['numbering'] . '. ' . $arrAdventure['title'], 'url' => $objPageAdventure->getFrontendUrl('/adventure/' . $arrAdventure['id']));
		}
		$path[] = array('title' => $arrBroadcast['chapter'] . '. ' . $arrBroadcast['title'], 'url' => $objPageBroadcast->getFrontendUrl('/broadcast/' . $arrBroadcast['date']));
		return $path;
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
			if (strlen((string) ($arrSong['labelcode'] ?? '')))
			{
				$sameLC = $this->Database->prepare("SELECT id FROM tl_broadcast_song WHERE labelcode = ?")
					->execute($arrSong['labelcode'])
					->fetchEach('id');
			}
			else
			{
				$sameLC = array();
			}
			if (strlen((string) ($arrSong['album'] ?? '').(string) ($arrSong['artist'] ?? '')))
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
				$arrCover = $this->Database->prepare("SELECT * FROM tl_song_cover WHERE song IN (" . join(',', $samefound) . ")")
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

