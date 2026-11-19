<?php

namespace Hschottm\AurigaBundle;

use Contao\CoreBundle\Exception\ResponseException;
use Contao\FrontendUser;
use Contao\System;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;

final class PlayerPreference
{
    public static function context(): array
    {
        $container = System::getContainer();
        $request = $container->get('request_stack')->getCurrentRequest();
        $user = $container->get('security.token_storage')->getToken()?->getUser();
        $member = $container->get('contao.security.token_checker')->hasFrontendUser() && $user instanceof FrontendUser && (int) $user->id > 0;
        $post = $request?->request->all() ?? [];
        $saving = $request?->isMethod('POST') && ($post['FORM_SUBMIT'] ?? null) === 'auriga_player_preference';
        if (!$member) {
            if ($saving) throw new AccessDeniedHttpException('Member login required');
            return ['mode' => 'full'];
        }
        $manager = $container->get('contao.csrf.token_manager');
        $name = $container->getParameter('contao.csrf_token_name');
        $db = $container->get('database_connection');
        if ($saving) {
            $token = $post['REQUEST_TOKEN'] ?? null;
            if (!is_string($token) || !$manager->isTokenValid(new CsrfToken($name, $token))) throw new AccessDeniedHttpException('Invalid request token');
            $mode = $post['mode'] ?? null;
            if (!in_array($mode, ['full', 'story'], true)) throw new BadRequestHttpException('Invalid playback mode');
            // Never accept a member id from the request.
            $db->update('tl_member', ['aurigaPlaybackMode' => $mode], ['id' => (int) $user->id]);
            $response = new JsonResponse(['mode' => $mode]);
            $response->headers->set('Cache-Control', 'private, no-store');
            throw new ResponseException($response);
        }
        $mode = $db->fetchOne('SELECT aurigaPlaybackMode FROM tl_member WHERE id = ?', [(int) $user->id]);
        return ['mode' => $mode === 'story' ? 'story' : 'full', 'token' => $manager->getToken($name)->getValue()];
    }
}
