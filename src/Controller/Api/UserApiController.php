<?php

namespace App\Controller\Api;

use App\Entity\Client;
use App\Service\EmailService;
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
    private EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

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
            $client = $serializer->deserialize($jsonContent, Client::class, 'json');
        } catch (NotEncodableValueException $exception) {
            return $this->json([ "error" => ["message" => $exception->getMessage()] ], Response::HTTP_BAD_REQUEST);
        }

        $errors = $validator->validate($client);
        if (count($errors) > 0) {
            $dataErrors = [];
            foreach ($errors as $error) {
                $dataErrors[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(["error" => ["message" => $dataErrors]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($client->getPassword()) {
            $hashedPassword = $passwordHasher->hashPassword($client, $client->getPassword());
            $client->setPassword($hashedPassword);
        }

        $entityManager->persist($client);
        $entityManager->flush();

        try {
            // Utilisation du nouveau service EmailService
            $this->emailService->sendWelcomeEmail("amaury.cascan@hotmail.fr", $client->getFirstName());
        } catch (\Exception $e) {
            // Log l'erreur mais ne pas empêcher la création du compte
            //$this->logger->error('Error sending welcome email: ' . $e->getMessage());
            // Optionnel : informer l'utilisateur que l'email n'a pas pu être envoyé
             return $this->json([ 'message' => 'Account created successfully, but welcome email could not be sent.' ], Response::HTTP_CREATED);
        }

        return $this->json($client, Response::HTTP_CREATED);
    }
}