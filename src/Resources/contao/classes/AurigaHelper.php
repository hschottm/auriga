<?php 

namespace Hschottm\AurigaBundle;

/**
 * @copyright  Helmut Schottmüller 2009
 * @author     Helmut Schottmüller <typolight@aurealis.de>
 * @package    survey_ce
 * @license    LGPL
 * @filesource
 */


/**
 * Class AurigaHelper
 *
 * @copyright  Helmut Schottmüller 2009
 * @author     Helmut Schottmüller <typolight@aurealis.de>
 * @package    Controller
 */
class AurigaHelper extends \Backend
{
    public function __construct()
    {
        parent::__construct();
    }

	private function getProviderId($initials)
	{
		if (!strlen($initials)) return null;
		$objProvider = $this->Database->prepare('SELECT * FROM tl_broadcast_provider WHERE initials = ?')->executeUncached($initials);
		while ($objProvider->next())
		{
			$row = $objProvider->row();
			return $row['id'];
		}
		return null;
	}
	
	private function getBroadcastId($date, $adv, $chap, $hour)
	{
		$objBroadcast = $this->Database->prepare('SELECT * FROM tl_broadcast WHERE `date` = ? AND hour = ? AND pid = ? AND chapter = ?')
			->executeUncached($date, $hour, $adv, $chap);
		while ($objBroadcast->next())
		{
			$row = $objBroadcast->row();
			return $row['id'];
		}
		return null;
	}
	
	private function getBroadcastIdForDate($date)
	{
		$objBroadcast = $this->Database->prepare('SELECT * FROM tl_broadcast WHERE `date` = ? AND story = ?')
			->executeUncached($date, '1');
		while ($objBroadcast->next())
		{
			$row = $objBroadcast->row();
			return $row['id'];
		}
		return null;
	}
	
	/**
	 * Imports the old auriga database into TYPOlight
	 *
	 * @param array
	 */
	public function importOldDatabase(DataContainer $dc)
	{
		$link = mysql_connect("localhost", "hschottm", ".haibin.") or die("Keine Verbindung möglich: " . mysql_error());
		mysql_select_db("Pop") or die("Auswahl der Datenbank fehlgeschlagen");

		$result = mysql_query('SET NAMES utf8') or die("Anfrage fehlgeschlagen: " . mysql_error());

		$query = "SELECT * FROM tkeyabenteuer ORDER BY idAbenteuer";
		$result = mysql_query($query) or die("Anfrage fehlgeschlagen: " . mysql_error());
		while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) 
		{
			$q2 = sprintf("SELECT dtDatum FROM tkeysendung WHERE dtAbenteuer = %s ORDER BY dtDatum",
				$line['idAbenteuer']
			);
			$q2result = mysql_query($q2) or die("Anfrage fehlgeschlagen: " . mysql_error());
			$first = "";
			$last = "";
			while ($l = mysql_fetch_array($q2result, MYSQL_ASSOC)) 
			{
				if (!strlen($first)) $first = $l['dtDatum'];
				$last = $l['dtDatum'];
			}
			$d1 = 0; $d2 = 0;
			preg_match("/(\\d{4})-(\\d{2})-(\\d{2})/", $first, $matches);
			$d1 = mktime(0, 0, 0, $matches[2], $matches[3], $matches[1]);
			preg_match("/(\\d{4})-(\\d{2})-(\\d{2})/", $last, $matches);
			$d2 = mktime(0, 0, 0, $matches[2], $matches[3], $matches[1]);
			$objResult = $this->Database->prepare("INSERT INTO tl_adventure (tstamp, title, description, numbering, alias, firstbroadcast, lastbroadcast) VALUES (?, ?, ?, ?, ?, ?, ?)")
				->executeUncached(
					time(),
					$line['dtTitel'],
					$line['dtBeschreibung'],
					$line['idAbenteuer'],
					'abenteuer'.$line['idAbenteuer'],
					$d1,
					$d2
				);
		}
		mysql_free_result($result);

