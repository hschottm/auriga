<?php

namespace Hschottm\AurigaBundle;

use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\FilesModel;
use Contao\System;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class ContentMediafileBrowser extends \Contao\ContentElement
{
    protected $strTemplate = 'ce_mediafile_browser';

    private const AUDIO = ['mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'aac' => 'audio/aac', 'ogg' => 'audio/ogg', 'oga' => 'audio/ogg', 'opus' => 'audio/ogg', 'wav' => 'audio/wav', 'flac' => 'audio/flac'];

    protected function canAccessFile(string $path): bool
    {
        // Public visibility is inherited from any ancestor folder.
        for ($folder = dirname($path); $folder !== '.' && $folder !== '/'; $folder = dirname($folder)) {
            $model = FilesModel::findByPath($folder);
            if ($model && $model->public) return true;
        }
        $groups = \Contao\StringUtil::deserialize($this->aurigaBrowserGroups, true);
        return $groups !== [] && System::getContainer()->get('contao.security.token_checker')->hasFrontendUser()
            && System::getContainer()->get('security.authorization_checker')->isGranted(
                \Contao\CoreBundle\Security\ContaoCorePermissions::MEMBER_IN_GROUPS, $groups);
    }

    protected function compile()
    {
        $container = System::getContainer();
        $request = $container->get('request_stack')->getCurrentRequest();
        $this->loadLanguageFile('tl_content');
        $labels = $GLOBALS['TL_LANG']['tl_content']['aurigaBrowser'];
        $this->Template->labels = $labels;
        $this->Template->entries = [];
        $this->Template->parentUrl = null;
        $this->Template->currentDirectory = '/';
        $this->Template->message = '';
        if (!$request || $container->get('contao.routing.scope_matcher')->isBackendRequest($request)) {
            $this->Template->message = 'MediafileBrowser';
            return;
        }
        // Lists and downloads must not be served from the public page cache.
        global $objPage;
        if ($objPage) { $objPage->cache = 0; $objPage->clientCache = 0; }
        $root = $this->aurigaBrowserRoot ? FilesModel::findByUuid($this->aurigaBrowserRoot) : null;
        if (!$root || $root->type !== 'folder') {
            $this->Template->message = $labels['unavailable'];
            return;
        }
        $project = $container->getParameter('kernel.project_dir');
        $key = 'auriga_browser_'.$this->id;
        $downloadKey = $key.'_download';
        $params = $request->query->all();
        $relative = $params[$key] ?? '';
        $download = $params[$downloadKey] ?? null;
        $url = static function (string $path, string $download = '') use ($request, $params, $key, $downloadKey): string {
            unset($params[$key], $params[$downloadKey]);
            $params[$key] = $path;
            if ($download !== '') $params[$downloadKey] = $download;
            return $request->getPathInfo().'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        };
        try {
            if (!is_string($relative) || ($download !== null && !in_array($download, ['1', 'play'], true))) throw new \InvalidArgumentException();
            $browser = new MediafileBrowser($project.'/'.$container->getParameter('contao.upload_path'), $project.'/'.$root->path, (string) ($this->aurigaBrowserExtensions ?? 'mp3'));
            if ($download !== null) {
                $file = $browser->resolve($relative);
                if (!is_file($file) || !$browser->allows($relative)) throw new \InvalidArgumentException();
                if (!$this->canAccessFile($root->path.'/'.$relative)) throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Member access required');
                $audioType = self::AUDIO[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? null;
                if ($download === 'play' && $audioType === null) throw new \InvalidArgumentException();
                $response = new BinaryFileResponse($file);
                $response->setContentDisposition(($download === 'play' ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT), basename($file));
                $response->headers->set('Content-Type', $download === 'play' ? $audioType : 'application/octet-stream');
                $response->headers->set('X-Content-Type-Options', 'nosniff');
                $response->headers->set('Cache-Control', 'private, no-store');
                throw new ResponseException($response);
            }
            $entries = $browser->entries($relative);
            foreach ($entries as &$entry) {
                $entry['allowed'] = $entry['directory'] || $this->canAccessFile($root->path.'/'.$entry['path']);
                $entry['url'] = $entry['allowed'] ? $url($entry['path'], $entry['directory'] ? '' : '1') : null;
                $entry['audioType'] = !$entry['directory'] ? (self::AUDIO[strtolower(pathinfo($entry['name'], PATHINFO_EXTENSION))] ?? null) : null;
                $entry['playUrl'] = $entry['allowed'] && $entry['audioType'] ? $url($entry['path'], 'play') : null;
                $entry['date'] = \Contao\Date::parse(\Contao\Config::get('datimFormat'), $entry['modified']);
                $entry['formattedSize'] = $entry['directory'] ? '' : \Contao\System::getReadableSize($entry['size']);
            }
            unset($entry);
            $this->Template->entries = $entries;
            $this->Template->currentDirectory = '/'.$relative;
            if ($relative !== '') $this->Template->parentUrl = $url(str_contains($relative, '/') ? substr($relative, 0, strrpos($relative, '/')) : '');
        } catch (\InvalidArgumentException | \UnexpectedValueException $e) {
            throw new PageNotFoundException('MediafileBrowser path not found');
        }
    }
}
