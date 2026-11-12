<?php
require __DIR__.'/template-output.php';
$twig = $container->get('twig');
$template = $twig->createTemplate(file_get_contents(__DIR__.'/../examples/templates/mod_auriga_dpi_bootstrap.html.twig'));
$data = ['headline'=>'Search', 'hl'=>'h2', 'formaction'=>'/dpi.html', 'lngSearch'=>'Search', 'lngIn'=>'in',
    'lngSearchButton'=>'Find', 'resultsperpage'=>'Per page', 'savedSearch'=>'<needle>', 'savedField'=>'artist',
    'savedResults'=>20, 'options'=>['title','artist'], 'title'=>'Title', 'artist'=>'Artist', 'results'=>[10,20],
    'foundResults'=>true, 'searchResult'=>'Results', 'resFrom'=>1, 'resTo'=>1, 'of'=>'of', 'resTotal'=>1,
    'adventure'=>'Adventure', 'album'=>'Album', 'year'=>'Year', 'labelcode'=>'Label', 'genres'=>'Genres',
    'foundsongs'=>[['advurl'=>'/adventure','adventure'=>'Test adventure','special'=>'','titleurl'=>'/song',
        'title'=>'Result song','artisturl'=>'/artist','artist'=>'Test artist','idurl'=>'/cover','cover'=>'',
        'albumurl'=>'/album','album'=>'Test album','year'=>'2026','labelurl'=>'/label','labelcode'=>'LC', 'genres'=>['Rock']]],
    'pagination'=>'<nav>Pagination</nav>'];
$html = $template->render($data);
assertFormTokens($html);
foreach (['form-control','form-select','btn btn-primary','table-responsive','Result song','Pagination','&lt;needle&gt;'] as $text) assertOutput(str_contains($html,$text), 'Missing '.$text);
$dom = new DOMDocument(); @$dom->loadHTML($html); $xpath = new DOMXPath($dom);
foreach (['search','field','results','FORM_SUBMIT','REQUEST_TOKEN'] as $field) assertOutput($xpath->query('//*[@name="'.$field.'"]')->length === 1, 'Duplicated/missing field '.$field);
assertOutput($xpath->query('//select[@name="field"]/option[@selected and @value="artist"]')->length === 1, 'Lost selected field');
assertOutput($xpath->query('//tbody/tr')->length === 1, 'Lost result row');
$html = $template->render(array_replace($data,['foundResults'=>false]));
assertOutput(!str_contains($html,'<table') && str_contains($html,'<form'), 'Empty result state broken');
echo "OK: inherited Bootstrap DPI form, selected values, CSRF, escaping, result table and pagination\n";
