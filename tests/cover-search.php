<?php
require getenv('AURIGA_VENDOR_AUTOLOAD') ?: dirname(__DIR__).'/vendor/autoload.php';
require __DIR__.'/../src/CoverSearch.php';
use Hschottm\AurigaBundle\CoverSearch;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\Exception\TransportException;
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) throw new ErrorException($message, 0, $severity, $file, $line);
});
function check($condition) { if (!$condition) throw new RuntimeException('Assertion failed'); }
$id = '12345678-1234-1234-1234-123456789abc';
foreach (['{}', 'null', 'invalid json', '{"releases":false}', '{"releases":[null,"bad",{}]}'] as $body) {
    check((new CoverSearch(new MockHttpClient(new MockResponse($body))))->search('Album','Artist') === []);
}
check((new CoverSearch(new MockHttpClient(static function () { throw new TransportException('timeout'); })))->search('Album','Artist') === []);
$releases = json_encode(['releases' => [['id'=>$id]]]);
foreach ([new MockResponse('', ['http_code'=>404]), new MockResponse('{"images":false}'), new MockResponse('bad json')] as $response) {
    check((new CoverSearch(new MockHttpClient(static fn ($method, $url) => str_contains($url, 'musicbrainz.org') ? new MockResponse($releases) : $response)))->search('Album','Artist') === []);
}
$images = json_encode(['images'=>[null, ['front'=>true], ['front'=>true,'thumbnails'=>['large'=>'http://example.org/a.png']], ['front'=>true,'thumbnails'=>['large'=>'https://example.org/a.png']], ['front'=>false,'thumbnails'=>['large'=>'https://example.org/back.png']]]]);
check((new CoverSearch(new MockHttpClient([new MockResponse($releases),new MockResponse($images)])))->search('Album','Artist') === ['https://example.org/a.png']);
$gif=base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
check(CoverSearch::imageExtension($gif) === 'gif');
check(CoverSearch::imageExtension('not an image') === null);
check((new CoverSearch(new MockHttpClient(new MockResponse($gif))))->download('https://example.org/image?x=1')['extension'] === 'gif');
check((new CoverSearch(new MockHttpClient(new MockResponse($gif))))->download('https://example.org/image', 5) === null);
check((new CoverSearch(new MockHttpClient(new MockResponse('<html>error</html>'))))->download('https://example.org/image') === null);
check((new CoverSearch(new MockHttpClient()))->download('file:///etc/passwd') === null);
echo "OK: cover lookup and image validation (E_ALL, offline).\n";
check((new CoverSearch(new MockHttpClient([new MockResponse('{"releases":[]}'), new MockResponse('{"releases":[]}'), new MockResponse($releases), new MockResponse($images)])))->search('Album (Remaster)','Artist') === ['https://example.org/a.png']);
echo "OK: album-only fallback.\n";
$cache = new Symfony\Component\Cache\Adapter\ArrayAdapter();
$calls = 0;
$client = new MockHttpClient(static function ($method, $url) use (&$calls, $releases, $images) {
    ++$calls;
    return new MockResponse(strpos($url, 'musicbrainz.org') !== false ? $releases : $images);
});
$search = new CoverSearch($client, $cache);
check($search->search('Cached album', 'Artist') === ['https://example.org/a.png']);
check((new CoverSearch($client, $cache))->search('Cached album', 'Artist') === ['https://example.org/a.png']);
check($calls === 2);
$search->search('Different album', 'Artist');
check($calls === 4);
$failures = 0;
$search = new CoverSearch(new MockHttpClient(static function () use (&$failures) {
    ++$failures;
    throw new TransportException('unavailable');
}), $cache);
check($search->search('Unavailable album', 'Artist') === []);
check($search->search('Unavailable album', 'Artist') === []);
check($failures === 1);
echo "OK: shared positive/negative cache and separate query keys.\n";

