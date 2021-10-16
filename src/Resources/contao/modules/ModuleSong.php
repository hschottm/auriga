<?php 

namespace Hschottm\AurigaBundle;

use Symfony\Component\HttpClient\HttpClient;


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
		$uploadTypes = trimsplit(',', $this->extensions);

		// File type is not allowed
		if (!in_array(strtolower($pathinfo['extension']), $uploadTypes))
		{
			$this->addError(sprintf($GLOBALS['TL_LANG']['ERR']['filetype'], $pathinfo['extension']));
			$this->log('File type "'.$pathinfo['extension'].'" is not allowed to be uploaded ('.$file['name'].')', 'FormFileUpload validate()', TL_ERROR);

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

		// Store file in the session and optionally on the server
		if (!$this->hasErrors())
		{
			$_SESSION['FILES']['uploadcover'] = $_FILES['uploadcover'];
			$this->log('File "'.$file['name'].'" uploaded successfully', 'FormFileUpload validate()', TL_FILES);

			$strUploadFolder = $GLOBALS['TL_CONFIG']['auriga']['coverpath'];

			// Store the file if the upload folder exists
			if (strlen($strUploadFolder) && is_dir(TL_ROOT . '/' . $strUploadFolder))
			{
				$coverid = $this->createNewCover($song_id, $data);
				if ($coverid)
				{
					$this->import('Files');
					$info = $this->distributedPathInfo($coverid);
					$this->createPaths($strUploadFolder, $info['path']);
					$this->Files->move_uploaded_file($file['tmp_name'], $strUploadFolder . '/' . $info['fullpath'] . '/' . $coverid . '.' . $pathinfo['extension']);
					$this->Files->chmod($strUploadFolder . '/' . $info['fullpath'] . '/' . $coverid . '.' . $pathinfo['extension'], 0644);

					$_SESSION['FILES']['uploadcover'] = array
					(
						'name' => $file['name'],
						'type' => $file['type'],
						'tmp_name' => TL_ROOT . '/' . $strUploadFolder . '/' . $file['name'],
						'error' => $file['error'],
						'size' => $file['size'],
						'uploaded' => true
					);

					$this->log('File "'.$file['name'].'" has been moved to "'.$strUploadFolder.'"', 'FormFileUpload validate()', TL_FILES);
				}
				else
				{
					$this->addError($GLOBALS['TL_LANG']['ERR']['could_not_create_cover_id']);
				}
			}
		}

		unset($_FILES['uploadcover']);
	}

	protected function createNewCover($song_id, $data)
	{
		$coverid = false;
		$foundCover = $this->Database->prepare("SELECT * FROM tl_song_cover WHERE song = ?")
			->execute($song_id)
			->fetchAssoc();
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
			if (strlen($data['labelcode']))
			{
				$sameLC = $this->Database->prepare("SELECT id FROM tl_broadcast_song WHERE labelcode = ?")
					->execute($data['labelcode'])
					->fetchEach('id');
			}
			else
			{
				$sameLC = array();
			}
			if (strlen($data['album'].$data['artist']))
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
				if ($foundid != $song_id)
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
		$songid = $this->Input->get('song');
		if (!$songid) return;
		$this->checkCoverForSong($songid);
		$thumbs = array();
		$adventure = $this->getAdventureForSong($song_id);
		$objSong = $this->Database->prepare("SELECT * FROM tl_broadcast_song WHERE `id` = ?")
			->execute($songid);
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
			if (strlen($genre))
			{
				$arrGenre = $this->Database->prepare("SELECT * FROM tl_genre WHERE id = ?")
					->execute($genre)
					->fetchAssoc();
				$foundgenres[] = $arrGenre['name'];
			}
		}
		$data['genres'] = $foundgenres;
		$this->uploadCoverFile($objSong->id, $data);

		$foundimages = $this->Session->get('foundimages');
		if (strlen($this->Input->post('usecover')))
		{
			if (is_array($foundimages) && array_key_exists($this->Input->post('usecover'), $foundimages))
			{
				$coverid = $this->createNewCover($objSong->id, $data);

				if ($coverid)
				{
					// download the cover image
					$image = file_get_contents($foundimages[$this->Input->post('usecover')]['artwork']);
					$pathinfo = pathinfo($foundimages[$this->Input->post('usecover')]['artwork']);
					$info = $this->distributedPathInfo($coverid);
					$this->createPaths($GLOBALS['TL_CONFIG']['auriga']['coverpath'], $info['path']);
					$written = file_put_contents($GLOBALS['TL_CONFIG']['auriga']['coverpath'] . '/' . $info['fullpath'] . '/' . $coverid . '.' . $pathinfo['extension'], $image);
					if ($written > 0)
					{
						$foundimages = array();
						$this->Session->set('foundimages', null);
					}
				}
			}
			$this->redirect($this->generateFrontendUrl($objPage->row(), '/song/' . $objSong->id));
		}

		$arrCover = $this->Database->prepare("SELECT cover FROM tl_song_cover WHERE song = ?")
			->execute($objSong->id)
			->fetchAssoc();
		if (!is_array($arrCover))
		{
			$use_existing_cover = false;
			// check for duplicates first
			$arrCover = $this->Database->prepare("SELECT tl_song_cover.* FROM tl_broadcast_song LEFT JOIN tl_song_cover ON tl_broadcast_song.id = tl_song_cover.song WHERE tl_broadcast_song.id <> ? AND labelcode = ?")
				->execute($objSong->id, $objSong->labelcode)
				->fetchAssoc();
			if (is_array($arrCover) && $arrCover['id'] > 0)
			{
				$use_existing_cover = true;
			}
			else
			{
				$arrCover = null;
				$arrCover = $this->Database->prepare("SELECT tl_song_cover.* FROM tl_broadcast_song LEFT JOIN tl_song_cover ON tl_broadcast_song.id = tl_song_cover.song WHERE tl_broadcast_song.id <> ? AND tl_broadcast_song.artist = ? AND tl_broadcast_song.album = ?")
					->execute($objSong->id, $objSong->artist, $objSong->album)
					->fetchAssoc();
				if (is_array($arrCover) && $arrCover['id'] > 0)
				{
					$use_existing_cover = true;
				}
				else
				{
					$arrCover = null;
				}
			}
			if ($use_existing_cover)
			{
				$objDupResult = $this->Database->prepare("INSERT INTO tl_song_cover (tstamp, song, cover) VALUES (?, ?, ?)")
					->execute(
						time(),
						$objSong->id,
						$arrCover['cover']
						);
			}
		}
		$this->import('FrontendUser', 'User');
		$is_admin = false;
		foreach ($admingroups as $group)
		{
			if ($this->User->isMemberOf($group)) $is_admin = true;
		}
		if (is_array($arrCover))
		{
			$imagename = $this->getCovername($arrCover['cover']);
			if (!strlen($imagename))
			{
				$objResult = $this->Database->prepare("DELETE FROM tl_cover WHERE id = ?")
					->execute($arrCover['cover']);
				$objResult = $this->Database->prepare("DELETE FROM tl_song_cover WHERE cover = ?")
					->execute($arrCover['cover']);
				$this->redirect($this->generateFrontendUrl($objPage->row(), '/song/' . $objSong->id));
			}
			else
			{
				$arrImageSize = getimagesize($imagename);

				$size[0] = 300;
				$size[1] = floor(300 * $arrImageSize[1] / $arrImageSize[0]);

				$this->Template->albumcover = '<img src="' . $imagename . '" alt="' . htmlspecialchars($objSong->artist) . ': ' . htmlspecialchars($objSong->album) . '" class="img-fluid albumart" />';
			}
		}
		else
		{
			$client = HttpClient::create([
				'headers' => [
						'User-Agent' => 'Auriga Secret Society Pop Index',
				],
			]);
			$response = $client->request('GET', 'https://musicbrainz.org/ws/2/release?query=' . "release:" . \System::urlEncode($data['album']) . \System::urlEncode(" AND artist") . ":" . \System::urlEncode($data["artist"]));// . "&limit=10");
			$content = $response->getContent();
			$xml = simplexml_load_string($content);
			$json = json_encode($xml);
			$array = json_decode($json,TRUE);

			if ($array['release-list']["@attributes"]['count'] == 0) {
				$album = $data["album"];
				if (strpos($album, "(") > 0) {
					$album = substr($album, 0, strpos($album, "("));
				}
				$response = $client->request('GET', 'https://musicbrainz.org/ws/2/release?query=' . "release:" . \System::urlEncode(trim($album)));// . "&limit=10");
				$content = $response->getContent();
				$xml = simplexml_load_string($content);
				$json = json_encode($xml);
				$array = json_decode($json,TRUE);
			}

			$coverclient = HttpClient::create([
				'headers' => [
						'User-Agent' => 'Auriga Secret Society Pop Index',
				],
			]);
			$mbids = array();
			if ($array['release-list']["@attributes"]['count'] > 1) {
				foreach ($array['release-list']['release'] as $release) {
					$mbid = $release["@attributes"]['id'];
					if (!in_array($mbid, $mbids)) $mbids[] = $mbid;
				}
			} else if ($array['release-list']["@attributes"]['count'] == 1) {
				$mbid = $array['release-list']['release']["@attributes"]['id'];
				if (!in_array($mbid, $mbids)) $mbids[] = $mbid;
			}
			foreach ($mbids as $mbid) {
				$coverresponse = $coverclient->request('GET', 'https://coverartarchive.org/release/' . $mbid);
				if ($coverresponse->getStatusCode() < 400) {
					$covercontent = $coverresponse->getContent();
					$coverjson = json_decode($covercontent, TRUE);
					foreach ($coverjson['images'] as $imagedata) {
						if ($imagedata['front']) {
							if (!in_array($imagedata['thumbnails']['large'], $thumbs))
							{
								$thumbs[] = $imagedata['thumbnails']['large'];
							}
						}
					}
				}
			}

			if ($is_admin)
			{
				$ownsearch = strlen($this->Input->post('search')) & strlen($this->Input->post('searchtext'));
				if ($ownsearch)
				{
					$this->Input->setGet('imgidx', '');
				}
				if ((is_array($foundimages) && $foundimages[0]['song'] != $objSong->id) || $ownsearch)
				{
					$foundimages = array();
				}
				if (!is_array($foundimages)) $foundimages = array();
				if (count($foundimages) == 0)
				{
					//$pas = new AmazonPAS('0KTJZ8FG7FSTFQ2S9EG2', 'g58MVTP8MuXdKmSGEQg1uO4XgyoNDMmJz/C9txB2', '8102-4954-1410');
					//$album = preg_replace("/\\(.*?\\)/", "", $objSong->album);
					//$album = preg_replace("/-\\s*?CD.*/", "", $album);
					//$album = preg_replace("/-\\s*?Single.*/", "", $album);
					//$searchtext = ($ownsearch) ? $this->Input->post('searchtext') : $objSong->artist . " " . $album;
				 	//$items = $pas->item_search($searchtext, array('ResponseGroup' => 'Medium'), 'de');
					//if (count($items->body->Items->Item) == 0 && $ownsearch)
					//{
					//	$items = $pas->item_search($album, array('ResponseGroup' => 'Medium'), 'de');
					//	if (count($items->body->Items->Item) == 0)
					//	{
					//		$items = $pas->item_search($objSong->labelcode, array('ResponseGroup' => 'Medium'), 'de');
					//	}
					//}
					//foreach ($items->body->Items->Item as $item)
					//{
					//	$url = $item->DetailPageURL;
					//	$title = $item->ItemAttributes->Title;
					//	$ItemId = $item->ASIN;
					//	$artist = $pas->util->try_these(array('Artist', 'Creator'), $item->ItemAttributes);
					//	$artwork = $pas->util->try_these(array('LargeImage','MediumImage','SmallImage'), $item, null);
					//	$preview = $artwork;
					//	if ($artwork)
					//	{
					//		$artwork = $artwork->URL;
					//		$foundimages[] = array("song" => $objSong->id, "id" => (string)$ItemId, "title" => (string)$title, "artist" => (string)$artist, "artwork" => (string)$artwork, "preview" => '<img src="' . $preview->URL . '" alt="' . htmlspecialchars($artist) . ': ' . htmlspecialchars($title) . '" title="' . htmlentities($artist) . ': ' . htmlentities($title) . '" />');
					//	}
					//}
					//$this->Session->set('foundimages', $foundimages);
				}
				$possibleIndex = strlen($this->Input->get('imgidx')) ? $this->Input->get('imgidx') : 0;
				if (count($foundimages) > $possibleIndex+1) $this->Template->urlShowNext = $this->generateFrontendUrl($objPage->row(), '/song/' . $objSong->id . '/imgidx/' . ($possibleIndex+1));
				if ($possibleIndex > 0) $this->Template->urlShowPrev = $this->generateFrontendUrl($objPage->row(), '/song/' . $objSong->id . '/imgidx/' . ($possibleIndex-1));
				$this->Template->possibleImage = $foundimages[$possibleIndex]['preview'];
				$this->Template->imagePosition = ($possibleIndex+1) . '/' . count($foundimages);
				$this->Template->formaction = $this->generateFrontendUrl($objPage->row(), '/song/' . $objSong->id);
				$this->Template->usecover = $possibleIndex;
			}
		}
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

