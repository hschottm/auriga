<?php
require __DIR__.'/template-output.php';
use Hschottm\AurigaBundle\ContentMediafileBrowser;
Contao\Controller::loadDataContainer('tl_content');
assertOutput(isset($GLOBALS['TL_DCA']['tl_content']['fields']['aurigaBrowserRoot']['sql']), 'Missing browser schema');
assertOutput(isset($GLOBALS['TL_CTE']['files']['mediafile_browser']), 'Missing browser registration');
$folder = $project.'/files/auriga-browser-test';
mkdir($folder.'/sub', 0755, true);
file_put_contents($folder.'/example.mp3','test download');
$uuid = Symfony\Component\Uid\Uuid::v4()->toBinary();
$db = $container->get('database_connection');
$db->insert('tl_files', ['uuid'=>$uuid,'type'=>'folder','path'=>'files/auriga-browser-test','name'=>'auriga-browser-test','hash'=>'']);
$model = new Contao\ContentModel();
$model->id = 987654; $model->type = 'mediafile_browser'; $model->aurigaBrowserRoot = $uuid;
$model->aurigaBrowserExtensions = 'mp3';
$model->headline = serialize(['value'=>'Archive','unit'=>'h2']);
$GLOBALS['objPage'] = new Contao\PageModel();
try {
    $html = (new ContentMediafileBrowser($model))->generate();
    assertOutput(str_contains($html,'example.mp3') && str_contains($html, 'sub') && str_contains($html, '13'), 'Listing metadata missing');
    file_put_contents($folder.'/example.mp3', 'TAG'.str_pad('<Titel & mehr>', 30, "\0").str_repeat("\0", 95));
    $html = (new ContentMediafileBrowser($model))->generate();
    assertOutput(str_contains($html, '&lt;Titel &amp; mehr&gt;') && !str_contains($html, '<Titel & mehr>'), 'ID3 title must be displayed and escaped');
    file_put_contents($folder.'/example.mp3', 'test download');
    $request->query->set('auriga_browser_987654', 'sub');
    $html = (new ContentMediafileBrowser($model))->generate();
    assertOutput(str_contains($html, 'Parent directory') || str_contains($html, 'Übergeordnetes Verzeichnis'), 'Missing parent navigation');
    $request->query->set('auriga_browser_987654', 'example.mp3');
    $request->query->set('auriga_browser_987654_download', '1');
    $denied = false;
    try { (new ContentMediafileBrowser($model))->generate(); }
    catch (Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) { $denied = true; }
    assertOutput($denied, 'Guest private download must be denied even on unprotected element');
    class AuthorizedBrowser extends ContentMediafileBrowser { protected function canAccessFile(string $path): bool { return true; } }
    $downloaded = false;
    try { (new AuthorizedBrowser($model))->generate(); }
    catch (Contao\CoreBundle\Exception\ResponseException $e) {
        $response = $e->getResponse();
        assertOutput($response instanceof Symfony\Component\HttpFoundation\BinaryFileResponse, 'Expected streamed file');
        assertOutput(str_contains($response->headers->get('Content-Disposition'), 'attachment'), 'Download disposition missing');
        assertOutput(str_contains($response->headers->get('Cache-Control'), 'no-store'), 'Download cached');
        $downloaded = true;
    }
    assertOutput($downloaded, 'Download not handled');
    $request->query->set('auriga_browser_987654_download', 'play');
    try { (new AuthorizedBrowser($model))->generate(); throw new RuntimeException('Playback missing'); }
    catch (Contao\CoreBundle\Exception\ResponseException $e) {
        $response = $e->getResponse();
        assertOutput($response->headers->get('Content-Type') === 'audio/mpeg', 'Wrong audio type');
        $request->headers->set('Range', 'bytes=0-3');
        $response->prepare($request);
        assertOutput($response->getStatusCode() === 206, 'Audio seeking must support byte ranges');
        $request->headers->remove('Range');
    }
    $request->query->remove('auriga_browser_987654_download');
    $request->query->set('auriga_browser_987654', '');
    $html = (new AuthorizedBrowser($model))->generate();
    assertOutput(str_contains($html, '<audio controls preload="none"') && str_contains($html, 'play'), 'Audio player missing');
    $request->query->set('auriga_browser_987654', 'example.mp3');
    $request->query->set('auriga_browser_987654_download', '1');
    $model->aurigaBrowserExtensions = 'flac';
    try { (new AuthorizedBrowser($model))->generate(); throw new RuntimeException('Extension filter bypassed'); }
    catch (Contao\CoreBundle\Exception\PageNotFoundException $e) {}
    class AccessProbe extends ContentMediafileBrowser { public function allowed(string $path): bool { return $this->canAccessFile($path); } }
    $member = (new ReflectionClass(Contao\FrontendUser::class))->newInstanceWithoutConstructor();
    $member->groups = [123];
    $token = new Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($member, 'contao_frontend', ['ROLE_MEMBER']);
    $storage = $container->get('security.token_storage');
    $previousToken = $storage->getToken();
    $session = new Symfony\Component\HttpFoundation\Session\Session(new Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage());
    $request->setSession($session);
    $session->start();
    $session->set('_security_contao_frontend', serialize($token));
    $storage->setToken($token);
    $model->aurigaBrowserGroups = serialize([123]);
    assertOutput((new AccessProbe($model))->allowed('files/auriga-browser-test/example.mp3'), 'Authorized group rejected');
    $model->aurigaBrowserGroups = serialize([456]);
    assertOutput(!(new AccessProbe($model))->allowed('files/auriga-browser-test/example.mp3'), 'Wrong member group accepted');
    $model->aurigaBrowserGroups = null;
    assertOutput(!(new AccessProbe($model))->allowed('files/auriga-browser-test/example.mp3'), 'Empty groups accepted');
    $session->remove('_security_contao_frontend');
    $storage->setToken($previousToken);
    $publicFolder = Contao\FilesModel::findByUuid($uuid);
    $publicFolder->public = true;
    assertOutput((new AccessProbe($model))->allowed('files/auriga-browser-test/sub/track.mp3'), 'Public ancestor not inherited');
    $publicFolder->public = false;
    $model->protected = true; $model->groups = serialize([999999]);
    assertOutput(!Contao\Controller::isVisibleElement($model), 'Guest must not see protected browser');
    assertOutput(Contao\Controller::getContentElement($model) === '', 'Guest download request must not execute protected browser');
    echo "OK: Contao registration, Twig rendering, directory navigation, private file response and guest visibility\n";
} finally {
    $db->executeStatement('DELETE FROM tl_files WHERE uuid=?', [$uuid]);
    unlink($folder.'/example.mp3'); rmdir($folder.'/sub'); rmdir($folder);
}
