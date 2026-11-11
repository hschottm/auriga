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

    public function __construct(?HttpClientInterface $client = null, ?\Psr\Cache\CacheItemPoolInterface $cache = null)
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

    private function content(string $url, int $limit): ?string
    {
        $response = null;
        try {
            $response = $this->client->request('GET', $url, ['timeout' => 3, 'max_duration' => 5]);
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

    private function json(string $url): array
    {
        $content = $this->content($url, 1000000);
        if ($content === null) return [];
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    public function search(string $album, string $artist): array
    {
        $key = hash('sha256', serialize([trim($album), trim($artist)]));
        $item = null;
        try {
            if ($this->cache !== null) {
                $item = $this->cache->getItem($key);
                if ($item->isHit() && is_array($item->get())) return $item->get();
            }
        } catch (\Exception $exception) {
            // A cache failure must not break the song page.
        }
        $images = $this->searchRemote($album, $artist);
        try {
            if ($item !== null) $this->cache->save($item->set($images)->expiresAfter($images ? 86400 : 600));
        } catch (\Exception $exception) {
        }
        return $images;
    }

    private function searchRemote(string $album, string $artist): array
    {
        $album = trim($album);
        if ($album === '') return [];
        $quote = static function ($value) { return '"'.addcslashes($value, '\\"').'"'; };
        $query = 'release:'.$quote($album);
        if (trim($artist) !== '') $query .= ' AND artist:'.$quote(trim($artist));
        $started = microtime(true);
        $data = $this->json('https://musicbrainz.org/ws/2/release?'.http_build_query([
            'query' => $query, 'fmt' => 'json', 'limit' => 3,
        ]));
        if (!isset($data['releases']) || !is_array($data['releases'])) return [];
        if ($data['releases'] === []) {
            // Preserve the broader album-only fallback; space MusicBrainz requests.
            $delay = 1.1 - (microtime(true) - $started);
            if ($delay > 0) usleep((int) ($delay * 1000000));
            $fallback = trim(explode('(', $album, 2)[0]);
            if ($fallback === '') return [];
            $data = $this->json('https://musicbrainz.org/ws/2/release?'.http_build_query([
                'query' => 'release:'.$quote($fallback), 'fmt' => 'json', 'limit' => 3,
            ]));
            if (!isset($data['releases']) || !is_array($data['releases'])) return [];
        }
        $images = [];
        $seen = [];
        foreach (array_slice($data['releases'], 0, 3) as $release) {
            $id = is_array($release) ? ($release['id'] ?? null) : null;
            if (!is_string($id) || !preg_match('/^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/i', $id) || isset($seen[$id])) continue;
            $seen[$id] = true;
            $cover = $this->json('https://coverartarchive.org/release/'.$id);
            if (!isset($cover['images']) || !is_array($cover['images'])) continue;
            foreach ($cover['images'] as $image) {
                if (!is_array($image) || ($image['front'] ?? false) !== true) continue;
                $url = $image['thumbnails']['large'] ?? null;
                if (!is_string($url)) continue;
                $url = preg_replace('~^http://~i', 'https://', $url);
                if (filter_var($url, FILTER_VALIDATE_URL) && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https') $images[] = $url;
            }
        }
        return array_values(array_unique($images));
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