		$adventures = array();
		$objAdv = $this->Database->prepare('SELECT * FROM tl_adventure')->executeUncached();
		while ($objAdv->next())
		{
			$row = $objAdv->row();
			$adventures[$row['numbering']] = $row['id'];
		}

		$query = "SELECT * FROM tkeyprovider";
		$result = mysql_query($query) or die("Anfrage fehlgeschlagen: " . mysql_error());
		while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) 
		{
			$objResult = $this->Database->prepare("INSERT INTO tl_broadcast_provider (tstamp, initials, name, email, homepage) VALUES (?, ?, ?, ?, ?)")
				->executeUncached(
					time(),
					$line['dtShortcut'],
					$line['dtName'],
					$line['dtMail'],
					($line['dtHome']) ? $line['dtHome'] : ''
				);
		}
		mysql_free_result($result);

		$query = "SELECT * FROM tkeysendung ORDER BY dtAbenteuer, dtKapitel, dtDatum, dtStunde";
		$result = mysql_query($query) or die("Anfrage fehlgeschlagen: " . mysql_error());
		$lastadv = -1;
		while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) 
		{
			if ($lastadv != $line['dtAbenteuer'])
			{
				$sort = 1;
			}
			else
			{
				$sort = $sort+10;
			}
			$lastadv = $line['dtAbenteuer'];
			$providers = array();
			
			$filequery = "SELECT * FROM broadcastptwfiles WHERE fiBroadcast = " . $line['idSendung'];
			$fileresult = mysql_query($filequery) or die("Anfrage fehlgeschlagen: " . mysql_error());
			$filedata = mysql_fetch_array($fileresult, MYSQL_ASSOC);
			$files = null;
			if (is_array($filedata) && strlen($filedata['filename']))
			{
				$filename = 'files/auriga/recordings/ptw/' . sprintf("%03d", $line['dtAbenteuer']) . '/' . $filedata['filename'];
				$files ='a:1:{i:0;s:' . (strlen($filename)) . ':"' . $filename . '";}';
			}

			$proid = $this->getProviderId($line['dtProvider']); if ($proid) array_push($providers, $proid);
			$proid = $this->getProviderId($line['dtProvider2']); if ($proid) array_push($providers, $proid);
			$proid = $this->getProviderId($line['dtProvider3']); if ($proid) array_push($providers, $proid);
			$date = preg_match("/(\\d{4})-(\\d{2})-(\\d{2})/", $line['dtDatum'], $matches);
			$objResult = $this->Database->prepare("INSERT INTO tl_broadcast (tstamp, sorting, title, `date`, hour, pid, chapter, " .
				"story, special, length, complete, providers, files, frequency, samplerate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
				->executeUncached(
						time(),
						$sort,
						($line['dtInhalt']) ? $line['dtInhalt'] : '', 
						mktime(0, 0, 0, $matches[2], $matches[3], $matches[1]), 
						($line['dtStunde']) ? $line['dtStunde'] : '',
						($adventures[$line['dtAbenteuer']] > 0) ? $adventures[$line['dtAbenteuer']] : 0,
						($line['dtKapitel']) ? $line['dtKapitel'] : '',
						($line['dtStory']) ? '1' : '0',
						($line['dtSondersendung']) ? '1' : '0',
						($line['dtDauer']) ? $line['dtDauer'] : '',
						($line['dtComplete']) ? '1' : '0',
						(count($providers)) ? serialize($providers) : null,
						$files,
						($line['dtFrequency']) ? $line['dtFrequency'] : '',
						($line['dtSamplerate']) ? $line['dtSamplerate'] : ''
					);
			$insertid = $objResult->insertId;
			/*
			if ($insertid > 0)
			{
				$queryfiles = sprintf("SELECT * FROM broadcastPTWfiles WHERE fiBroadcast = %s", $line['idSendung']);
				$resultfiles = mysql_query($queryfiles) or die("Anfrage fehlgeschlagen: " . mysql_error());
				while ($linefile = mysql_fetch_array($resultfiles, MYSQL_ASSOC)) 
				{
					if (strlen($linefile['filename']))
					{
						$objResult = $this->Database->prepare("INSERT INTO tl_broadcast_file (pid, tstamp, filename) VALUES (?, ?, ?)")
							->executeUncached(
									$insertid,
									time(),
									$linefile['filename']
								);
					}
				}
			}
			*/
		}

		$query = "SELECT * FROM tkeytitelliste";
		$result = mysql_query($query) or die("Anfrage fehlgeschlagen: " . mysql_error());
		$genres = array();
		while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) 
		{
			$genres[$line['dtMusiktyp1']]++;
			$genres[$line['dtMusiktyp2']]++;
		}
		mysql_free_result($result);

		foreach (array_keys($genres) as $genre)
		{
			if (strlen(trim($genre)))
			{
				$objResult = $this->Database->prepare("INSERT INTO tl_genre (tstamp, name) VALUES (?, ?)")
					->executeUncached(
						time(),
						trim($genre)
					);
			}
		}

		$genres = array();
		$objResult = $this->Database->prepare("SELECT * FROM tl_genre")
			->executeUncached();
		while ($objResult->next())
		{
			$row = $objResult->row();
			$genres[$row['name']] = $row['id'];
		}

		$query = "SELECT * FROM tkeytitelliste";
		$result = mysql_query($query) or die("Anfrage fehlgeschlagen: " . mysql_error());

		while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) 
		{
			preg_match("/(\\d{4})-(\\d{2})-(\\d{2})/", $line['dtDatum'], $matches);
			$date = mktime(0, 0, 0, $matches[2], $matches[3], $matches[1]);
			$bid = $this->getBroadcastId($date, ($adventures[$line['dtAbenteuer']] > 0) ? $adventures[$line['dtAbenteuer']] : 0, ($line['dtKapitel']) ? $line['dtKapitel'] : '', ($line['dtStunde']) ? $line['dtStunde'] : '');
			if ($bid && $line['fiMusiktypPTW'] != 3)
			{
				$foundgenres = array();
				if ($genres[$line['dtMusiktyp1']]) array_push($foundgenres, $genres[$line['dtMusiktyp1']]);
				if ($genres[$line['dtMusiktyp2']]) array_push($foundgenres, $genres[$line['dtMusiktyp2']]);
				$proid = $this->getProviderId($line['dtProvider']); if ($proid) array_push($providers, $proid);
				$proid = $this->getProviderId($line['dtProvider2']); if ($proid) array_push($providers, $proid);
				$proid = $this->getProviderId($line['dtProvider3']); if ($proid) array_push($providers, $proid);
				$objResult = $this->Database->prepare("INSERT INTO tl_broadcast_song (tstamp, pid, sorting, title, artist, album, year, " .
					"labelcode, genres, songtype, length, composer, sequence) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
					->executeUncached(
							time(),
							($bid) ? $bid : 0,
							($line['dtSong']) ? $line['dtSong'] : 0,
							($line['dtSongtitel']) ? $line['dtSongtitel'] : '', 
							($line['dtInterpret']) ? $line['dtInterpret'] : '', 
							($line['dtAlbumtitel']) ? $line['dtAlbumtitel'] : '', 
							($line['dtJahr']) ? $line['dtJahr'] : '', 
							($line['dtLCN']) ? $line['dtLCN'] : '', 
							(count($foundgenres)) ? serialize($foundgenres) : null,
							($line['fiMusiktypPTW']) ? $line['fiMusiktypPTW'] : '',
							($line['dtZeit']) ? $line['dtZeit'] : '',
							($line['dtKomponist']) ? $line['dtKomponist'] : '',
							($line['dtSong']) ? $line['dtSong'] : 0
						);
			} else if ($line['fiSendungTitel'] == 1) {
				$this->log('Import: No broadcast found for ' . print_r($line, true), 'importOldDatabase', 5);
			}

		}
		mysql_free_result($result);

		mysql_close($link);
	}

	/**
	 * Imports the old auriga database into TYPOlight
	 *
	 * @param array
	 */
	public function importBackgroundMusic(DataContainer $dc)
	{
		$link = mysql_connect("localhost", "hschottm", ".haibin.") or die("Keine Verbindung möglich: " . mysql_error());
		mysql_select_db("Pop") or die("Auswahl der Datenbank fehlgeschlagen");

		$result = mysql_query('SET NAMES utf8') or die("Anfrage fehlgeschlagen: " . mysql_error());

		$genres = array();
		$objResult = $this->Database->prepare("SELECT * FROM tl_genre")
			->executeUncached();
		while ($objResult->next())
		{
			$row = $objResult->row();
			$genres[$row['name']] = $row['id'];
		}

		$query = "SELECT * FROM tkeytitelliste WHERE fiMusiktypPTW = 3";
		$result = mysql_query($query) or die("Anfrage fehlgeschlagen: " . mysql_error());

		while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) 
		{
			$providers = array();
			preg_match("/(\\d{4})-(\\d{2})-(\\d{2})/", $line['dtDatum'], $matches);
			$date = mktime(0, 0, 0, $matches[2], $matches[3], $matches[1]);
			$bid = $this->getBroadcastIdForDate($date);
			$foundgenres = array();
			if ($genres[$line['dtMusiktyp1']]) array_push($foundgenres, $genres[$line['dtMusiktyp1']]);
			if ($genres[$line['dtMusiktyp2']]) array_push($foundgenres, $genres[$line['dtMusiktyp2']]);
			$proid = $this->getProviderId($line['dtProvider']); if ($proid) array_push($providers, $proid);
			$proid = $this->getProviderId($line['dtProvider2']); if ($proid) array_push($providers, $proid);
			$proid = $this->getProviderId($line['dtProvider3']); if ($proid) array_push($providers, $proid);
			$objResult = $this->Database->prepare("INSERT INTO tl_broadcast_song (tstamp, pid, sorting, title, artist, album, year, " .
				"labelcode, genres, songtype, length, composer, sequence) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
				->executeUncached(
						time(),
						($bid) ? $bid : 0,
						($line['dtSong']) ? $line['dtSong'] : 0,
						($line['dtSongtitel']) ? $line['dtSongtitel'] : '', 
						($line['dtInterpret']) ? $line['dtInterpret'] : '', 
						($line['dtAlbumtitel']) ? $line['dtAlbumtitel'] : '', 
						($line['dtJahr']) ? $line['dtJahr'] : '', 
						($line['dtLCN']) ? $line['dtLCN'] : '', 
						(count($foundgenres)) ? serialize($foundgenres) : null,
						3,
						($line['dtZeit']) ? $line['dtZeit'] : '',
						($line['dtKomponist']) ? $line['dtKomponist'] : '',
						($line['dtSong']) ? $line['dtSong'] : 0
					);

		}
		mysql_free_result($result);

		mysql_close($link);
	}
	
	protected function getTitleHash($songtitle, $artist, $album, $year, $lc, $type1, $type2)
	{
		$songtitle = preg_replace("/&nbsp;/is", " ", $songtitle);
		$artist = preg_replace("/&nbsp;/is", " ", $artist);
		$album = preg_replace("/&nbsp;/is", " ", $album);
		$lc = preg_replace("/&nbsp;/is", " ", $lc);
		$type1 = preg_replace("/&nbsp;/is", " ", $type1);
		$type2 = preg_replace("/&nbsp;/is", " ", $type2);
		$songtitle = htmlentities(html_entity_decode(trim($songtitle)));
		$artist = htmlentities(html_entity_decode(trim($artist)));
		if (preg_match("/\s*Album:(.*)/is", $album, $matches))
		{
			$album = $matches[1];
		}
		$album = htmlentities(html_entity_decode(trim($album)));
		if (preg_match("/\s*\((.*?)\)/is", $year, $matches))
		{
			$year = $matches[1];
		}
		$lc = htmlentities(html_entity_decode(trim($lc)));
		$type1 = htmlentities(html_entity_decode(trim($type1)));
		$type2 = htmlentities(html_entity_decode(trim($type2)));
		return array(
			"title" => "$songtitle",
			"artist" => "$artist",
			"album" => "$album",
			"year"  => "$year",
			"lc"    => "$lc",
			"type1" => "$type1",
			"type2" => "$type2",
		);
	}
	
	protected function getTitleInfo($tablecell)
	{
		$string = preg_replace("/\\n/is", " ", $tablecell);
		$string = preg_replace("/\\s/is", " ", $string);
		$counter = 1;
		$result = array();
		preg_match_all("/(.*?)SONGTRENNER/is", $string, $matches);
		foreach ($matches[1] as $title)
		{
			if (preg_match("/\s*(.*?)(&nbsp;){3,}(.*?)(&nbsp;){3,}(.*?)(&nbsp;){3,}(.*?)(&nbsp;){3,}(.*?)(&nbsp;){3,}(.*?)(&nbsp;){3,}(.*)/is", $title, $tmatches))
			{
				$result[$counter] = $this->getTitleHash($tmatches[1], $tmatches[3], $tmatches[5], $tmatches[7], $tmatches[9], $tmatches[11], $tmatches[13]);
				$counter++;
			}
		}
		if (preg_match("/.*SONGTRENNER(.*)$/is", $string, $matches))
		{
			$title = $matches[1];
			if (preg_match("/\s*(.*?)(&nbsp;){3,}(.*?)(&nbsp;){3,}(.*?)(&nbsp;){3,}(.*?)(&nbsp;){3,}(.*?)(&nbsp;){3,}(.*?)(&nbsp;){3,}(.*)/is", $title, $tmatches))
			{
				$result[$counter] = $this->getTitleHash($tmatches[1], $tmatches[3], $tmatches[5], $tmatches[7], $tmatches[9], $tmatches[11], $tmatches[13]);
			}
		}
		return $result;
	}
	
	protected function readPopwelt()
	{
		$sendungen = array();

		// create curl resource 
		$ch = curl_init(); 
		curl_setopt($ch, CURLOPT_URL, "http://www.popwelt.de/popliz.htm"); 
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
		$page = curl_exec($ch); 
		curl_close($ch);      

//		$page = file_get_contents('http://www.popwelt.de/popliz.htm');
		preg_match_all("/<table.*?>(.*?)<\/table>/is", $page, $matches);
		foreach ($matches[1] as $tablecontent)
		{
			$row = 1;
			$ausgabe = "";
			$datum = "";
			$sendung = array();
			preg_match_all("/<tr.*?>(.*?)<\/tr>/is", $tablecontent, $tablecontentmatches);
			foreach ($tablecontentmatches[1] as $rowcontent)
			{
				if ($row == 1) 
				{
					if (preg_match("/Ausgabe/", $rowcontent))
					{
						$is_titlelist = 1;
					} else {
						$is_titlelist = 0;
					}
				}
				if ($is_titlelist)
				{
					$col = 1;
					preg_match_all("/<td.*?>(.*?)<\/td>/is", $rowcontent, $rowcontentmatches);
					foreach ($rowcontentmatches[1] as $tablecell)
					{
						$tablecell = preg_replace("/[\n\r]+/", " ", $tablecell);
						$tablecell = preg_replace("/<br>\\s*<br>/", "<br><br>", $tablecell);
						$tablecell = preg_replace("/<br><br>/", "SONGTRENNER", $tablecell);
						$tablecell = preg_replace("/<.*?>/is", "", $tablecell);

						if ($row == 1) 
						{
							if (preg_match("/Ausgabe\s*(\d+)\/(\d+)/is", $tablecell, $tcmatches))
							{
								$sendung['ausgabe'] = sprintf("%04d%02d", $tcmatches[2], $tcmatches[1]);
							}
						}
						if (($row == 2) && ($col == 1)) 
						{
							$tablecell = preg_replace("/&nbsp;/is", " ", $tablecell);
							$tablecell = preg_replace("/\\s/is", " ", $tablecell);
							if (preg_match("/Radio\\s*Bremen\\s*(,|:)\\s*(.*)/is", $tablecell, $tcmatches))
							{
								$sendung['datum'] = $tcmatches[2];
							}
						}
						if ($row == 4) 
						{
							if ($col == 1) 
							{
								$sendung['stunde1'] = $this->getTitleInfo($tablecell);
							} 
							else if ($col == 2) 
							{
								$sendung['stunde2'] = $this->getTitleInfo($tablecell);
								array_push($sendungen, $sendung);
							}
						}

						$col++;
					}
					$row++;
				}
			}
		}
		return $sendungen;
	}
	
	protected function saveBroadcast($broadcast, $year, $month, $day, $chapter, $title1, $title2)
	{
		$genres = array();
		$objResult = $this->Database->prepare("SELECT * FROM tl_genre")
			->executeUncached();
		while ($objResult->next())
		{
			$row = $objResult->row();
			$genres[$row['name']] = $row['id'];
		}
		$sortres = $this->Database->prepare("SELECT MAX(sorting) sortmax FROM tl_broadcast WHERE pid = ?")
			->executeUncached($this->Input->get('id'))
			->fetchAssoc();
		$sort = $sortres['sortmax'] + 10;
		$providers = array();
		$proid = $this->getProviderId('HS'); if ($proid) array_push($providers, $proid);
		$date = preg_match("/(\\d{4})-(\\d{2})-(\\d{2})/", $line['dtDatum'], $matches);
		$objResult = $this->Database->prepare("INSERT INTO tl_broadcast (tstamp, sorting, title, `date`, hour, pid, chapter, " .
			"story, special, length, complete, providers, frequency, samplerate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
			->executeUncached(
					time(),
					$sort,
					$title1, 
					mktime(0, 0, 0, $month, $day, $year), 
					1,
					$this->Input->get('id'),
					$chapter,
					0,
					0,
					'',
					1,
					(count($providers)) ? serialize($providers) : null,
					44100,
					128
				);
		$id_broadcast1 = $objResult->insertId;
		$sequence = 1;
		foreach ($broadcast['stunde1'] as $song)
		{
			$foundgenres = array();
			if ($genres[$song['type1']]) array_push($foundgenres, $genres[$song['type1']]);
			if ($genres[$song['type2']]) array_push($foundgenres, $genres[$song['type2']]);
			$objResult = $this->Database->prepare("INSERT INTO tl_broadcast_song (tstamp, pid, sorting, title, artist, album, year, " .
				"labelcode, genres, songtype, length, composer, sequence) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
				->executeUncached(
						time(),
						$id_broadcast1,
						$sequence,
						html_entity_decode($song['title'], ENT_NOQUOTES, 'UTF-8'), 
						html_entity_decode($song['artist'], ENT_NOQUOTES, 'UTF-8'), 
						html_entity_decode($song['album'], ENT_NOQUOTES, 'UTF-8'), 
						html_entity_decode($song['year'], ENT_NOQUOTES, 'UTF-8'), 
						html_entity_decode($song['lc'], ENT_NOQUOTES, 'UTF-8'), 
						(count($foundgenres)) ? serialize($foundgenres) : null,
						0,
						'',
						'',
						$sequence
					);
			$sequence++;
		}
		$objResult = $this->Database->prepare("INSERT INTO tl_broadcast (tstamp, sorting, title, `date`, hour, pid, chapter, " .
			"story, special, length, complete, providers, frequency, samplerate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
			->executeUncached(
					time(),
					$sort,
					$title2, 
					mktime(0, 0, 0, $month, $day, $year), 
					2,
					$this->Input->get('id'),
					$chapter,
					1,
					0,
					'',
					1,
					(count($providers)) ? serialize($providers) : null,
					44100,
					128
				);
		$id_broadcast2 = $objResult->insertId;
		$sequence = 1;
		foreach ($broadcast['stunde2'] as $song)
		{
			$foundgenres = array();
			if ($genres[$song['type1']]) array_push($foundgenres, $genres[$song['type1']]);
			if ($genres[$song['type2']]) array_push($foundgenres, $genres[$song['type2']]);
			$objResult = $this->Database->prepare("INSERT INTO tl_broadcast_song (tstamp, pid, sorting, title, artist, album, year, " .
				"labelcode, genres, songtype, length, composer, sequence) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
				->executeUncached(
						time(),
						$id_broadcast2,
						$sequence,
						html_entity_decode($song['title'], ENT_NOQUOTES, 'UTF-8'), 
						html_entity_decode($song['artist'], ENT_NOQUOTES, 'UTF-8'), 
						html_entity_decode($song['album'], ENT_NOQUOTES, 'UTF-8'), 
						html_entity_decode($song['year'], ENT_NOQUOTES, 'UTF-8'), 
						html_entity_decode($song['lc'], ENT_NOQUOTES, 'UTF-8'), 
						(count($foundgenres)) ? serialize($foundgenres) : null,
						0,
						'',
						'',
						$sequence
					);
			$sequence++;
		}
	}

	public function importFromPopwelt(DataContainer $dc)
	{
		if ($this->Input->get('key') != 'popwelt')
		{
			return '';
		}

		$this->loadLanguageFile("tl_broadcast");
		$this->Template = new BackendTemplate('be_import_popwelt');

		$this->Template->hrefBack = ampersand(str_replace('&key=popwelt', '', $this->Environment->request));
		$this->Template->goBack = $GLOBALS['TL_LANG']['MSC']['goBack'];
		$this->Template->headline = $GLOBALS['TL_LANG']['MSC']['import_popwelt'][0];
		$this->Template->request = ampersand(\Environment::get('request'));
		$this->Template->submit = specialchars($GLOBALS['TL_LANG']['tl_broadcast']['import'][0]);

//		$broadcasts = $this->Session->get('broadcasts');
		if (!is_array($broadcasts))
		{
			$broadcasts = $this->readPopwelt();
			$this->Session->set('broadcasts', $broadcasts);
		}
		$this->Template->broadcasts = $broadcasts;
		$this->Template->lngDate = specialchars($GLOBALS['TL_LANG']['tl_broadcast']['date'][0]);
		$this->Template->lngChapter = specialchars($GLOBALS['TL_LANG']['tl_broadcast']['chapter'][0]);
		$this->Template->lngTitle1 = specialchars($GLOBALS['TL_LANG']['tl_broadcast']['title'][0]) . " 1";
		$this->Template->lngTitle2 = specialchars($GLOBALS['TL_LANG']['tl_broadcast']['title'][0]) . " 2";

		// Create import form
		if ($this->Input->post('FORM_SUBMIT') == 'tl_import_popwelt')
		{
			$checked = $this->Input->post('cb');
			foreach ($checked as $id)
			{
				$date = $this->Input->post("date_" . $id);
				$chapter = $this->Input->post("chapter_" . $id);
				$title1 = $this->Input->post("title1_" . $id);
				$title2 = $this->Input->post("title2_" . $id);
				if (preg_match("/(\\d{4})-(\\d{2})-(\\d{2})/is", $date, $matches) && strlen($title1) && strlen($title2) && strlen($chapter))
				{
					// save the broadcast
					foreach ($broadcasts as $broadcast)
					{
						if ($broadcast['ausgabe'] == $id)
						{
							$this->saveBroadcast($broadcast, $matches[1], $matches[2], $matches[3], $chapter, $title1, $title2);
						}
					}
				}
			}
			$this->redirect(str_replace('&key=popwelt', '', $this->Environment->request));
		}
		return $this->Template->parse();
	}
}
