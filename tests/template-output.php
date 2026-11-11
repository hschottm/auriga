<?php
/** Render the shipped templates through Contao's real Twig/legacy bridge. */
$project = getenv('AURIGA_TEST_PROJECT');
if (!$project) throw new RuntimeException('Set AURIGA_TEST_PROJECT to a Contao 5.7 installation');
require $project.'/vendor/autoload.php';
$kernel = Contao\ManagerBundle\HttpKernel\ContaoKernel::fromInput($project, new Symfony\Component\Console\Input\ArrayInput(['--env'=>'prod']));
$kernel->boot();
$container = $kernel->getContainer();
$request = Symfony\Component\HttpFoundation\Request::create('https://auriga.test/');
$request->attributes->set('_scope', 'frontend');
$container->get('request_stack')->push($request);
foreach ($container->get('event_dispatcher')->getListeners('kernel.request') as $listener) {
    if (is_array($listener) && $listener[0] instanceof Contao\CoreBundle\EventListener\CsrfTokenCookieSubscriber) {
        $listener(new Symfony\Component\HttpKernel\Event\RequestEvent($kernel, $request, Symfony\Component\HttpKernel\HttpKernelInterface::MAIN_REQUEST));
    }
}
$container->get('contao.framework')->initialize();
$csrfManager = $container->get('contao.csrf.token_manager');
function assertFormTokens(string $html): void {
    global $csrfManager, $container;
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $inputs = (new DOMXPath($dom))->query('//input[@name="REQUEST_TOKEN"]');
    assertOutput($inputs->length > 0, 'No request token fields');
    foreach ($inputs as $input) {
        assertOutput($input->getAttribute('value') !== '' && $csrfManager->isTokenValid(new Symfony\Component\Security\Csrf\CsrfToken($container->getParameter('contao.csrf_token_name'), $input->getAttribute('value'))), 'Invalid real Contao CSRF token');
    }
}
function assertOutput(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function renderTemplate(string $name, array $data): string {
    $template = new Contao\FrontendTemplate($name);
    $template->setData(array_replace([
        'class'=>'test-module', 'cssID'=>' id="test-id"', 'style'=>'margin-top:2px',
        'headline'=>'', 'hl'=>'h2',
    ], $data));
    return $template->parse();
}
$attack = '\"><script>alert(1)</script>';
$song = ['album'=>$attack, 'artist'=>$attack, 'title'=>$attack, 'year'=>null,
    'labelcode'=>null, 'length'=>null, 'composer'=>null, 'special'=>'', 'genres'=>[$attack]];
$html = renderTemplate('mod_auriga_song', ['song'=>$song, 'currentpath'=>[['url'=>'/test?q="bad"','title'=>$attack]]]);
assertOutput(!str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;'), 'Song text must be escaped');
assertOutput(str_contains($html, 'q=&quot;bad&quot;'), 'Song URL attribute must be escaped');
assertOutput(str_contains($html, 'coverplaceholder'), 'Missing cover must show placeholder');
assertOutput(str_contains($html, 'id="test-id"') && str_contains($html, 'ptw_song') && str_contains($html, 'margin-top'), 'Wrapper attributes must survive');
$html = renderTemplate('mod_auriga_song', ['song'=>$song, 'currentpath'=>[], 'isAdmin'=>true,
    'possibleImage'=>'<img src="preview.gif" alt="preview">', 'albumcover'=>'', 'formaction'=>'/song.html',
    'urlShowPrev'=>'/previous', 'urlShowNext'=>'/next', 'imagePosition'=>'1/2', 'usecover'=>'candidate',
    'searchValue'=>$attack]);
assertOutput(str_contains($html, '<img src="preview.gif"'), 'Generated cover HTML must remain HTML');
assertFormTokens($html);
assertOutput(substr_count($html, 'name="REQUEST_TOKEN"') === 3, 'All cover forms need a CSRF token');
assertOutput(!str_contains($html, '<script>') && !str_contains($html, 'coverplaceholder'), 'Cover preview/search state');
$html = renderTemplate('mod_auriga_song', ['song'=>$song,'currentpath'=>[], 'isAdmin'=>true,'albumcover'=>'<img src="saved.gif">']);
assertOutput(str_contains($html,'saved.gif') && !str_contains($html,'<form'), 'Saved covers must hide cover search/upload forms');
$html = renderTemplate('mod_auriga_dpi', ['savedSearch'=>$attack, 'savedField'=>'title', 'savedResults'=>10,
    'options'=>['title'], 'title'=>'Titel', 'results'=>[10,20], 'foundResults'=>false]);
assertFormTokens($html);
assertOutput(!str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;'), 'Search input must be escaped');
assertOutput(str_contains($html, '>Titel</option>') && str_contains($html, 'selected'), 'Dynamic search labels and selected options');
foreach (['mod_auriga_broadcastlist','mod_auriga_archivebrowser'] as $name) {
    $html = renderTemplate($name, ['adventure'=>['numbering'=>1,'title'=>'Schrippen f&uuml;r&apos;n Sechser','description'=>'<em>Story</em>'],
        'broadcasts'=>[['chapter'=>1,'isSpecial'=>true,'isStory'=>false,'url'=>'/broadcast','title'=>'Special',
            'date'=>'today','length'=>'','frequency'=>'','samplerate'=>'','providers'=>['AB'=>1],'files'=>['/recording.mp3']]],
        'providers'=>[['initials'=>'AB','name'=>$attack,'homepage'=>'javascript:alert(1)'],['initials'=>'CD','name'=>'Provider','homepage'=>'https://example.org/']]]);
    assertOutput(str_contains($html,'<em>Story</em>'), 'Rich text must remain HTML');
    assertOutput(substr_count($html,'<span') === substr_count($html,'</span>'), 'Special broadcasts must close their span');
    assertOutput(!str_contains($html,'href="javascript:') && !str_contains($html,'<script>'), 'Provider output must remain safe');
    assertOutput(!str_contains($html,'&amp;apos;'), 'Stored HTML entities must not be double encoded');
}
$html = renderTemplate('be_import_popwelt', ['hrefBack'=>'/back','goBack'=>'Back','request'=>'/import',
    'headline'=>$attack,'broadcasts'=>[['ausgabe'=>1,'datum'=>'today']]]);
assertOutput(!str_contains($html,'<script>') , 'Archived backend template escaping/token');
assertFormTokens($html);
echo "OK: Twig escaping, wrapper attributes, cover states, CSRF tokens, search options, providers and archived backend template.\n";
