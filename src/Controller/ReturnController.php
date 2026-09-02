<?php
declare(strict_types=1);
namespace GlpiPlugin\Responsivas\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
final class ReturnController extends ManualFormController
{
    #[Route('/return', name: 'responsivas_return', methods: ['GET'])]
    public function __invoke(Request $request): Response { return $this->renderManual($request,'return'); }
}
