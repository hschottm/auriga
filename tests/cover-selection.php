<?php
require getenv('AURIGA_VENDOR_AUTOLOAD') ?: '/var/www/html/vendor/autoload.php';
require __DIR__.'/php-compatibility.php';
require __DIR__.'/../src/CoverSearch.php';
interface uploadable {}
require __DIR__.'/../src/Resources/contao/modules/ModuleSong.php';
use Hschottm\AurigaBundle\CoverSearch;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
function deserialize($value, $force = false) { return is_array($value) ? $value : []; }
class SongDatabase extends Database {
    public $numRows = 1;
    public $id = 42;
    public $songtype = 0;
    public $genres = [];
    public function row() { return ['id'=>42,'album'=>'Album','artist'=>'Artist']; }
    public function fetchAssoc() { return null; }
}
class CoverModuleTest extends Hschottm\AurigaBundle\ModuleSong {
    public $client;
    public $saved;
    public $admin = true;
    public function run() { $this->compile(); }
    protected function coverSearch(): CoverSearch { return new CoverSearch($this->client); }
    protected function checkCoverForSong($id) {}
    protected function getPathForSong($id) { return []; }
    protected function uploadCoverFile($id, $data) {}
    protected function storeCover($id, array $data, string $bytes, string $extension): bool {
        $this->saved = [$id, $bytes, $extension]; return true;
    }
    public function import($class, $key) { $admin = $this->admin; $this->User = new class($admin) {
        private $admin;
        public function __construct($admin) { $this->admin = $admin; }
        public function isMemberOf($id) { return $this->admin; }
    }; }
    public function redirect($url) { throw new RuntimeException('redirect'); }
}
function module() {
    $m = new CoverModuleTest(); $m->Input = new Inputs(); $m->Input->get['song'] = 42;
    $m->Session = new Session(); $m->Template = new stdClass(); $m->Database = new SongDatabase(); $m->admingroups = [1];
    return $m;
}
$release = '{"releases":[{"id":"12345678-1234-1234-1234-123456789abc"}]}';
$art = '{"images":[{"front":true,"thumbnails":{"large":"https://example.org/a.png"}},{"front":true,"thumbnails":{"large":"https://example.org/b.png"}}]}';
$m = module();
$m->client = new MockHttpClient([new MockResponse($release), new MockResponse($art)]);
$m->run();
if (strpos($m->Template->possibleImage, 'a.png') === false || $m->Template->imagePosition !== '1/2' || !$m->Template->urlShowNext) throw new RuntimeException('Missing preview/navigation');
$candidates = $m->Session->get('auriga_cover_candidates_42');
if (count($candidates) !== 2) throw new RuntimeException('Missing session candidates');
$gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
$m->Input->post['usecover'] = '1';
$m->client = new MockHttpClient(new MockResponse($gif));
try { $m->run(); throw new RuntimeException('Expected redirect'); } catch (RuntimeException $e) { if ($e->getMessage() !== 'redirect') throw $e; }
if ($m->saved !== [42, $gif, 'gif'] || $m->Session->get('auriga_cover_candidates_42') !== null) throw new RuntimeException('Selection not saved');
$m = module(); $m->admin = false; $m->Input->post['usecover'] = '0'; $m->Session->set('auriga_cover_candidates_42', $candidates);
$m->client = new MockHttpClient(new MockResponse('{}')); $m->run();
if ($m->saved !== null) throw new RuntimeException('Guest modified cover');
echo "OK: cover preview, navigation, selection persistence and guest protection.\n";
class RepairDatabase extends Database {
    public $insertId = 777;
    public function fetchAssoc() { return ['id'=>1, 'song'=>42, 'cover'=>111]; }
    public function fetchEach($field) { return []; }
}
class RepairModule extends Hschottm\AurigaBundle\ModuleSong {
    public $existing = '';
    public function repair() { return $this->createNewCover(42, ['album'=>'','artist'=>'','labelcode'=>'']); }
    protected function getCovername($id) { return $this->existing; }
}
$m = new RepairModule(); $m->Database = new RepairDatabase();
if ($m->repair() !== 777) throw new RuntimeException('Missing cover not repaired');
if (!in_array('DELETE FROM tl_song_cover WHERE song = ?', $m->Database->queries, true)) throw new RuntimeException('Song mapping not replaced');
foreach ($m->Database->queries as $sql) {
    if (strpos($sql, 'DELETE FROM tl_cover') !== false || strpos($sql, 'WHERE cover =') !== false) throw new RuntimeException('Shared cover deleted');
}
$filename = tempnam(sys_get_temp_dir(), 'auriga-image-'); file_put_contents($filename, $gif);
$m = new RepairModule(); $m->Database = new RepairDatabase(); $m->existing = $filename;
if ($m->repair() !== false || count($m->Database->queries) !== 1) throw new RuntimeException('Healthy cover replaced');
unlink($filename);
echo "OK: missing cover mapping repair and preservation of healthy/shared covers.\n";
class SearchInputs extends Inputs { public function setGet($key, $value) { $this->get[$key] = $value; } }
$m = module(); $m->Input = new SearchInputs(); $m->Input->get = ['song'=>42, 'imgidx'=>1];
$m->Input->post = ['search'=>'1', 'searchtext'=>'Different Album'];
$queries = [];
$m->client = new MockHttpClient(static function ($method, $url) use (&$queries, $release, $art) {
    $queries[] = $url;
    return new MockResponse(strpos($url, 'musicbrainz.org') !== false ? $release : $art);
});
$m->run();
if ($m->Template->searchValue !== 'Different Album' || $m->Template->imagePosition !== '1/2') throw new RuntimeException('Own search did not reset index');
parse_str(parse_url($queries[0], PHP_URL_QUERY), $query);
if ($query['query'] !== 'release:"Different Album"') throw new RuntimeException('Wrong custom album query');
$m->Input->post = []; $m->Input->get['imgidx'] = '1'; $m->run();
if ($m->Template->imagePosition !== '2/2' || strpos($m->Template->possibleImage, 'b.png') === false || $m->Template->searchValue !== 'Different Album') throw new RuntimeException('Custom search not retained while paging');
echo "OK: own album search and pagination retain the query and select the correct image.\n";
