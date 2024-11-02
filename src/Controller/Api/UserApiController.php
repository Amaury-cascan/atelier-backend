<?php

namespace App\Controller\Api;

use App\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

class UserApiController extends AbstractController
{
    #[Route('/api/signup', name: 'app_api_client_add', methods: ['POST'])]
    public function add(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $jsonContent = $request->getContent();

        try {
            // Désérialiser le JSON dans l'entité Client
            $client = $serializer->deserialize($jsonContent, Client::class, 'json');
        } catch (NotEncodableValueException $exception) {
            return $this->json([
                "error" => ["message" => $exception->getMessage()]
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validation des données de l'entité Client
        $errors = $validator->validate($client);
        if (count($errors) > 0) {
            $dataErrors = [];
            foreach ($errors as $error) {
                $dataErrors[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(["error" => ["message" => $dataErrors]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Hachage du mot de passe
        if ($client->getPassword()) {
            $hashedPassword = $passwordHasher->hashPassword($client, $client->getPassword());
            $client->setPassword($hashedPassword);
        }

        // Enregistrer le client en base de données
        $entityManager->persist($client);
        $entityManager->flush();

        return $this->json($client, Response::HTTP_CREATED);
    }
}
