<?php

namespace App\Security;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\UserBadge;

class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    private UrlGeneratorInterface $urlGenerator;
    private UserRepository $userRepository;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(UrlGeneratorInterface $urlGenerator, UserRepository $userRepository)
    {
        $this->urlGenerator = $urlGenerator;
        $this->userRepository = $userRepository;
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('_username');
        $password = $request->request->get('_password');
        // Vérifie si l'utilisateur existe dans la base de données
        $user = $this->userRepository->findOneBy(['email' => $email]);
        dd($this->userRepository->findAll());
        if (!$user) {
            // Si l'email n'est pas trouvé, on renvoie une exception personnalisée
            throw new CustomUserMessageAuthenticationException('Cet email est incorrect.');
        }

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        // Rediriger vers une URL spécifique après une connexion réussie
        $redirectUrl = $this->urlGenerator->generate('app_dashboard_index');
        return new JsonResponse(['message' => 'Connexion réussie !'], Response::HTTP_OK);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        // On vérifie le type d'erreur pour personnaliser le message
        $error = $exception instanceof CustomUserMessageAuthenticationException
            ? $exception->getMessage()
            : 'Mot de passe incorrect.';

        return new JsonResponse(['error' => $error], Response::HTTP_UNAUTHORIZED);
    }

    protected function getLoginUrl(Request $request): string
    {
        // Retourne l'URL de connexion pour l'application
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
