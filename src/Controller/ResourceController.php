<?php
declare(strict_types=1);

namespace GlpiPlugin\Responsivas\Controller;

use Glpi\Controller\AbstractController;
use GlpiPlugin\Responsivas\Paths;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ResourceController extends AbstractController
{
    #[Route('/resource/logo', name: 'responsivas_logo', methods: ['GET'])]
    public function logo(Request $request): Response
    {
        \Session::checkLoginUser();
        \Session::checkRight('config', UPDATE);

        $path = Paths::logoPath();
        if (!is_file($path) || !is_readable($path)) {
            return new Response('', 404);
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return new Response('', 404);
        }

        $mime = mime_content_type($path) ?: 'image/png';
        return new Response($content, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string)strlen($content),
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
