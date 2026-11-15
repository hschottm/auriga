<?php
require __DIR__.'/template-output.php';
$data = [
    'broadcasts'=>[['chapter'=>1,'date'=>'today','hour'=>1,'title'=>'Test','isStory'=>false,'songs'=>[]]],
    'adventure'=>['numbering'=>1,'title'=>'Adventure','description'=>''],
    'nav_first_title'=>'First', 'nav_prev_title'=>'Previous',
    'nav_next_title'=>'Next', 'nav_last_title'=>'Last',
    'nav_next_url'=>'/next?x=1&y=2', 'nav_last_url'=>'/last',
];
$html = renderTemplate('mod_auriga_broadcast', $data);
$dom = new DOMDocument(); @$dom->loadHTML($html);
$xpath = new DOMXPath($dom);
assertOutput($xpath->query('//a[@class="auriga-chapter-control"]')->length === 2, 'Active navigation links missing');
assertOutput($xpath->query('//span[@aria-disabled="true"]')->length === 2, 'Disabled controls missing');
assertOutput($xpath->query('//a[@aria-label="Next"]')->item(0)->getAttribute('href') === '/next?x=1&y=2', 'Navigation URL changed');
assertOutput(substr_count($html,'<svg') === 4 && !str_contains($html,'control_start'), 'Lucide icons missing');
echo "OK: chapter SVG controls, active URLs and disabled destinations\n";

$derived = $container->get('twig')->createTemplate(file_get_contents(__DIR__.'/../examples/templates/mod_auriga_broadcast_bootstrap.html.twig'));
$derivedHtml = $derived->render($data + ['cssID'=>'', 'class'=>'', 'headline'=>'', 'urlUp'=>'/adventure']);
assertOutput(substr_count($derivedHtml, '<svg') === 4, 'Inherited navigation icons missing');
assertOutput(str_contains($derivedHtml, 'href="/next?x=1&amp;y=2"'), 'Inherited navigation link missing');
echo "OK: Bootstrap override inherits chapter navigation controls\n";
