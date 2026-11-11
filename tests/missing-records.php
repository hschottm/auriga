<?php
namespace Contao\CoreBundle\Exception { class PageNotFoundException extends \RuntimeException {} }
namespace Contao { class PageModel { public static function findById($id) { return null; } } }
namespace {
    require __DIR__.'/php-compatibility.php';
    require __DIR__.'/../src/CoverSearch.php';
    interface uploadable {}
    require __DIR__.'/../src/Resources/contao/modules/ModuleSong.php';
    require __DIR__.'/../src/Resources/contao/modules/ModuleBroadcast.php';
    require __DIR__.'/../src/Resources/contao/modules/ModuleBroadcastList.php';
    class SongTest extends \Hschottm\AurigaBundle\ModuleSong {
        public function run() { $this->compile(); }
        public function path() { return $this->getPathForSong(1); }
        public function save($bytes) { return $this->storeCover(1, [], $bytes, 'gif'); }
        protected function createNewCover($songId, $data) { return 123; }
    }
    class BroadcastTest extends \Hschottm\AurigaBundle\ModuleBroadcast { public function run() { $this->compile(); } }
    class AdventureTest extends \Hschottm\AurigaBundle\ModuleBroadcastList { public function run() { $this->compile(); } }
    class RecordDatabase extends Database {
        public $rows = [];
        public $committed = false;
        public $rolledBack = false;
        public function fetchAssoc() { return array_shift($this->rows); }
        public function beginTransaction() {}
        public function commitTransaction() { $this->committed = true; }
        public function rollbackTransaction() { $this->rolledBack = true; }
    }
    foreach (['SongTest'=>'song', 'BroadcastTest'=>'broadcast', 'AdventureTest'=>'adventure'] as $class=>$parameter) {
        foreach (['999', ['invalid'], 'invalid'] as $value) {
            $module = new $class();
            $module->Input = new Inputs();
            $module->Input->get[$parameter] = $value;
            $module->Database = new RecordDatabase();
            $module->Session = new Session();
            $module->Template = new \stdClass();
            try { $module->run(); throw new \RuntimeException('Expected 404'); }
            catch (\Contao\CoreBundle\Exception\PageNotFoundException $expected) {}
        }
    }
    if (\Hschottm\AurigaBundle\ModulePTW::resolvePage(99) !== $GLOBALS['objPage']) throw new \RuntimeException('Deleted target fallback failed');
    $song = new SongTest();
    $song->Database = new RecordDatabase();
    if ($song->path() !== []) throw new \RuntimeException('Missing broadcast breadcrumb');
    $song->Database->rows = [['id'=>1,'chapter'=>1,'title'=>'Broadcast','date'=>123], null];
    if (count($song->path()) !== 1) throw new \RuntimeException('Missing adventure breadcrumb');
    define('TL_ROOT', sys_get_temp_dir().'/auriga-cover-test-'.getmypid());
    mkdir(TL_ROOT);
    $GLOBALS['TL_CONFIG']['auriga']['coverpath'] = 'covers/';
    $bytes = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    if (!$song->save($bytes) || !$song->Database->committed) throw new \RuntimeException('Cover storage failed');
    $info = \Hschottm\AurigaBundle\ModulePTW::distributedPathInfo(123);
    $file = TL_ROOT.'/covers/'.$info['fullpath'].'/123.gif';
    if (file_get_contents($file) !== $bytes) throw new \RuntimeException('Cover bytes differ');
    unlink($file);
    $directory = dirname($file);
    while ($directory !== TL_ROOT) { rmdir($directory); $directory = dirname($directory); }
    file_put_contents(TL_ROOT.'/blocked', 'file instead of directory');
    $GLOBALS['TL_CONFIG']['auriga']['coverpath'] = 'blocked/';
    // Respect @ suppression while retaining E_ALL for actual warnings.
    set_error_handler(static function ($severity, $message, $file, $line) {
        if (error_reporting() & $severity) throw new \ErrorException($message, 0, $severity, $file, $line);
    });
    if ($song->save($bytes) || !$song->Database->rolledBack) throw new \RuntimeException('Expected cover storage rollback');
    unlink(TL_ROOT.'/blocked');
    rmdir(TL_ROOT);
    echo "OK: missing records, deleted targets, incomplete breadcrumbs and cover storage rollback (E_ALL).\n";
}
