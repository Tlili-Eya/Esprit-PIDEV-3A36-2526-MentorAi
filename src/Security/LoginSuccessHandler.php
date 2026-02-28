<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    private $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): RedirectResponse
    {
        // Get user roles
        $roles = $token->getUser()->getRoles();

        // Check roles and redirect accordingly
        
        // ROLE_ADMIN → backoffice
        if (in_array('ROLE_ADMIN', $roles, true)) {
            return new RedirectResponse($this->router->generate('back_home'));
        }

        // ROLE_ADMINM or ROLE_ADMINISTRATEUR → dashboard admin
        if (in_array('ROLE_ADMINM', $roles, true) || in_array('ROLE_ADMINISTRATEUR', $roles, true)) {
            return new RedirectResponse($this->router->generate('back_administrateur'));
        }

        // ROLE_ENSEIGNANT → dashboard enseignant
        if (in_array('ROLE_ENSEIGNANT', $roles, true)) {
            return new RedirectResponse($this->router->generate('app_enseignant_dashboard'));
        }

        // Default → accueil
        return new RedirectResponse($this->router->generate('app_home'));
    }
}
