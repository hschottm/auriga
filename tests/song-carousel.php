<?php
/** Uses the same real Contao bootstrap and escaping checks as template-output.php. */
require __DIR__.'/template-output.php';
$twig = $container->get('twig');
$template = $twig->createTemplate(file_get_contents(__DIR__.'/../examples/templates/mod_auriga_song_bootstrap.html.twig'));
$data = ['song'=>['album'=>'Album','title'=>'Song','artist'=>'Artist','genres'=>[]],
    'currentpath'=>[], 'headline'=>'', 'isAdmin'=>true, 'formaction'=>'/song.html',
    'coverSearch'=>['https://example.org/first.jpg','https://example.org/second.jpg'],
    'possibleImage'=>'<img src="preview.jpg">', 'usecover'=>1,
    'lngShowPrevCover'=>'Previous', 'lngShowNextCover'=>'Next'];
$html = $template->render($data);
$dom = new DOMDocument();
@$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
$xpath = new DOMXPath($dom);
$slides = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " carousel-item ")]');
assertOutput($slides->length === 2, 'Expected two carousel slides');
foreach ($slides as $index=>$slide) {
    assertOutput($xpath->evaluate('string(.//input[@name="usecover"]/@value)', $slide) === (string)$index, 'Slide submits the wrong cover index');
    assertFormTokens($dom->saveHTML($slide));
    assertOutput($xpath->evaluate('string(.//input[@name="FORM_SUBMIT"]/@value)', $slide) === 'image', 'Wrong cover form type');
    assertOutput(str_contains($slide->getAttribute('class'), ' active') === ($index===1), 'Selected slide not retained');
}
assertOutput(str_contains($html,'value="searchform"') && str_contains($html,'value="uploadform"'), 'Inherited search/upload forms missing');
$id=$xpath->evaluate('string(//div[@aria-roledescription="Karussell"]/@id)');
assertOutput($id!=='' && substr_count($html,'data-bs-target="#'.$id.'"')===2, 'Carousel controls target mismatch');
$data['isAdmin']=false;
$html=$template->render($data);
assertOutput(!str_contains($html,'<form') && str_contains($html,'carousel-item'), 'Guest must see previews without editing forms');
$data['isAdmin']=true;
$data['albumcover']='<img src="saved.jpg">';
$html=$template->render($data);
assertOutput(str_contains($html,'saved.jpg') && !str_contains($html,'<form') && !str_contains($html,'carousel-item'), 'Saved cover must hide suggestions and forms');
$data['albumcover']=''; $data['possibleImage']=''; $data['coverSearch']=[];
$html=$template->render($data);
assertOutput(str_contains($html,'coverplaceholder') && !str_contains($html,'carousel-item') && str_contains($html,'value="uploadform"'), 'No-results state broken');
$data['coverSearch']=['https://example.org/single.jpg']; $data['usecover']=0;
$html=$template->render($data);
assertOutput(str_contains($html,'carousel-item active') && !str_contains($html,'data-bs-slide='), 'Single result should have no carousel controls');
echo "OK: carousel selection indexes, CSRF tokens, admin/guest, existing cover, empty/single results and inherited cover tools.\n";
