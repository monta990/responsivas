<?php
declare(strict_types=1);

namespace GlpiPlugin\Responsivas\Controller;

use Glpi\Controller\AbstractController;
use GlpiPlugin\Responsivas\Service\ConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ConfigController extends AbstractController
{
    #[Route('/config', name: 'responsivas_config', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        return ConfigService::handle();
    }

    #[Route('/config/export', name: 'responsivas_config_export', methods: ['GET'])]
    public function export(): Response
    {
        return ConfigService::export();
    }
}
