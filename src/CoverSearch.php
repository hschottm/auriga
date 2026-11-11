<?php

namespace Hschottm\AurigaBundle;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

/** Optional artwork must never prevent the song page from rendering. */
final class CoverSearch
{
    private $client;
    private $cache;
    private ?float $deadline = null;
    private float $lastMusicBrainzRequest = 0.0;

    public function __construct(?HttpClientInterface $client = null, ?\Psr\Cache\CacheItemPoolInterface $cache = null, private string $provider = 'musicbrainz', private string $discogsToken = '')
    {
        $this->cache = $cache;
        if ($cache === null && \Contao\System::getContainer() !== null) {
            try {
                $this->cache = new \Symfony\Component\Cache\Adapter\FilesystemAdapter('auriga_covers', 86400, \Contao\System::getContainer()->getParameter('kernel.cache_dir').'/auriga');
            } catch (\Exception $exception) {
                // Continue without caching if the cache directory is unavailable.
            }
        }
        $this->client = $client ?? HttpClient::create([
            'headers' => ['User-Agent' => 'Auriga/1.0 (https://github.com/hschottm/auriga)'],
        ]);
    }

    private function content(string $url, int $limit, array $options = []): ?string
    {
        $response = null;
        try {
            $remaining = $this->deadline === null ? 5.0 : min(5.0, $this->deadline - microtime(true));
            if ($remaining <= 0) return null;
            $response = $this->client->request('GET', $url, ['timeout' => min(3.0, $remaining), 'max_duration' => $remaining] + $options);
            if ($response->getStatusCode() !== 200) return null;
            $content = '';
            foreach ($this->client->stream($response) as $chunk) {
                $content .= $chunk->getContent();
                if (strlen($content) > $limit) return null;
            }
            return $content;
        } catch (ExceptionInterface $exception) {
            return null;
        } finally {
            if ($response !== null) $response->cancel();
        }
    }

