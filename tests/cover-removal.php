<?php
require __DIR__.'/template-output.php';
if (!str_contains((string) getenv('DATABASE_URL'), '/auriga57_test_')) throw new RuntimeException('Isolated database required');
$db = $container->get('database_connection');
class CoverRemovalProbe extends Hschottm\AurigaBundle\ModuleSong {
    public function remove(int $id, bool $allowed): void {
        $this->Template = new Contao\FrontendTemplate('mod_auriga_song');
        $this->removeAlbumCover($id, $allowed, '/song.html');
    }
    public function inherit(int $id): void { $this->checkCoverForSong($id); }
}
$model = new Contao\ModuleModel(); $model->id = 987124;
$view = new CoverRemovalProbe($model);
$request = $container->get('request_stack')->getCurrentRequest();
$request->setSession(new Symfony\Component\HttpFoundation\Session\Session(new Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage()));
$request->getSession()->set('authenticated-test-user', true);
$token = $container->get('contao.csrf.token_manager')->getToken($container->getParameter('contao.csrf_token_name'))->getValue();
$ui = ['song'=>['genres'=>[]], 'currentpath'=>[], 'albumcover'=>'<img src="saved.gif">', 'formaction'=>'/song.html', 'removeCoverFormId'=>'auriga_remove_cover_test', 'metadataLabels'=>['song_cover_remove'=>'Remove album cover', 'song_cover_remove_help'=>'All songs in album']];
foreach ([false,true] as $allowed) {
    $html = renderTemplate('mod_auriga_song', $ui + ['isAdmin'=>$allowed]);
    assertOutput(str_contains($html, 'class="auriga-cover-remove mt-2"') === $allowed, 'Wrong removal form visibility');
    if ($allowed) assertFormTokens($html);
}
$ids = [];
try {
    foreach ([['A','Removal album','Removal label'], ['B','Removal album','Removal label'], ['C','Removal album','Removal label'], ['A','Other album','Removal label'], ['A','Removal album','Other label']] as $i => [$artist,$album,$label]) {
        $db->insert('tl_broadcast_song', ['title'=>'Removal test','artist'=>$artist,'album'=>$album,'labelcode'=>$label]);
        $ids[] = $id = (int) $db->lastInsertId();
        if ($i !== 2) $db->insert('tl_song_cover', ['song'=>$id,'cover'=>987654,'tstamp'=>time()]);
    }
    $post = ['FORM_SUBMIT'=>'auriga_remove_cover_987124_'.$ids[0], 'REQUEST_TOKEN'=>$token];
    $request->request->replace($post); $request->setMethod('GET');
    $view->remove($ids[0],true);
    assertOutput((int)$db->fetchOne('SELECT cover FROM tl_song_cover WHERE song=?',[$ids[0]])===987654,'GET mutated cover');
    $request->setMethod('POST');
    try { $view->remove($ids[0],false); throw new RuntimeException('Unauthorized removal'); }
    catch (Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {}
    $request->request->set('REQUEST_TOKEN','invalid');
    try { $view->remove($ids[0],true); throw new RuntimeException('Invalid token accepted'); }
    catch (Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {}
    assertOutput((int)$db->fetchOne('SELECT cover FROM tl_song_cover WHERE song=?',[$ids[0]])===987654,'Rejected request mutated cover');
    $request->request->replace($post);
    try { $view->remove($ids[0],true); throw new RuntimeException('Missing redirect'); }
    catch (Contao\CoreBundle\Exception\ResponseException $e) { assertOutput($e->getResponse()->getStatusCode()===303,'Wrong redirect'); }
    foreach ($ids as $i=>$id) {
        $view->inherit($id);
        $cover=$db->fetchOne('SELECT cover FROM tl_song_cover WHERE song=?',[$id]);
        assertOutput($cover!==false && (int)$cover===($i<3 ? 0 : 987654),'Wrong album scope or automatic reassignment');
    }
    echo "OK: album cover removal permissions, CSRF, POST, compilation scope and inheritance suppression\n";
} finally {
    foreach ($ids as $id) { $db->delete('tl_song_cover',['song'=>$id]); $db->delete('tl_broadcast_song',['id'=>$id]); }
}
