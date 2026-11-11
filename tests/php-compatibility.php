<?php
// Standalone regression test; intentionally uses no application database.
namespace {
    error_reporting(E_ALL);
    set_error_handler(static function ($severity, $message, $file, $line) {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    });
    class Module extends \stdClass {
        public function __get($name) { return null; }
        protected function loadLanguageFile($name) {
            require __DIR__.'/../src/Resources/contao/languages/de/'.$name.'.php';
        }
    }
    class Pagination {
        public function __construct($total, $perPage) {
            if (!is_int($perPage) || $perPage < 1) throw new \RuntimeException('Invalid page size');
        }
        public function generate($separator) { return ''; }
    }
    class Inputs {
        public $get = array();
        public $post = array();
        public function get($key) { return $this->get[$key] ?? null; }
        public function post($key) { return $this->post[$key] ?? null; }
    }
    class Session {
        public $data = array();
        public function get($key) { return $this->data[$key] ?? null; }
        public function set($key, $value) { $this->data[$key] = $value; }
    }
    class Database {
        public $numRows = 0;
        public $queries = array();
        public function prepare($sql) { $this->queries[] = $sql; return $this; }
        public function execute(...$args) { return $this; }
        public function fetchAllAssoc() { return array(); }
        public function next() { return false; }
        public function limit($limit, $offset) {
            if (!is_int($offset) || $offset < 0) throw new \RuntimeException('Invalid offset');
            return $this;
        }
    }
    require __DIR__.'/../src/Resources/contao/modules/ModulePTW.php';
    require __DIR__.'/../src/Resources/contao/modules/ModuleDPI.php';
    class SearchModule extends \Hschottm\AurigaBundle\ModuleDPI {
        public function run() { $this->compile(); }
    }
    $GLOBALS['objPage'] = new class {
        public function getFrontendUrl($suffix) { return '/'.$suffix; }
    };
    $cases = array(
        array(array(), array(), 10, 'title'),
        array(array('search' => 'test', 'page' => 'abc'), array(), 10, 'title'),
        array(array('search' => 'test', 'field' => 'invalid', 'page' => array('2')), array('results' => array('20')), 10, 'title'),
        array(array('search' => 'test', 'page' => '2', 'field' => 'artist'), array('results' => '20'), 20, 'artist'),
        array(array('search' => array('bad'), 'page' => '-1'), array('results' => '0'), 10, 'title'),
    );
    foreach ($cases as $case) {
        $module = new SearchModule();
        $module->Input = new Inputs();
        $module->Input->get = $case[0];
        $module->Input->post = $case[1];
        $module->Session = new Session();
        $module->Database = new Database();
        $module->Template = new \stdClass();
        $module->run();
        if ($module->Template->savedResults !== $case[2] || $module->Template->savedField !== $case[3]) {
            throw new \RuntimeException('Unexpected normalized search parameters');
        }
        if ($module->Template->foundsongs !== array()) throw new \RuntimeException('Expected empty results');
    }
    echo 'OK: '.count($cases)." search regression cases (E_ALL).\n";
}
