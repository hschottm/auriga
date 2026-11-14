<?php
require __DIR__.'/template-output.php';
if (!str_contains((string) getenv('DATABASE_URL'), '/auriga57_test_')) throw new RuntimeException('Isolated database required');
$db = $container->get('database_connection');
Contao\System::loadLanguageFile('tl_module', 'de');
class MetadataProbe extends Hschottm\AurigaBundle\ModuleSong {
    public function edit(int $id, bool $allowed, array $data): string {
        $this->Template = new Contao\FrontendTemplate('mod_auriga_song');
        $this->editSongMetadata($id, $allowed, '/song.html', $data);
        $this->Template->song = $data + ['genres'=>[]];
        $this->Template->isAdmin = $allowed;
        $this->Template->metadataLabels = $GLOBALS['TL_LANG']['tl_module'];
        return $this->Template->parse();
    }
}
$db->insert('tl_broadcast_song', ['title'=>'Original', 'artist'=>'Artist', 'album'=>'Album', 'year'=>'2000','labelcode'=>'LC1']);
$id = (int) $db->lastInsertId();
$model = new Contao\ModuleModel(); $model->id = 987123;
$view = new MetadataProbe($model);
$data = $db->fetchAssociative('SELECT * FROM tl_broadcast_song WHERE id=?', [$id]);
$request = $container->get('request_stack')->getCurrentRequest();
$request->setSession(new Symfony\Component\HttpFoundation\Session\Session(new Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage()));
$request->getSession()->set('authenticated-test-user', true);
$token = $container->get('contao.csrf.token_manager')->getToken($container->getParameter('contao.csrf_token_name'))->getValue();
$post = ['FORM_SUBMIT'=>'auriga_song_metadata_987123_'.$id, 'REQUEST_TOKEN'=>$token, 'song_title'=>'Title <test> & "quote"', 'song_artist'=>'Björk', 'song_album'=>'New Album', 'song_year'=>'1997', 'song_labelcode'=>'LC123'];
$related = [];
foreach ([
    'same' => ['Artist','Album','LC1'],
    'otherLabel' => ['Artist','Album','LC2'],
    'otherArtist' => ['Other Artist','Album','LC1'],
    'destination' => ['Artist','New Album','LC1'],
    'caseDifference' => ['artist','Album','LC1'],
] as $name => [$artist,$album,$label]) {
    $db->insert('tl_broadcast_song', ['title'=>'Sibling','artist'=>$artist,'album'=>$album,'labelcode'=>$label]);
    $related[$name] = (int) $db->lastInsertId();
}
try {
    $request->setMethod('GET');
    assertOutput(!str_contains($view->edit($id, false, $data), 'name="song_title"'), 'Guest editor visible');
    assertOutput(str_contains($view->edit($id, true, $data), 'name="song_title"'), 'Administrator editor missing');
    $request->setMethod('POST'); $request->request->replace($post);
    try { $view->edit($id, false, $data); throw new RuntimeException('Unauthorized edit accepted'); }
    catch (Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {}
    $request->request->set('REQUEST_TOKEN', 'invalid');
    try { $view->edit($id, true, $data); throw new RuntimeException('Invalid token accepted'); }
    catch (Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {}
    foreach (['song_title'=>str_repeat('x',256), 'song_year'=>'abcd', 'song_labelcode'=>['invalid']] as $field=>$value) {
        $request->request->replace(array_replace($post, [$field=>$value]));
        $html = $view->edit($id, true, $data);
        assertOutput(str_contains($html, 'role="alert"'), 'Validation error missing');
        assertOutput($db->fetchOne('SELECT title FROM tl_broadcast_song WHERE id=?',[$id])==='Original', 'Invalid edit changed database');
    }
    $request->request->replace($post + ['song_pid'=>'999', 'song_genres'=>'bad']);
    try { $view->edit($id, true, $data); throw new RuntimeException('Missing redirect'); }
    catch (Contao\CoreBundle\Exception\ResponseException $e) { assertOutput($e->getResponse()->getStatusCode()===303, 'Missing POST redirect'); }
    $saved=$db->fetchAssociative('SELECT * FROM tl_broadcast_song WHERE id=?',[$id]);
    foreach (['title','artist','album','year','labelcode'] as $field) assertOutput($saved[$field]===$post['song_'.$field], 'Field not saved: '.$field);
    assertOutput($saved['pid']===$data['pid'], 'Unexpected field modified');
    foreach ($related as $name=>$relatedId) {
        $row=$db->fetchAssociative('SELECT * FROM tl_broadcast_song WHERE id=?',[$relatedId]);
        assertOutput($row['title']==='Sibling', 'Sibling title changed');
        assertOutput($row['artist']===($name==='otherArtist' ? 'Other Artist' : ($name==='caseDifference' ? 'artist' : 'Artist')), 'Sibling artist changed');
        assertOutput($row['album']===(in_array($name,['same','otherLabel','destination'],true) ? 'New Album' : 'Album'), 'Wrong album propagation: '.$name);
        assertOutput($row['labelcode']===($name==='same' ? 'LC123' : ($name==='otherLabel' ? 'LC2' : 'LC1')), 'Wrong label propagation: '.$name);
    }
    $request->setMethod('GET'); $request->request->replace([]);
    assertOutput(str_contains($view->edit($id,true,$saved), '&lt;test&gt;'), 'Metadata not escaped');
    echo "OK: song metadata permissions, CSRF, validation, scoped update, redirect and escaped editor\n";
} finally {
    $db->delete('tl_broadcast_song', ['id'=>$id]);
    foreach ($related as $relatedId) $db->delete('tl_broadcast_song', ['id'=>$relatedId]);
}
