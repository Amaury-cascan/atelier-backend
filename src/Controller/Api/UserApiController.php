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
        $payload = json_decode($jsonContent, true);

        if (!is_array($payload)) {
            return $this->json(["error" => ["message" => "Requête invalide."]], Response::HTTP_BAD_REQUEST);
        }

        if (empty($payload['privacyPolicyAccepted'])) {
            return $this->json([
                "error" => ["message" => "Vous devez accepter la politique de confidentialité pour créer un compte."]
            ], Response::HTTP_BAD_REQUEST);
        }

        unset($payload['privacyPolicyAccepted'], $payload['privacyPolicyAcceptedAt']);

        try {
            $client = $serializer->deserialize(json_encode($payload), Client::class, 'json');
        } catch (NotEncodableValueException $exception) {
            return $this->json([ "error" => ["message" => $exception->getMessage()] ], Response::HTTP_BAD_REQUEST);
        }

        $client->setPrivacyPolicyAcceptedAt(new \DateTime());

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
            $this->emailService->sendWelcomeEmail($client->getEmail(), $client->getFirstName());
            $this->emailService->sendInfoWelcome($client->getName(), $client->getFirstName(), $client->getEmail());
        } catch (\Exception $e) {
            // Log l'erreur mais ne pas empêcher la création du compte
            //$this->logger->error('Error sending welcome email: ' . $e->getMessage());
            // Optionnel : informer l'utilisateur que l'email n'a pas pu être envoyé
             return $this->json([ 'message' => 'Account created successfully, but welcome email could not be sent.' ], Response::HTTP_CREATED);
        }

        return $this->json($client, Response::HTTP_CREATED);
    }
}