function releaseId(int $n): string { return sprintf('00000000-0000-0000-0000-%012d', $n); }
function coverResponse(string $name): MockResponse {
    return new MockResponse(json_encode(['images'=>[['front'=>true,'thumbnails'=>['500'=>'https://example.org/'.$name.'.jpg']]]]));
}
$calls=[];
$client=new MockHttpClient(static function ($method,$url) use (&$calls) {
    $calls[]=$url;
    if (str_contains($url,'musicbrainz.org')) {
        parse_str(parse_url($url,PHP_URL_QUERY),$query);
        check($query['limit']==='10');
        return new MockResponse(json_encode(['releases'=>array_map(static fn($i)=>['id'=>releaseId($i)],range(1,5))]));
    }
    return str_ends_with($url,releaseId(4)) ? coverResponse('fourth') : new MockResponse('', ['http_code'=>404]);
});
check((new CoverSearch($client))->search('Album','Artist')===['https://example.org/fourth.jpg']);
check(count($calls)===6);
echo "OK: cover beyond the old three-release limit and numeric thumbnail sizes.\n";

$queries=[]; $covers=[];
$client=new MockHttpClient(static function ($method,$url) use (&$queries,&$covers) {
    if (str_contains($url,'musicbrainz.org')) {
        parse_str(parse_url($url,PHP_URL_QUERY),$query); $queries[]=$query['query'];
        return new MockResponse(json_encode(['releases'=>count($queries)===1
            ? [['id'=>releaseId(1)]] : [['id'=>releaseId(1)],['id'=>releaseId(2)]]]));
    }
    $covers[]=$url;
    return str_ends_with($url,releaseId(2)) ? coverResponse('fallback') : new MockResponse('', ['http_code'=>404]);
});
check((new CoverSearch($client))->search('Album','Artist')===['https://example.org/fallback.jpg']);
check($queries===['release:"Album" AND artist:"Artist"','release:"Album"']);
check(count($covers)===2);
echo "OK: broaden after coverless releases and avoid repeated release lookups.\n";

foreach (['Album (2011 Remastered)', 'Album [Deluxe Edition]', 'Album - 2020 Remaster', 'Album (Deluxe Edition) [Remastered]'] as $album) {
    $queries=[];
    $client=new MockHttpClient(static function ($method,$url) use (&$queries) {
        if (!str_contains($url,'musicbrainz.org')) return coverResponse('cleaned');
        parse_str(parse_url($url,PHP_URL_QUERY),$query); $queries[]=$query['query'];
        return new MockResponse(json_encode(['releases'=>count($queries)===1 ? [] : [['id'=>releaseId(1)]]]));
    });
    check((new CoverSearch($client))->search($album,'Artist')===['https://example.org/cleaned.jpg']);
    check($queries[1]==='release:"Album" AND artist:"Artist"');
}
$queries=[];
$client=new MockHttpClient(static function ($method,$url) use (&$queries) {
    parse_str(parse_url($url,PHP_URL_QUERY),$query); $queries[]=$query['query'];
    return new MockResponse('{"releases":[]}');
});
check((new CoverSearch($client))->search('Album (Live in Berlin)','Artist')===[]);
check($queries===['release:"Album (Live in Berlin)" AND artist:"Artist"','release:"Album (Live in Berlin)"']);
echo "OK: conservative edition cleanup preserves artist and meaningful parentheses.\n";

$calls=0;
$client=new MockHttpClient(static function ($method,$url) use (&$calls) {
    if (str_contains($url,'musicbrainz.org')) return new MockResponse(json_encode(['releases'=>array_map(static fn($i)=>['id'=>releaseId($i),'score'=>$i],range(1,10))]));
    ++$calls; return coverResponse(basename($url));
});
$found=(new CoverSearch($client))->search('Album','Artist');
check(count($found)===8 && $calls===8 && $found[0]==='https://example.org/'.releaseId(10).'.jpg');
// The original image is usable when the service omits thumbnails.
$client=new MockHttpClient([new MockResponse($releases),new MockResponse('{"images":[{"front":true,"image":"http://example.org/original.jpg"}]}')]);
check((new CoverSearch($client))->search('Album','Artist')===['https://example.org/original.jpg']);
echo "OK: relevance ordering, eight-cover cap and original-image fallback.\n";
