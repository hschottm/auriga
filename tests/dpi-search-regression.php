<?php
// Included by the guarded, isolated Contao integration harness.
$searchRequest = static function (array $query, array $post = []) use ($request, $model) {
    $request->query->replace($query);
    foreach (['search', 'field', 'results', 'page'] as $key) Contao\Input::setGet($key, $query[$key] ?? null);
    foreach (['FORM_SUBMIT', 'search', 'field', 'results'] as $key) Contao\Input::setPost($key, $post[$key] ?? null);
    return (new SearchView($model))->render();
};
$fixtureIds = [];
for ($i=0; $i<23; ++$i) {
    $db->insert('tl_broadcast_song', ['pid'=>$broadcast, 'title'=>sprintf('Pagination fixture %02d', $i), 'artist'=>'A / B & C']);
    $fixtureIds[] = (int) $db->lastInsertId();
}
$query = ['search'=>'Pagination fixture', 'field'=>'title', 'results'=>'10'];
$first = $searchRequest($query);
$second = $searchRequest($query + ['page'=>'2']);
check(str_contains($first, 'Pagination fixture 00') && !str_contains($first, 'Pagination fixture 10'), 'Wrong first search page');
check(str_contains($second, 'Pagination fixture 10') && !str_contains($second, 'Pagination fixture 00'), 'Wrong second search page');
$searchRequest(['search'=>'something else', 'field'=>'album', 'results'=>'20']);
$back = $searchRequest($query);
check(str_contains($back, 'Pagination fixture 00') && !str_contains($back, 'Pagination fixture 10') && str_contains($back, 'value="Pagination fixture"'), 'Returning to page 1 lost search state');
check(str_contains($first, 'A%20%2F%20B%20%26%20C'), 'Search links do not encode special characters');
check(str_contains($searchRequest(['search'=>'impossible-fixture', 'field'=>'title', 'results'=>'10']), 'Keine Treffer'), 'Missing no-results feedback');
check(!str_contains($searchRequest([]), 'Keine Treffer'), 'Idle form reports no results');
check(is_string($searchRequest(['search'=>['invalid'], 'page'=>['invalid']])), 'Invalid input failed');
try {
    $searchRequest($query + ['page'=>'99']);
    throw new RuntimeException('Expected out-of-range redirect');
} catch (Contao\CoreBundle\Exception\ResponseException $e) {
    check(str_contains($e->getResponse()->headers->get('Location'), 'page=3'), 'Wrong last-page redirect');
}
try {
    $searchRequest(['page'=>'2'], ['FORM_SUBMIT'=>'dpi', 'search'=>'A / B & C', 'field'=>'artist', 'results'=>'20']);
    throw new RuntimeException('Expected POST redirect');
} catch (Contao\CoreBundle\Exception\ResponseException $e) {
    $response = $e->getResponse();
    $location = $response->headers->get('Location');
    check($response->getStatusCode() === 303 && str_contains($location, 'search=A%20%2F%20B%20%26%20C') && !str_contains($location, 'page='), 'POST redirect lost search or retained old page');
}
$searchRequest([]);
foreach ($fixtureIds as $id) $db->delete('tl_broadcast_song', ['id'=>$id]);
echo "OK: search pagination 1/2/1, independent search state, empty results, encoded links, invalid input and POST redirect\n";
