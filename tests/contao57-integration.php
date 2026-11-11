<?php
/** Run only against an isolated test database. */
$project = getenv('AURIGA_TEST_PROJECT');
if (!$project || !str_contains((string) getenv('DATABASE_URL'), '/auriga57_test_')) throw new RuntimeException('Set AURIGA_TEST_PROJECT and an isolated auriga57_test_* DATABASE_URL');
require $project.'/vendor/autoload.php';
$kernel = Contao\ManagerBundle\HttpKernel\ContaoKernel::fromInput($project, new Symfony\Component\Console\Input\ArrayInput(['--env'=>'prod']));
$kernel->boot();
$container = $kernel->getContainer();
$request = Symfony\Component\HttpFoundation\Request::create('https://auriga.test/song.html');
$request->attributes->set('_scope', 'frontend');
$request->setSession(new Symfony\Component\HttpFoundation\Session\Session(new Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage()));
$container->get('request_stack')->push($request);
// This CLI integration harness renders templates without HttpKernel::handle().
foreach ($container->get('event_dispatcher')->getListeners('kernel.request') as $listener) {
    if (is_array($listener) && $listener[0] instanceof Contao\CoreBundle\EventListener\CsrfTokenCookieSubscriber) {
        $listener(new Symfony\Component\HttpKernel\Event\RequestEvent($kernel, $request, Symfony\Component\HttpKernel\HttpKernelInterface::MAIN_REQUEST));
    }
}
$container->get('contao.framework')->initialize();
$GLOBALS['TL_LANGUAGE'] = 'de';
Contao\System::loadLanguageFile('default', 'de');
$db = $container->get('database_connection');
// This script owns the explicitly guarded, disposable test database.
foreach (['tl_song_cover','tl_cover','tl_broadcast_song','tl_broadcast_file','tl_broadcast','tl_adventure','tl_page','tl_files'] as $fixtureTable) {
    $db->executeStatement('DELETE FROM '.$fixtureTable);
}
set_error_handler(static function ($severity, $message, $file, $line) {
    if ((error_reporting() & $severity) && !in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) throw new ErrorException($message, 0, $severity, $file, $line);
});
function check($value, $message) { if (!$value) throw new RuntimeException($message); }
foreach (['tl_adventure','tl_broadcast','tl_broadcast_song','tl_genre','tl_cover','tl_song_cover','tl_broadcast_file','tl_broadcast_provider'] as $table) {
    Contao\Controller::loadDataContainer($table);
    check(isset($GLOBALS['TL_DCA'][$table]['fields']['id']['sql']), 'Missing schema '.$table);
}
$root = new Contao\PageModel(); $root->type='root'; $root->title='Auriga Test'; $root->alias='auriga-test'; $root->language='de'; $root->urlSuffix='.html'; $root->useSSL=true; $root->dns='auriga.test'; $root->published=true; $root->fallback=true; $root->save();
$page = new Contao\PageModel(); $page->type='regular'; $page->pid=$root->id; $page->title='Song'; $page->alias='song'; $page->published=true; $page->save();
$GLOBALS['objPage']=$page->loadDetails();
$request->attributes->set('pageModel', $page);
$db->insert('tl_adventure', ['title'=>'Test Adventure','numbering'=>'1','firstbroadcast'=>-1000]); $adventure=(int)$db->lastInsertId();
$db->insert('tl_broadcast', ['pid'=>$adventure,'title'=>'Test Broadcast','date'=>1000,'hour'=>'1','chapter'=>'1','providers'=>serialize([999999])]); $broadcast=(int)$db->lastInsertId();
$db->insert('tl_broadcast_song', ['pid'=>$broadcast,'title'=>'Test Song','artist'=>'Test Artist','album'=>'','songtype'=>1,'genres'=>serialize([999999])]); $song=(int)$db->lastInsertId();
class AdventureView extends Hschottm\AurigaBundle\ModuleAdventureList { public function render() { $this->Template=new Contao\FrontendTemplate($this->strTemplate); $this->compile(); return $this->Template->parse(); } }
class BroadcastListView extends Hschottm\AurigaBundle\ModuleBroadcastList { public function render() { $this->Template=new Contao\FrontendTemplate($this->strTemplate); $this->compile(); return $this->Template->parse(); } }
class BroadcastView extends Hschottm\AurigaBundle\ModuleBroadcast { public function render() { $this->Template=new Contao\FrontendTemplate($this->strTemplate); $this->compile(); return $this->Template->parse(); } }
class SongView extends Hschottm\AurigaBundle\ModuleSong { public function render() { $this->Template=new Contao\FrontendTemplate($this->strTemplate); $this->compile(); return $this->Template->parse(); } }
class SearchView extends Hschottm\AurigaBundle\ModuleDPI { public function render() { $this->Template=new Contao\FrontendTemplate($this->strTemplate); $this->compile(); return $this->Template->parse(); } }
$model=new Contao\ModuleModel(); $model->id=999; $model->name='Auriga Test'; $model->type='song'; $model->jumpTo_song=$page->id; $model->jumpTo_broadcast=$page->id; $model->jumpTo_adventure=$page->id; $model->jumpTo_search=$page->id;
Contao\Input::setGet('adventure', (string)$adventure); Contao\Input::setGet('broadcast','1000'); Contao\Input::setGet('song',(string)$song);
foreach ([AdventureView::class=>'Test Adventure', BroadcastListView::class=>'Test Broadcast', BroadcastView::class=>'Test Song', SongView::class=>'Test Song', SearchView::class=>''] as $class=>$expected) {
    $module=new $class($model); $html=$module->render(); check($expected==='' || str_contains($html,$expected), $class.' failed to render'); echo 'OK: '.$class."\n";
}
Contao\Input::setGet('song','999999');
try { (new SongView($model))->render(); throw new RuntimeException('Expected 404'); } catch (Contao\CoreBundle\Exception\PageNotFoundException $expected) {}
echo "OK: missing record returns 404\n";
class SpecialView extends Hschottm\AurigaBundle\ContentSpecialMusic { public function render() { $this->Template=new Contao\FrontendTemplate($this->strTemplate); $this->compile(); return $this->Template->parse(); } }
$content = new Contao\ContentModel(); $content->type='speciallist'; $content->special_type=1; $content->jumpTo_song=$page->id;
check(str_contains((new SpecialView($content))->render(), 'Test Song'), 'Special music list failed');
foreach (['tl_adventure','tl_broadcast','tl_broadcast_song','tl_broadcast_provider','tl_song_type','tl_broadcast_type'] as $table) {
    Contao\Controller::loadDataContainer($table);
    $callback=Contao\System::importStatic($table);
    if (method_exists($callback, 'addIcon')) check(str_contains($callback->addIcon([], 'Test'), 'Test'), 'Backend callback '.$table);
}
Contao\Controller::loadDataContainer('tl_module');
check(in_array('mod_auriga_song', ($GLOBALS['TL_DCA']['tl_module']['fields']['aurigaTpl']['options_callback'])(), true), 'Twig module template not selectable');
Contao\Controller::loadDataContainer('tl_content');
check(in_array('ce_specialmusic', ($GLOBALS['TL_DCA']['tl_content']['fields']['aurigaTpl']['options_callback'])(), true), 'Twig content template not selectable');
echo "OK: special music element, backend callbacks and template selectors\n";
$uuid = Symfony\Component\Uid\Uuid::v4()->toBinary();
$db->insert('tl_files', ['uuid'=>$uuid,'type'=>'file','path'=>'files/auriga/recordings/test.mp3','name'=>'test.mp3','extension'=>'mp3','hash'=>'']);
check(Hschottm\AurigaBundle\ModulePTW::recordingUrls([$uuid, 'files/auriga/recordings/test.mp3','javascript:alert(1)']) === ['https://mp3.nasbrill-soft.de/test.mp3'], 'UUID/path resolution failed');
echo "OK: UUID and legacy recording paths\n";
class CoverView extends SongView {
    public $client;
    protected function coverSearch(): Hschottm\AurigaBundle\CoverSearch { return new Hschottm\AurigaBundle\CoverSearch($this->client, new Symfony\Component\Cache\Adapter\ArrayAdapter()); }
    protected function canEditCovers(array $groups): bool { return true; }
    public function saveCover($song, $bytes) { return $this->storeCover($song, ['album'=>'','artist'=>'','labelcode'=>''], $bytes, 'gif'); }
}
$GLOBALS['TL_CONFIG']['auriga']['coverpath']='files/auriga-test-covers/';
$gif=base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
$db->insert('tl_cover', ['tstamp'=>time()]); $oldCover=(int)$db->lastInsertId();
$db->insert('tl_song_cover', ['song'=>$song,'cover'=>$oldCover]);
$db->insert('tl_song_cover', ['song'=>999998,'cover'=>$oldCover]);
$view=new CoverView($model);
check($view->saveCover($song,$gif), 'Repair/save failed');
$newCover=(int)$db->fetchOne('SELECT cover FROM tl_song_cover WHERE song=?',[$song]);
check($newCover!==$oldCover && (int)$db->fetchOne('SELECT cover FROM tl_song_cover WHERE song=999998 ORDER BY id DESC LIMIT 1')===$oldCover,'Repair changed other mappings');
check(Hschottm\AurigaBundle\ModulePTW::_getCovername($newCover)!=='','Saved image not found');
check(!$view->saveCover($song,$gif),'Healthy cover overwritten');
$db->insert('tl_broadcast_song', ['pid'=>$broadcast,'title'=>'Cover selection','album'=>'Album','artist'=>'Artist']); $coverSong=(int)$db->lastInsertId();
Contao\Input::setGet('song',(string)$coverSong);
$view->client=new Symfony\Component\HttpClient\MockHttpClient([
 new Symfony\Component\HttpClient\Response\MockResponse('{"releases":[{"id":"12345678-1234-1234-1234-123456789abc"}]}'),
 new Symfony\Component\HttpClient\Response\MockResponse('{"images":[{"front":true,"thumbnails":{"large":"https://example.org/cover.gif"}}]}'),
]);
check(str_contains($view->render(), 'https://example.org/cover.gif'), 'Cover preview missing');
Contao\Input::setPost('usecover','0');
$view->client=new Symfony\Component\HttpClient\MockHttpClient(new Symfony\Component\HttpClient\Response\MockResponse($gif));
try { $view->render(); throw new RuntimeException('Expected redirect after save'); } catch (Contao\CoreBundle\Exception\ResponseException $expected) {}
check((int)$db->fetchOne('SELECT COUNT(*) FROM tl_song_cover WHERE song=?',[$coverSong])===1, 'Cover selection not persisted');
Contao\Input::setPost('usecover',null);
$blocked=$project.'/files/auriga-test-blocked'; file_put_contents($blocked,'not a directory');
$GLOBALS['TL_CONFIG']['auriga']['coverpath']='files/auriga-test-blocked/';
$count=(int)$db->fetchOne('SELECT COUNT(*) FROM tl_cover');
check(!$view->saveCover(999997,$gif),'Expected filesystem failure');
check((int)$db->fetchOne('SELECT COUNT(*) FROM tl_cover')===$count && !(bool)$db->fetchOne('SELECT id FROM tl_song_cover WHERE song=999997'), 'Failed cover left DB records');
unlink($blocked);
echo "OK: real cover preview, download, persistence, shared-cover repair and transaction rollback\n";
foreach ([['search'=>['invalid'], 'page'=>['invalid']], ['search'=>'Test Song', 'field'=>'bad', 'page'=>'2']] as $params) {
    foreach ($params as $key=>$value) Contao\Input::setGet($key,$value);
    check(is_string((new SearchView($model))->render()), 'Invalid search parameters failed');
}
foreach (['song'=>SongView::class,'broadcast'=>BroadcastView::class,'adventure'=>BroadcastListView::class] as $key=>$class) {
    Contao\Input::setGet($key, ['invalid']);
    try { (new $class($model))->render(); throw new RuntimeException('Expected invalid ID 404'); } catch (Contao\CoreBundle\Exception\PageNotFoundException $expected) {}
}
check(Hschottm\AurigaBundle\ModulePTW::resolvePage(999999)===$GLOBALS['objPage'], 'Deleted target fallback failed');
$request->attributes->set('_scope','backend');
foreach ([AdventureView::class, BroadcastListView::class, BroadcastView::class, SongView::class, SearchView::class] as $class) {
    check(str_contains((new $class($model))->generate(), '/contao'), 'Backend wildcard failed');
}
echo "OK: invalid search/record parameters, deleted targets and backend module previews\n";
$theme=new Contao\ThemeModel(); $theme->name='Auriga test'; $theme->author='Test'; $theme->save();
$httpModule=new Contao\ModuleModel(); $httpModule->pid=$theme->id; $httpModule->type='adventurelist'; $httpModule->name='Auriga HTTP'; $httpModule->jumpTo_adventure=$page->id; $httpModule->save();
$layout=new Contao\LayoutModel(); $layout->pid=$theme->id; $layout->name='Auriga test'; $layout->template='fe_page'; $layout->cols='1cl'; $layout->rows='1rw'; $layout->modules=serialize([['mod'=>$httpModule->id,'col'=>'main','enable'=>1]]); $layout->save();
$db->update('tl_page',['includeLayout'=>1,'layout'=>$layout->id],['id'=>$page->id]);
echo "OK: HTTP fixture prepared at https://auriga.test/song.html\n";
$fixtures = [['url'=>'https://auriga.test/song.html','text'=>'Test Adventure']];
foreach (['broadcastlist'=>['adventure/'.$adventure,'Test Broadcast'], 'broadcast'=>['broadcast/1000','Test Song'], 'song'=>['song/'.$song,'Test Song'], 'dpi'=>['search/Test%20Song/field/title','Test Song']] as $type=>[$params,$expected]) {
    $target=new Contao\PageModel(); $target->pid=$root->id; $target->type='regular'; $target->title=$type; $target->alias='auriga-'.$type; $target->published=true; $target->includeLayout=true;
    $module=new Contao\ModuleModel(); $module->pid=$theme->id; $module->type=$type; $module->name=$type; $module->jumpTo_adventure=$page->id; $module->jumpTo_broadcast=$page->id; $module->jumpTo_song=$page->id; $module->jumpTo_search=$page->id; $module->save();
    $targetLayout=new Contao\LayoutModel(); $targetLayout->pid=$theme->id; $targetLayout->name=$type; $targetLayout->template='fe_page'; $targetLayout->cols='1cl'; $targetLayout->rows='1rw'; $targetLayout->modules=serialize([['mod'=>$module->id,'col'=>'main','enable'=>1]]); $targetLayout->save();
    $target->layout=$targetLayout->id; $target->save();
    $fixtures[]=['url'=>'https://auriga.test/auriga-'.$type.'/'.$params.'.html','text'=>$expected];
}
file_put_contents($project.'/var/auriga-http-fixtures.json',json_encode($fixtures, JSON_THROW_ON_ERROR));
