<?php

namespace App\Controller\Compte;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/compte')]
final class CompteController extends AbstractController
{
    #[Route(name: 'app_compte_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('compte/index.html.twig');
    }
}
