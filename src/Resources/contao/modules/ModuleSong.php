<?php 

namespace Hschottm\AurigaBundle;

use Contao\PageModel;


/**
 * Class ModuleSong
 *
 * Front end module "Song".
 * @copyright  Helmut Schottmüller 2010
 * @author     Helmut Schottmüller <typolight@aurealis.de>
 * @package    Controller
 */
class ModuleSong extends ModulePTW implements \uploadable
{

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'mod_auriga_song';
	protected $maxlength = 5000000;
	protected $extensions = 'gif,jpg,png,jpeg';
	protected $errors = array();


	/**
	 * @return string
	 */
	public function generate()
	{
		if (TL_MODE == 'BE')
		{
			$objTemplate = new \BackendTemplate('be_wildcard');

			$objTemplate->wildcard = '### SONG ###';
			$objTemplate->title = $this->headline;
			$objTemplate->id = $this->id;
			$objTemplate->link = $this->name;
			$objTemplate->href = 'typolight/main.php?do=modules&amp;act=edit&amp;id=' . $this->id;

			return $objTemplate->parse();
		}

		return parent::generate();
	}

	public function addError($errorstring)
	{
		array_push($this->errors, $errorstring);
	}
	
	public function hasErrors()
	{
		return count($this->errors) > 0;
	}
	
	protected function coverSearch(): CoverSearch
	{
		return new CoverSearch();
	}

	protected function uploadCoverFile($song_id, $data)
	{
		// No file specified
		if (!isset($_FILES['uploadcover']) || empty($_FILES['uploadcover']['name']))
		{
			return false;
		}

		$file = $_FILES['uploadcover'];
		$maxlength_kb = $this->getReadableSize($this->maxlength);

		// Romanize the filename
		$file['name'] = utf8_romanize($file['name']);

		// File was not uploaded
		if (!is_uploaded_file($file['tmp_name']))
		{
			if (in_array($file['error'], array(1, 2)))
			{
				$this->addError(sprintf($GLOBALS['TL_LANG']['ERR']['filesize'], $maxlength_kb));
				$this->log('File "'.$file['name'].'" exceeds the maximum file size of '.$maxlength_kb, 'FormFileUpload validate()', TL_ERROR);
			}

			if ($file['error'] == 3)
			{
				$this->addError(sprintf($GLOBALS['TL_LANG']['ERR']['filepartial'], $file['name']));
				$this->log('File "'.$file['name'].'" was only partially uploaded', 'FormFileUpload validate()', TL_ERROR);
			}

			unset($_FILES['uploadcover']);
			return false;
		}

		// File is too big
		if ($this->maxlength > 0 && $file['size'] > $this->maxlength)
		{
			$this->addError(sprintf($GLOBALS['TL_LANG']['ERR']['filesize'], $maxlength_kb));
			$this->log('File "'.$file['name'].'" exceeds the maximum file size of '.$maxlength_kb, 'FormFileUpload validate()', TL_ERROR);

			unset($_FILES['uploadcover']);
			return false;
		}

		$pathinfo = pathinfo($file['name']);
		$pathinfo['extension'] = $pathinfo['extension'] ?? '';
		$uploadTypes = trimsplit(',', $this->extensions);

		// File type is not allowed
		if (!in_array(strtolower($pathinfo['extension']), $uploadTypes))
		{
			$this->addError(sprintf($GLOBALS['TL_LANG']['ERR']['filetype'], $pathinfo['extension']));
			$this->log('File type "'.$pathinfo['extension'].'" is not allowed to be uploaded ('.$file['name'].')', 'FormFileUpload validate()', TL_ERROR);

			unset($_FILES['uploadcover']);
			return false;
		}

		$bytes = @file_get_contents($file['tmp_name']);
		$extension = is_string($bytes) ? CoverSearch::imageExtension($bytes) : null;
		if ($extension === null)
		{
			$this->addError('Die Datei ist kein unterstütztes Coverbild.');
			unset($_FILES['uploadcover']);
			return false;
		}
		if (($arrImageSize = @getimagesize($file['tmp_name'])) != false)
		{
			// Image exceeds maximum image width
			if ($arrImageSize[0] > $GLOBALS['TL_CONFIG']['imageWidth'])
			{
				$this->addError(sprintf($GLOBALS['TL_LANG']['ERR']['filewidth'], $file['name'], $GLOBALS['TL_CONFIG']['imageWidth']));
				$this->log('File "'.$file['name'].'" exceeds the maximum image width of '.$GLOBALS['TL_CONFIG']['imageWidth'].' pixels', 'FormFileUpload validate()', TL_ERROR);

				unset($_FILES['uploadcover']);
				return false;
			}

			// Image exceeds maximum image height
			if ($arrImageSize[1] > $GLOBALS['TL_CONFIG']['imageHeight'])
			{
				$this->addError(sprintf($GLOBALS['TL_LANG']['ERR']['fileheight'], $file['name'], $GLOBALS['TL_CONFIG']['imageHeight']));
				$this->log('File "'.$file['name'].'" exceeds the maximum image height of '.$GLOBALS['TL_CONFIG']['imageHeight'].' pixels', 'FormFileUpload validate()', TL_ERROR);

				unset($_FILES['uploadcover']);
				return false;
			}
		}

		if (!$this->hasErrors() && !$this->storeCover($song_id, $data, $bytes, $extension))
		{
			$this->addError('Das Cover konnte nicht gespeichert werden.');
		}

		unset($_FILES['uploadcover']);
	}

