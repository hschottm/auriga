<?php
require __DIR__.'/contao57-integration.php';
$request->attributes->set('_scope', 'frontend');
$model->adventure_source = $adventure;
$folder = $project.'/files/auriga-archive-test';
mkdir($folder.'/ptw/001',0755,true);
file_put_contents($folder.'/ptw/001/ptw_4902_2.mp3','audio');
$uuid = Symfony\Component\Uid\Uuid::v4()->toBinary();
$db->insert('tl_files',['uuid'=>$uuid,'type'=>'folder','path'=>'files/auriga-archive-test','name'=>'auriga-archive-test','hash'=>'']);
$model->aurigaArchiveRoot = $uuid;
$model->aurigaArchiveGroups = serialize([123]);
$db->update('tl_broadcast',['files'=>serialize(['files/auriga/recordings/ptw/001/ptw_4902_2.mp3','files/old/missing.mp3'])],['id'=>$broadcast]);
try {
    $html = (new BroadcastListView($model))->render();
    check(!str_contains($html, '<audio') && !str_contains($html, 'song.gif') && !str_contains($html, '<th>Abspielen</th>'), 'Guest player column visible');
    $key = 'auriga_archive_'.$model->id;
    $request->query->set($key,$broadcast.':0');
    try { (new BroadcastListView($model))->render(); throw new RuntimeException('Guest file request accepted'); }
    catch (Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {}
    $request->query->remove($key);
    $member = (new ReflectionClass(Contao\FrontendUser::class))->newInstanceWithoutConstructor();
    $member->groups = [123];
    $token = new Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($member,'contao_frontend',['ROLE_MEMBER']);
    $storage = $container->get('security.token_storage');
    $previous = $storage->getToken();
    $request->getSession()->start();
    $request->getSession()->set('_security_contao_frontend',serialize($token));
    $storage->setToken($token);
    $html = (new BroadcastListView($model))->render();
    check(substr_count($html,'<audio') === 1 && str_contains($html,'<th>Abspielen</th>'), 'Expected one existing recording player');
    check(!str_contains($html,'files/auriga') && !str_contains($html,'mp3.nasbrill'), 'Raw archive URL leaked');
    $request->query->set($key,$broadcast.':0');
    try { (new BroadcastListView($model))->render(); throw new RuntimeException('Expected audio response'); }
    catch (Contao\CoreBundle\Exception\ResponseException $e) {
        check($e->getResponse()->headers->get('Content-Type') === 'audio/mpeg','Audio type');
        $request->headers->set('Range','bytes=0-2');
        $e->getResponse()->prepare($request);
        check($e->getResponse()->getStatusCode() === 206,'Range request');
    }
    $model->aurigaArchiveGroups = serialize([456]);
    try { (new BroadcastListView($model))->render(); throw new RuntimeException('Wrong group accepted'); }
    catch (Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {}
    $model->aurigaArchiveGroups = serialize([123]);
    $request->query->set($key,'999999:0');
    try { (new BroadcastListView($model))->render(); throw new RuntimeException('Unknown recording accepted'); }
    catch (Contao\CoreBundle\Exception\PageNotFoundException $e) {}
    echo "OK: archive path and filename, existing files only, conditional column, actual member groups, protected playback and byte ranges\n";
} finally {
    $request->query->remove($key);
    $request->getSession()->remove('_security_contao_frontend');
    if (isset($storage)) $storage->setToken($previous);
    $db->executeStatement('DELETE FROM tl_files WHERE uuid=?',[$uuid]);
    unlink($folder.'/ptw/001/ptw_4902_2.mp3'); rmdir($folder.'/ptw/001'); rmdir($folder.'/ptw'); rmdir($folder);
}
