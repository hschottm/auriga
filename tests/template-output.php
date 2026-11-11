<?php
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) throw new ErrorException($message, 0, $severity, $file, $line);
});
define('REQUEST_TOKEN', 'test-token');
class TemplateView extends stdClass {
    public function __get($key) { return null; }
    public function render($name) {
        ob_start();
        include __DIR__.'/../src/Resources/contao/templates/modules/'.$name.'.html5';
        return ob_get_clean();
    }
}
$malicious = '\"><script>alert(1)</script>';
$t = new TemplateView();
$t->song = ['album'=>$malicious,'artist'=>$malicious,'title'=>$malicious,'year'=>null,'labelcode'=>null,'length'=>null,'composer'=>null,'special'=>'','genres'=>[$malicious]];
$t->currentpath = [['url'=>'/test?q="bad"','title'=>$malicious]];
$t->coverErrors = [];
$html = $t->render('mod_auriga_song');
if (strpos($html, '<script>') !== false || strpos($html, '&lt;script&gt;') === false || strpos($html, 'q=&quot;bad&quot;') === false) throw new RuntimeException('Song output not escaped');
$t = new TemplateView(); $t->savedSearch = $malicious; $t->foundsongs = []; $t->options = []; $t->results = [];
$html = $t->render('mod_auriga_dpi');
if (strpos($html, '<script>') !== false || strpos($html, '&lt;script&gt;') === false) throw new RuntimeException('Search attribute not escaped');
echo "OK: template text/attribute escaping and nullable song fields (E_ALL).\n";
