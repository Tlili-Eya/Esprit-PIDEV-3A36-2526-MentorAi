<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Si l'utilisateur est déjà connecté → redirection selon son rôle
        if ($this->getUser()) {
            return $this->redirectToAppropriateDashboard();
        }

        // Récupérer l'erreur de connexion s'il y en a une
        $error = $authenticationUtils->getLastAuthenticationError();
        
        // Dernier nom d'utilisateur saisi
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'hCaptchaSiteKey' => $_ENV['HCAPTCHA_SITE_KEY'] ?? '35e6c3cc-8104-4422-8f80-f88253f465ea', // Fallback if env not loaded (though it should be)
        ]);
    }

    /**
     * Redirige l'utilisateur vers le bon dashboard selon son rôle
     */
    private function redirectToAppropriateDashboard(): Response
    {
        // Récupère l'utilisateur connecté
        $user = $this->getUser();

        // Sécurité supplémentaire (normalement impossible ici, mais au cas où)
        if (!$user) {
            return $this->redirectToRoute('front_home');
        }

        // Ordre important : tester les rôles du plus privilégié au moins privilégié
        if ($this->isGranted('ROLE_ADMIN')) {
            // → À créer si tu as un dashboard admin global
            return $this->redirectToRoute('back_home'); 
            // Ou : return $this->redirectToRoute('back_administrateur');
        }

        if ($this->isGranted('ROLE_ADMINM')) {
            return $this->redirectToRoute('adminm_dashboard');
        }

        if ($this->isGranted('ROLE_ENSEIGNANT')) {
            return $this->redirectToRoute('enseignant_dashboard');
        }

        // Par défaut : étudiant ou tout autre rôle
        return $this->redirectToRoute('front_home');
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        // Cette méthode peut rester vide - elle sera interceptée par la clé logout du firewall
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/connect/google', name: 'connect_google')]
    public function connectGoogle(\KnpU\OAuth2ClientBundle\Client\ClientRegistry $clientRegistry): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $client = $clientRegistry->getClient('google');
        
        // Log the redirect URI and server info for debugging
        $redirectUrl = $this->generateUrl('connect_google_check', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);
        $logMessage = sprintf(
            "[%s] Google Auth Start. \nGenerated Redirect URI: %s\nSERVER_NAME: %s\nSERVER_PORT: %s\nHTTPS: %s\nREQUEST_SCHEME: %s\n\n",
            date('H:i:s'),
            $redirectUrl,
            $_SERVER['SERVER_NAME'] ?? 'N/A',
            $_SERVER['SERVER_PORT'] ?? 'N/A',
            $_SERVER['HTTPS'] ?? 'off',
            $_SERVER['REQUEST_SCHEME'] ?? 'N/A'
        );
        file_put_contents(__DIR__ . '/../../var/log/mentor_debug.log', $logMessage, FILE_APPEND);

        return $client->redirect(['email', 'profile'], []);
    }

    #[Route('/connect/google/register', name: 'connect_google_register')]
    public function connectGoogleRegister(\KnpU\OAuth2ClientBundle\Client\ClientRegistry $clientRegistry, \Symfony\Component\HttpFoundation\Request $request): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        // Set a session variable to indicate we are in registration mode
        $request->getSession()->set('_google_auth_action', 'register');

        return $clientRegistry
            ->getClient('google')
            ->redirect(['email', 'profile'], []);
    }

    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectGoogleCheck()
    {
        // laissé vide car géré par le GoogleAuthenticator
    }
}