<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('administration/client/information')]
final class ClientInformationController extends AbstractController
{
    #[Route(name: 'app_client_information_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->discontinued();
    }

    #[Route('/new', name: 'app_client_information_new', methods: ['GET', 'POST'])]
    public function new(): Response
    {
        return $this->discontinued();
    }

    #[Route('/{id}', name: 'app_client_information_show', methods: ['GET'])]
    public function show(): Response
    {
        return $this->discontinued();
    }

    #[Route('/{id}/edit', name: 'app_client_information_edit', methods: ['GET', 'POST'])]
    public function edit(): Response
    {
        return $this->discontinued();
    }

    #[Route('/{id}', name: 'app_client_information_delete', methods: ['POST'])]
    public function delete(): Response
    {
        return $this->discontinued();
    }

    private function discontinued(): Response
    {
        $this->addFlash('error', 'Les fiches de suivi (photos, notes, état des ongles) ne sont plus conservées.');

        return $this->redirectToRoute('app_client_index');
    }
}
