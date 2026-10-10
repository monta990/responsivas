<?php
declare(strict_types=1);
namespace GlpiPlugin\Responsivas\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
final class ReturnController extends ManualFormController
{
    #[Route('/return', name: 'responsivas_return', methods: ['GET'])]
    #[Route('/devolucion', name: 'responsivas_return_es', methods: ['GET'])]
    #[Route('/restitution', name: 'responsivas_return_fr', methods: ['GET'])]
    #[Route('/rueckgabe', name: 'responsivas_return_de', methods: ['GET'])]
    #[Route('/restituzione', name: 'responsivas_return_it', methods: ['GET'])]
    public function __invoke(Request $request): Response { return $this->renderManual($request,'return'); }
}
