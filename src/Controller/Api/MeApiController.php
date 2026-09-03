<?php

namespace App\Controller\Api;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class MeApiController extends AbstractController
{
    #[Route('/api/me', name: 'app_api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user instanceof User) {
            return $this->json([
                'email' => $user->getUserIdentifier(),
                'roles' => $user->getRoles(),
            ]);
        }

        return $this->json([
            'id' => $user->getId(),
            'name' => $user->getName(),
            'firstName' => $user->getFirstName(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ]);
    }
}
