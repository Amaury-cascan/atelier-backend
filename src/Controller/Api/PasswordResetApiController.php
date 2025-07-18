<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/password-reset', name: 'api_password_reset_')]
class PasswordResetApiController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private EmailService $emailService,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator
    ) {}

    #[Route('/request', name: 'request', methods: ['POST'])]
    public function requestReset(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            // Validation des données
            if (!isset($data['email']) || empty($data['email'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'L\'adresse email est requise.'
                ], 400);
            }

            $email = trim($data['email']);

            // Validation du format email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Format d\'email invalide.'
                ], 400);
            }

            // Rechercher l'utilisateur
            $user = $this->userRepository->findOneBy(['email' => $email]);

            // Pour des raisons de sécurité, on retourne toujours le même message
            // même si l'utilisateur n'existe pas
            if (!$user) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Si cette adresse email est associée à un compte, vous recevrez un email de réinitialisation.'
                ]);
            }

            // Générer un token unique et sécurisé
            $resetToken = bin2hex(random_bytes(32));
            
            // Le token expire dans 1 heure
            $expiresAt = new \DateTime();
            $expiresAt->add(new \DateInterval('PT1H'));

            // Sauvegarder le token
            $user->setResetToken($resetToken);
            $user->setResetTokenExpiresAt($expiresAt);
            
            $this->entityManager->flush();

            // Envoyer l'email
            $frontendUrl = $data['frontend_url'] ?? null;
            $username = $user->getFirstName() . ' ' . $user->getName();
            
            $this->emailService->sendPasswordResetEmail(
                $user->getEmail(),
                $username,
                $resetToken,
                $frontendUrl
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Si cette adresse email est associée à un compte, vous recevrez un email de réinitialisation.'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Une erreur s\'est produite lors de l\'envoi de l\'email.'
            ], 500);
        }
    }

    #[Route('/confirm', name: 'confirm', methods: ['POST'])]
    public function confirmReset(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            // Validation des données
            if (!isset($data['token']) || empty($data['token'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Le token de réinitialisation est requis.'
                ], 400);
            }

            if (!isset($data['password']) || empty($data['password'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Le nouveau mot de passe est requis.'
                ], 400);
            }

            $token = $data['token'];
            $newPassword = $data['password'];

            // Validation de la force du mot de passe
            if (strlen($newPassword) < 8) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Le mot de passe doit contenir au moins 8 caractères.'
                ], 400);
            }

            // Rechercher l'utilisateur par token
            $user = $this->userRepository->findOneBy(['resetToken' => $token]);

            if (!$user) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Token de réinitialisation invalide ou expiré.'
                ], 400);
            }

            // Vérifier que le token n'a pas expiré
            if (!$user->isResetTokenValid()) {
                // Nettoyer le token expiré
                $user->setResetToken(null);
                $user->setResetTokenExpiresAt(null);
                $this->entityManager->flush();

                return new JsonResponse([
                    'success' => false,
                    'message' => 'Token de réinitialisation invalide ou expiré.'
                ], 400);
            }

            // Hasher le nouveau mot de passe
            $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
            
            // Mettre à jour le mot de passe et nettoyer le token
            $user->setPassword($hashedPassword);
            $user->setResetToken(null);
            $user->setResetTokenExpiresAt(null);

            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Votre mot de passe a été modifié avec succès.'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Une erreur s\'est produite lors de la réinitialisation du mot de passe.'
            ], 500);
        }
    }

    #[Route('/verify-token', name: 'verify_token', methods: ['POST'])]
    public function verifyToken(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['token']) || empty($data['token'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Le token est requis.'
                ], 400);
            }

            $token = $data['token'];
            $user = $this->userRepository->findOneBy(['resetToken' => $token]);

            if (!$user || !$user->isResetTokenValid()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Token invalide ou expiré.'
                ], 400);
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'Token valide.',
                'data' => [
                    'email' => $user->getEmail(),
                    'expires_at' => $user->getResetTokenExpiresAt()->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la vérification du token.'
            ], 500);
        }
    }
} 