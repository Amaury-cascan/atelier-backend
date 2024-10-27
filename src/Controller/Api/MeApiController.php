<?php

namespace App\Controller\Api;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class MeApiController extends AbstractController
{
    #[Route('api/me', name: 'app_api_me')]
    public function me(TokenStorageInterface $tokenStorage): JsonResponse
    {
        // Récupération du token depuis TokenStorageInterface
        $token = $tokenStorage->getToken();
        // Si pas de token, on renvoie une erreur
        if ($token === null || !$token->getUser() instanceof UserInterface) {
            return $this->json([
                'error' => 'Unauthorized'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // On récupère l'utilisateur
        $user = $this->getUser();

        // On renvoie directement l'objet utilisateur
        return $this->json($user, Response::HTTP_OK);
    }
}