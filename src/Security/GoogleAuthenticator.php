<?php

namespace App\Security;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class GoogleAuthenticator extends OAuth2Authenticator
{
    private $clientRegistry;
    private $entityManager;
    private $router;

    public function __construct(ClientRegistry $clientRegistry, EntityManagerInterface $entityManager, RouterInterface $router)
    {
        $this->clientRegistry = $clientRegistry;
        $this->entityManager = $entityManager;
        $this->router = $router;
    }

    public function supports(Request $request): ?bool
    {
        // continue ONLY if the current ROUTE matches the check ROUTE
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function() use ($accessToken, $client, $request) {
                /** @var \League\OAuth2\Client\Provider\GoogleUser $googleUser */
                $googleUser = $client->fetchUserFromToken($accessToken);

                $email = $googleUser->getEmail();

                // 1) Have they logged in with Google before? (Not tracking google ID here, just email)
                // 2) Do we have a matching user by email?
                $user = $this->entityManager->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);

                // Check environment/context to see if we are in "register" mode
                $session = $request->getSession();
                $authAction = $session->get('_google_auth_action');
                $session->remove('_google_auth_action'); // Clear it immediately

                if ($user && $authAction === 'register') {
                    // Registration mode + User exists -> ERROR
                    throw new \Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException(
                        'Ce compte existe déjà. Veuillez vous connecter.'
                    );
                }

                if (!$user) {
                    // Create the user
                    $user = new Utilisateur();
                    $user->setEmail($email);
                    $user->setPrenom($googleUser->getFirstName());
                    $user->setNom($googleUser->getLastName());
                    // Set default values as requested
                    $user->setRole('etudiant');
                    $user->setStatus('actif');
                    $user->setPdpUrl($googleUser->getAvatar());
                    $user->setDateInscription(new \DateTime());
                    // Random password as they log in with Google
                    $user->setMdp(bin2hex(random_bytes(16)));

                    $this->entityManager->persist($user);
                    $this->entityManager->flush();
                }

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // DEBUG: Verify where we are going
        // dd('DEBUG: GoogleAuthenticator success! Redirecting to app_profil', $this->router->generate('app_profil'));
        
        // Redirect to profile page as requested
        return new RedirectResponse($this->router->generate('app_profil'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $message = strtr($exception->getMessageKey(), $exception->getMessageData());

        return new Response($message, Response::HTTP_FORBIDDEN);
    }
}
