<?php
declare(strict_types=1);
namespace GlpiPlugin\Responsivas\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
final class InspectionController extends ManualFormController
{
    #[Route('/inspection', name: 'responsivas_inspection', methods: ['GET'])]
    public function __invoke(Request $request): Response { return $this->renderManual($request,'inspection'); }
}