    private function json(string $url, array $options = []): array
    {
        $content = $this->content($url, 1000000, $options);
        if ($content === null) return [];
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    public function search(string $album, string $artist, bool $manual = false): array
    {
        $key = hash('sha256', serialize(['v4', $manual, $this->provider, hash('sha256', $this->discogsToken), trim($album), trim($artist)]));
        $item = null;
        try {
            if ($this->cache !== null) {
                $item = $this->cache->getItem($key);
                if ($item->isHit() && is_array($item->get())) return $item->get();
            }
        } catch (\Exception $exception) {
            // A cache failure must not break the song page.
        }
        $images = $this->provider === 'discogs' ? $this->searchDiscogs($album, $artist, $manual) : $this->searchRemote($album, $artist);
        try {
            if ($item !== null) $this->cache->save($item->set($images)->expiresAfter($images ? 86400 : 600));
        } catch (\Exception $exception) {
        }
        return $images;
    }

    /** Strip edition labels only; meaningful parenthetical album titles stay intact. */
    private function cleanAlbum(string $album): string
    {
        $edition = '(?:remaster(?:ed)?|deluxe|expanded|anniversary|special edition|bonus tracks?)';
        $clean = preg_replace('~\s*[([][^)\]]*\b'.$edition.'\b[^)\]]*[)\]]~iu', '', $album);
        $clean = preg_replace('~\s+[-–—:]\s*(?:(?:19|20)\d{2}\s+)?'.$edition.'\b.*$~iu', '', $clean);
        return trim(preg_replace('/\s+/u', ' ', $clean)) ?: $album;
    }

    private function musicBrainz(string $query): array
    {
        $delay = 1.1 - (microtime(true) - $this->lastMusicBrainzRequest);
        if ($delay > 0) {
            if (microtime(true) + $delay >= $this->deadline) return [];
            usleep((int) ($delay * 1000000));
        }
        $this->lastMusicBrainzRequest = microtime(true);
        return $this->json('https://musicbrainz.org/ws/2/release?'.http_build_query([
            'query' => $query, 'fmt' => 'json', 'limit' => 10,
        ]));
    }

    private function frontImages(array $cover): array
    {
        $urls = [];
        foreach (is_array($cover['images'] ?? null) ? $cover['images'] : [] as $image) {
            if (!is_array($image) || ($image['front'] ?? false) !== true) continue;
            $thumbnails = is_array($image['thumbnails'] ?? null) ? $image['thumbnails'] : [];
            foreach ([$thumbnails['500'] ?? null, $thumbnails['large'] ?? null,
                $thumbnails['1200'] ?? null, $image['image'] ?? null] as $url) {
                if (!is_string($url)) continue;
                $url = preg_replace('~^http://~i', 'https://', $url);
                if (filter_var($url, FILTER_VALIDATE_URL) && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https') {
                    $urls[] = $url;
                    break;
                }
            }
        }
        return array_values(array_unique($urls));
    }

    private function searchRemote(string $album, string $artist): array
    {
        $album = trim($album);
        $artist = trim($artist);
        if ($album === '') return [];
        $quote = static fn ($value) => '"'.addcslashes($value, '\\"').'"';
        $clean = $this->cleanAlbum($album);
        $artistQuery = $artist !== '' ? ' AND artist:'.$quote($artist) : '';
        $queries = array_unique([
            'release:'.$quote($album).$artistQuery,
            'release:'.$quote($clean).$artistQuery,
            'release:'.$quote($clean),
        ]);
        $seen = [];
        $images = [];
        $requests = 0;
        $this->deadline = microtime(true) + 15;
        try {
            foreach ($queries as $query) {
                if (microtime(true) >= $this->deadline || $requests >= 20) break;
                $data = $this->musicBrainz($query);
                // Do not broaden the query on service errors or malformed responses.
                if (!is_array($data['releases'] ?? null)) break;
                $releases = array_values(array_filter($data['releases'], 'is_array'));
                // Keep MusicBrainz relevance order, preferring higher scores when supplied.
                usort($releases, static fn ($a, $b) => (int) ($b['score'] ?? 0) <=> (int) ($a['score'] ?? 0));
                foreach (array_slice($releases, 0, 10) as $release) {
                    $id = $release['id'] ?? null;
                    if (!is_string($id) || !preg_match('/^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/i', $id)) continue;
                    $id = strtolower($id);
                    if (isset($seen[$id])) continue;
                    if (microtime(true) >= $this->deadline || $requests >= 20) break;
                    $seen[$id] = true;
                    ++$requests;
                    $images = array_values(array_unique(array_merge($images,
                        $this->frontImages($this->json('https://coverartarchive.org/release/'.$id)))));
                    if (count($images) >= 8) return array_slice($images, 0, 8);
                }
                // Broaden only when this search found no usable front covers.
                if ($images !== []) return $images;
            }
            return $images;
        } finally {
            $this->deadline = null;
        }
    }

    private function searchDiscogs(string $album, string $artist, bool $manual): array
    {
        $album = trim($album);
        $artist = trim($artist);
        // Tokens are opaque credentials, never query parameters or global client headers.
        if ($album === '' || !preg_match('/^[A-Za-z0-9_-]+$/D', $this->discogsToken)) return [];
        $clean = $this->cleanAlbum($album);
        $queries = array_unique([
            http_build_query(['release_title' => $album, 'artist' => $artist]),
            http_build_query(['release_title' => $clean, 'artist' => $artist]),
            http_build_query(['release_title' => $clean, 'artist' => '']),
        ]);
        if ($manual) {
            // Discogs has no dedicated label-code filter. General search includes
            // indexed identifiers; catno would mean catalogue number, not LC code.
            $queries = array_map(static fn ($field) => http_build_query([$field => $album]),
                ['artist', 'release_title', 'q', 'label', 'track']);
        }
        $groups = [];
        $this->deadline = microtime(true) + 15;
        try {
            foreach ($queries as $query) {
                $data = $this->json('https://api.discogs.com/database/search?type=release&per_page=10&page=1&'.$query, [
                    'headers' => [
                        'Authorization' => 'Discogs token='.$this->discogsToken,
                        'User-Agent' => 'Auriga/1.0 (https://github.com/hschottm/auriga)',
                    ],
                    'max_redirects' => 0,
                ]);
                // Stop on authorization failures, rate limits and malformed responses.
                if (!is_array($data['results'] ?? null)) break;
                $images = [];
                foreach (array_slice($data['results'], 0, 10) as $result) {
                    if (!is_array($result)) continue;
                    $url = $result['cover_image'] ?? null;
                    if (!is_string($url) || !filter_var($url, FILTER_VALIDATE_URL)) continue;
                    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
                    if (parse_url($url, PHP_URL_SCHEME) !== 'https' ||
                        !in_array($host, ['i.discogs.com', 'img.discogs.com'], true) ||
                        str_contains($url, 'spacer.gif')) continue;
                    $images[] = $url;
                }
                $images = array_values(array_unique($images));
                if ($images && !$manual) return array_slice($images, 0, 8);
                $groups[] = $images;
            }
            // Interleave fields so early artist matches do not hide track matches.
            $merged = [];
            for ($i = 0; $i < 10; ++$i) {
                foreach ($groups as $group) {
                    if (isset($group[$i]) && !in_array($group[$i], $merged, true)) $merged[] = $group[$i];
                    if (count($merged) >= 8) return $merged;
                }
            }
            return $merged;
        } finally {
            $this->deadline = null;
        }
    }

    public static function imageExtension(string $bytes): ?string
    {
        $size = @getimagesizefromstring($bytes);
        if (!$size || $size[0] < 1 || $size[1] < 1 || $size[0] > 10000 || $size[1] > 10000) return null;
        return [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif'][$size[2]] ?? null;
    }

    public function download(string $url, int $limit = 5000000): ?array
    {
        if (!filter_var($url, FILTER_VALIDATE_URL) || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') return null;
        $bytes = $this->content($url, $limit);
        if ($bytes === null || ($extension = self::imageExtension($bytes)) === null) return null;
        return ['bytes' => $bytes, 'extension' => $extension];
    }
}
