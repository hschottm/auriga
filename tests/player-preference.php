<?php
require __DIR__.'/contao57-integration.php';
use Hschottm\AurigaBundle\PlayerPreference;
Contao\Controller::loadDataContainer('tl_member');
check(isset($GLOBALS['TL_DCA']['tl_member']['fields']['aurigaPlaybackMode']['sql']), 'Preference schema missing');
// This harness is guarded above to only use an isolated auriga57_test_* database.
$columns = $db->createSchemaManager()->listTableColumns('tl_member');
if (!isset($columns['aurigaplaybackmode'])) $db->executeStatement("ALTER TABLE tl_member ADD aurigaPlaybackMode varchar(5) NOT NULL DEFAULT 'full'");
$storage = $container->get('security.token_storage');
$oldToken = $storage->getToken();
$id = null;
try {
    check(PlayerPreference::context()['mode'] === 'full', 'Guest default');
    $request->setMethod('POST');
    $request->request->set('FORM_SUBMIT','auriga_player_preference');
    try { PlayerPreference::context(); throw new RuntimeException('Guest write accepted'); }
    catch (Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {}
    $db->insert('tl_member', ['username'=>'auriga-pref-'.bin2hex(random_bytes(6)), 'aurigaPlaybackMode'=>'full']);
    $id = (int) $db->lastInsertId();
    $member = (new ReflectionClass(Contao\FrontendUser::class))->newInstanceWithoutConstructor();
    $member->id = $id;
    $token = new Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($member,'contao_frontend',['ROLE_MEMBER']);
    $request->getSession()->start();
    $request->getSession()->set('_security_contao_frontend',serialize($token));
    $storage->setToken($token);
    try { PlayerPreference::context(); throw new RuntimeException('Missing CSRF accepted'); }
    catch (Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {}
    $request->setMethod('GET');
    $context = PlayerPreference::context();
    check($context['mode'] === 'full' && $context['token'] !== '', 'Member default');
    $request->setMethod('POST');
    $request->request->set('REQUEST_TOKEN', $context['token']);
    $request->request->set('mode', 'invalid');
    try { PlayerPreference::context(); throw new RuntimeException('Invalid mode accepted'); }
    catch (Symfony\Component\HttpKernel\Exception\BadRequestHttpException $e) {}
    foreach (['story','full'] as $mode) {
        $request->request->set('mode', $mode);
        $request->request->set('memberId', 999999);
        try { PlayerPreference::context(); throw new RuntimeException('Missing save response'); }
        catch (Contao\CoreBundle\Exception\ResponseException $e) {
            check(json_decode($e->getResponse()->getContent(), true)['mode'] === $mode, 'Save response');
        }
        $request->setMethod('GET');
        check(PlayerPreference::context()['mode'] === $mode, 'Stored account preference not read');
        $request->setMethod('POST');
    }
    echo "OK: account preference persists, guest/CSRF/invalid writes rejected, posted member id ignored\n";
} finally {
    if ($id) $db->delete('tl_member',['id'=>$id]);
    $request->setMethod('GET');
    $request->request->replace([]);
    $request->getSession()->remove('_security_contao_frontend');
    $storage->setToken($oldToken);
}
