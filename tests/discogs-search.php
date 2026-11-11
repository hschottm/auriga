<?php
require getenv('AURIGA_VENDOR_AUTOLOAD') ?: dirname(__DIR__).'/vendor/autoload.php';
use Hschottm\AurigaBundle\CoverSearch;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
function verify($condition) { if (!$condition) throw new RuntimeException('Assertion failed'); }
$calls = [];
$gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
$client = new MockHttpClient(function ($method, $url, $options) use (&$calls, $gif) {
    $calls[] = $url;
    $headers = implode("\n", $options['headers']);
    verify(!str_contains($url, 'test-token'));
    if (str_contains($url, 'api.discogs.com')) {
        verify(str_contains($headers, 'Authorization: Discogs token=test-token'));
        verify($options['max_redirects'] === 0);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        verify($query['type'] === 'release');
        if ($query['artist'] !== '') return new MockResponse('{"results":[]}');
        $results = [null, ['cover_image'=>'https://evil.example/test'], ['cover_image'=>'https://i.discogs.com/spacer.gif']];
        for ($i=0; $i<10; ++$i) $results[] = ['cover_image'=>'https://i.discogs.com/'.$i.'.jpg'];
        return new MockResponse(json_encode(['results'=>$results]));
    }
    verify(!str_contains(strtolower($headers), 'authorization'));
    return new MockResponse($gif);
});
$cache = new ArrayAdapter();
$search = new CoverSearch($client, $cache, 'discogs', 'test-token');
$result = $search->search('Album (Deluxe)', 'Artist');
verify(count($result) === 7); // Only the first ten result entries are inspected.
verify(count($calls) === 3);
verify($search->search('Album (Deluxe)', 'Artist') === $result && count($calls) === 3);
verify($search->download($result[0])['extension'] === 'gif');
foreach (['', "bad\ntoken"] as $token) {
    verify((new CoverSearch(new MockHttpClient(function () { throw new RuntimeException('Unexpected request'); }), new ArrayAdapter(), 'discogs', $token))->search('Album', 'Artist') === []);
}
foreach ([401, 429, 500] as $status) {
    $count = 0;
    $bad = new MockHttpClient(function () use (&$count, $status) { ++$count; return new MockResponse('{}', ['http_code'=>$status]); });
    verify((new CoverSearch($bad, new ArrayAdapter(), 'discogs', 'test-token'))->search('Album', 'Artist') === []);
    verify($count === 1);
}
$count = 0;
$other = new MockHttpClient(function () use (&$count) { ++$count; return new MockResponse('{}'); });
(new CoverSearch($other, $cache, 'discogs', 'different-token'))->search('Album (Deluxe)', 'Artist');
(new CoverSearch($other, $cache))->search('Album (Deluxe)', 'Artist');
verify($count === 2);
echo "OK: Discogs fallback, limits, auth isolation, caching, missing token and API failures.\n";
$fields = [];
$client = new MockHttpClient(function ($method, $url) use (&$fields) {
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    $field = array_values(array_diff(array_keys($query), ['type', 'per_page', 'page']))[0];
    $fields[] = $field;
    verify($query[$field] === 'LC 00199');
    return new MockResponse(json_encode(['results'=>[
        ['cover_image'=>'https://i.discogs.com/'.$field.'.jpg'],
        ['cover_image'=>'https://i.discogs.com/shared.jpg'],
    ]]));
});
$results = (new CoverSearch($client, new ArrayAdapter(), 'discogs', 'test-token'))->search('LC 00199', '', true);
verify($fields === ['artist','release_title','q','label','track']);
verify(count($results) === 6 && count(array_unique($results)) === 6);
verify(in_array('https://i.discogs.com/track.jpg', $results, true));
echo "OK: manual multi-field search collects and deduplicates all five fields.\n";
