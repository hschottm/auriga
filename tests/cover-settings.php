<?php
$project = getenv('AURIGA_TEST_PROJECT');
if ($project !== '/tmp/auriga57-test') throw new RuntimeException('Use the disposable test installation');
require $project.'/vendor/autoload.php';
$kernel = Contao\ManagerBundle\HttpKernel\ContaoKernel::fromInput($project, new Symfony\Component\Console\Input\ArrayInput(['--env'=>'prod']));
$kernel->boot();
$kernel->getContainer()->get('contao.framework')->initialize();
Contao\Controller::loadDataContainer('tl_settings');
use Hschottm\AurigaBundle\CoverSearchSettings as Settings;
function ensure($condition) { if (!$condition) throw new RuntimeException('Settings assertion failed'); }
ensure(isset($GLOBALS['TL_DCA']['tl_settings']['fields']['aurigaDiscogsToken']));
ensure(str_contains($GLOBALS['TL_DCA']['tl_settings']['palettes']['default'], 'aurigaCoverProvider'));
$file = $project.'/system/config/localconfig.php';
$original = file_exists($file) ? file_get_contents($file) : null;
try {
    ensure(Settings::saveToken(' test-token ') === '');
    ensure(Contao\Config::get('aurigaDiscogsToken') === 'test-token');
    ensure(Settings::hideToken('test-token') === '');
    Settings::saveToken('');
    ensure(Contao\Config::get('aurigaDiscogsToken') === 'test-token');
    $rejected = false;
    try { Settings::saveToken("invalid\nheader"); } catch (InvalidArgumentException $e) { $rejected = true; }
    ensure($rejected);
    Settings::deleteToken('1');
    ensure(Contao\Config::get('aurigaDiscogsToken') === '');
} finally {
    if ($original !== null) file_put_contents($file, $original); else @unlink($file);
}
echo "OK: real Contao settings DCA, token replacement, blank preservation, validation and deletion.\n";