	/** Roll back new mappings if the image cannot be persisted. */
	protected function storeCover($songId, array $data, string $bytes, string $extension): bool
	{
		if (strlen($bytes) > $this->maxlength || CoverSearch::imageExtension($bytes) !== $extension) return false;
		$path = null;
		$temp = null;
		$created = false;
		$this->Database->beginTransaction();
		try
		{
			$coverId = $this->createNewCover($songId, $data);
			if (!$coverId) throw new \RuntimeException('Cover mapping already exists');
			$info = $this->distributedPathInfo($coverId);
			$directory = TL_ROOT . '/' . trim($GLOBALS['TL_CONFIG']['auriga']['coverpath'], '/') . '/' . $info['fullpath'];
			if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) throw new \RuntimeException('Cannot create cover directory');
			$path = $directory . '/' . $coverId . '.' . $extension;
			if (file_exists($path)) throw new \RuntimeException('Cover file already exists');
			$temp = @tempnam($directory, '.cover-');
			if ($temp === false || @file_put_contents($temp, $bytes) !== strlen($bytes) || !@chmod($temp, 0644) || !@rename($temp, $path)) throw new \RuntimeException('Cannot store cover');
			$created = true;
			$this->Database->commitTransaction();
			return true;
		}
		catch (\Exception $exception)
		{
			$this->Database->rollbackTransaction();
			if ($temp && is_file($temp)) @unlink($temp);
			if ($created && $path && is_file($path)) @unlink($path);
			return false;
		}
	}

	protected function createNewCover($song_id, $data)
	{
		$coverid = false;
		$foundCover = $this->Database->prepare("SELECT * FROM tl_song_cover WHERE song = ?")
			->execute($song_id)
			->fetchAssoc();
		if (is_array($foundCover))
		{
			$existing = !empty($foundCover['cover']) ? $this->getCovername($foundCover['cover']) : '';
			if ($existing !== '' && @getimagesize($existing) !== false) return false;
			$this->Database->prepare("DELETE FROM tl_song_cover WHERE song = ?")->execute($song_id);
			$foundCover = null;
		}
		if (!is_array($foundCover))
		{
			$objResult = $this->Database->prepare("INSERT INTO tl_cover (tstamp) VALUES (?)")
				->execute(
					time()
					);
			$coverid = $objResult->insertId;
			$objResult = $this->Database->prepare("INSERT INTO tl_song_cover (tstamp, song, cover) VALUES (?, ?, ?)")
				->execute(
					time(),
					$song_id,
					$coverid
					);
			// change identical entries and add the cover too
			if (strlen((string) ($data['labelcode'] ?? '')))
			{
				$sameLC = $this->Database->prepare("SELECT id FROM tl_broadcast_song WHERE labelcode = ?")
					->execute($data['labelcode'])
					->fetchEach('id');
			}
			else
			{
				$sameLC = array();
			}
			if (strlen((string) ($data['album'] ?? '').(string) ($data['artist'] ?? '')))
			{
				$sameAlbum = $this->Database->prepare("SELECT id FROM tl_broadcast_song WHERE album = ? and artist = ?")
					->execute($data['album'], $data['artist'])
					->fetchEach('id');
			}
			else
			{
				$sameAlbum = array();
			}
			$samefound = array_unique(array_merge($sameLC, $sameAlbum));
			foreach ($samefound as $foundid)
			{
				if ($foundid != $song_id && !$this->Database->prepare("SELECT id FROM tl_song_cover WHERE song = ?")->execute($foundid)->numRows)
				{
					$objResult = $this->Database->prepare("INSERT INTO tl_song_cover (tstamp, song, cover) VALUES (?, ?, ?)")
						->execute(
							time(),
							$foundid,
							$coverid
							);
				}
			}
		}
		return $coverid;
	}

	/**
	 * Generate module
	 */
	protected function compile()
	{
		global $objPage;

		//require_once(TL_ROOT . '/plugins/cloudfusion/cloudfusion.class.php');
		//require_once(TL_ROOT . '/plugins/cloudfusion/pas.class.php');
		//require_once(TL_ROOT . '/plugins/cloudfusion/_utilities.class.php');
		//require_once(TL_ROOT . '/plugins/cloudfusion/lib/requestcore/requestcore.class.php');
		foreach (array('albumcover', 'possibleImage', 'urlShowNext', 'urlShowPrev', 'imagePosition', 'searchValue') as $field)
		{
			$this->Template->$field = '';
		}
		$songid = $this->Input->get('song');
		if ($songid === null || $songid === '') return;
		$songid = $this->requireRecordId($songid);
		$objPageSong = ModulePTW::resolvePage($this->jumpTo_song);
		$thumbs = array();
		$objSong = $this->Database->prepare("SELECT * FROM tl_broadcast_song WHERE `id` = ?")
			->execute($songid);
		if (!$objSong->numRows) throw new \Contao\CoreBundle\Exception\PageNotFoundException('Auriga song not found');
		$this->checkCoverForSong($songid);
		$this->loadLanguageFile('tl_broadcast_song');
		$this->loadLanguageFile('tl_module');
		$data = $objSong->row();
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
		$data['special'] = $special;
		$foundgenres = array();
		$admingroups = deserialize($this->admingroups, true);
		$song_genres = deserialize($objSong->genres, true);
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
		$data['genres'] = $foundgenres;
		$this->import('FrontendUser', 'User');
		$is_admin = false;
		foreach ($admingroups as $group)
		{
			if ($this->User->isMemberOf($group)) $is_admin = true;
		}
		if ($is_admin) $this->uploadCoverFile($objSong->id, $data);

		$foundimages = $this->Session->get('auriga_cover_candidates_' . $objSong->id);
		$usecover = filter_var($this->Input->post('usecover'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
		if ($is_admin && $usecover !== false && is_array($foundimages) && isset($foundimages[$usecover]))
		{
			$selected = $foundimages[$usecover];
			$image = null;
			if (is_array($selected) && ($selected['song'] ?? null) == $objSong->id && is_string($selected['artwork'] ?? null))
			{
				$image = $this->coverSearch()->download($selected['artwork'], $this->maxlength);
			}
			if ($image !== null && $this->storeCover($objSong->id, $data, $image['bytes'], $image['extension']))
			{
				$this->Session->set('auriga_cover_candidates_' . $objSong->id, null);
				$this->redirect($objPageSong->getFrontendUrl('/song/' . $objSong->id));
			}
			$this->addError('Das Cover konnte nicht geladen oder gespeichert werden.');
		}

		$arrCover = $this->Database->prepare("SELECT cover FROM tl_song_cover WHERE song = ?")
			->execute($objSong->id)
			->fetchAssoc();
		$imagename = !empty($arrCover['cover']) ? $this->getCovername($arrCover['cover']) : '';
		if ($imagename !== '' && @getimagesize($imagename) !== false)
		{
			$this->Template->albumcover = '<img src="' . htmlspecialchars($imagename, ENT_QUOTES) . '" alt="' . htmlspecialchars((string) $objSong->artist, ENT_QUOTES) . ': ' . htmlspecialchars((string) $objSong->album, ENT_QUOTES) . '" class="img-fluid albumart" />';
		}
		else
		{
			$ownsearch = $is_admin && $this->Input->post('search') && is_string($this->Input->post('searchtext')) && trim($this->Input->post('searchtext')) !== '';
			$sessionKey = 'auriga_cover_search_' . $objSong->id;
			$searchAlbum = $ownsearch ? trim($this->Input->post('searchtext')) : $this->Session->get($sessionKey);
			if (!is_string($searchAlbum) || $searchAlbum === '') $searchAlbum = (string) ($data['album'] ?? '');
			if ($ownsearch) $this->Session->set($sessionKey, $searchAlbum);
			$this->Template->searchValue = $searchAlbum;
			$thumbs = $this->coverSearch()->search($searchAlbum, $searchAlbum === (string) ($data['album'] ?? '') ? (string) ($data['artist'] ?? '') : '');
			if ($is_admin)
			{
				if ($ownsearch) $this->Input->setGet('imgidx', '');
				$foundimages = array();
				foreach ($thumbs as $url)
				{
					$foundimages[] = array('song' => $objSong->id, 'artwork' => $url,
						'preview' => '<img src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="Covervorschlag" class="img-fluid albumart" />');
				}
				$this->Session->set('auriga_cover_candidates_' . $objSong->id, $foundimages);

				$possibleIndex = filter_var($this->Input->get('imgidx'), FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)));
				if ($possibleIndex === false || !isset($foundimages[$possibleIndex])) $possibleIndex = 0;
				if (count($foundimages) > $possibleIndex+1) $this->Template->urlShowNext = $objPageSong->getFrontendUrl('/song/' . $objSong->id . '/imgidx/' . ($possibleIndex+1));
				if ($possibleIndex > 0) $this->Template->urlShowPrev = $objPageSong->getFrontendUrl('/song/' . $objSong->id . '/imgidx/' . ($possibleIndex-1));
				$this->Template->possibleImage = $foundimages[$possibleIndex]['preview'] ?? '';
				$this->Template->imagePosition = (count($foundimages) ? $possibleIndex+1 : 0) . '/' . count($foundimages);
				$this->Template->formaction = $objPageSong->getFrontendUrl('/song/' . $objSong->id);
				$this->Template->usecover = $possibleIndex;
			}
		}
		$this->Template->coverErrors = $this->errors;
		$this->Template->song = $data;
		$this->Template->coverSearch = $thumbs;
		$this->Template->coverSearchCount = count($thumbs);
		$this->Template->isAdmin = $is_admin;
		$this->Template->lngTitle = $GLOBALS['TL_LANG']['tl_module']['song_title'];
		$this->Template->lngCorrectCover = $GLOBALS['TL_LANG']['tl_module']['qst_correct_cover'];
		$this->Template->lngShowNextCover = $GLOBALS['TL_LANG']['tl_module']['show_next_cover'];
		$this->Template->lngShowPrevCover = $GLOBALS['TL_LANG']['tl_module']['show_prev_cover'];
		$this->Template->lngYes = $GLOBALS['TL_LANG']['tl_module']['yes'];
		$this->Template->lngSearch = $GLOBALS['TL_LANG']['tl_module']['search'];
		$this->Template->lngSearchOwn = $GLOBALS['TL_LANG']['tl_module']['search_own'];
		$this->Template->lngCorrectCover = $GLOBALS['TL_LANG']['tl_module']['qst_correct_cover'];
		$this->Template->lngArtist = $GLOBALS['TL_LANG']['tl_module']['song_artist'];
		$this->Template->lngAlbum = $GLOBALS['TL_LANG']['tl_module']['song_album'];
		$this->Template->lngYear = $GLOBALS['TL_LANG']['tl_module']['song_year'];
		$this->Template->lngLabelcode = $GLOBALS['TL_LANG']['tl_module']['song_labelcode'];
		$this->Template->lngSongtype = $GLOBALS['TL_LANG']['tl_module']['song_songtype'];
		$this->Template->lngLength = $GLOBALS['TL_LANG']['tl_module']['song_length'];
		$this->Template->lngComposer = $GLOBALS['TL_LANG']['tl_module']['song_composer'];
		$this->Template->lngCover = $GLOBALS['TL_LANG']['tl_module']['song_cover'];
		$this->Template->lngGenres = $GLOBALS['TL_LANG']['tl_module']['song_genres'];
		$this->Template->lngUpload = $GLOBALS['TL_LANG']['tl_module']['upload'];
		$this->Template->lngUploadCover = $GLOBALS['TL_LANG']['tl_module']['upload_cover'];
		$this->Template->currentpath = $this->getPathForSong($objSong->id);
	}
}

