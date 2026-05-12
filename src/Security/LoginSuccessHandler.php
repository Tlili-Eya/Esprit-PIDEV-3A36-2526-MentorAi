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

        // Specific redirection for prof1@esprit.tn
        if ($token->getUser()->getUserIdentifier() === 'prof1@esprit.tn') {
            return new RedirectResponse($this->router->generate('app_enseignant_dashboard'));
        }

        // Check roles and redirect accordingly
        
        // ROLE_ADMIN -> back_home
        if (in_array('ROLE_ADMIN', $roles, true)) {
             return new RedirectResponse($this->router->generate('back_home'));
        }

        // ROLE_ADMINISTRATEUR -> back_administrateur
        if (in_array('ROLE_ADMINISTRATEUR', $roles, true)) {
            return new RedirectResponse($this->router->generate('back_administrateur'));
        }

        // ROLE_ADMINM -> app_admin_dashboard
        if (in_array('ROLE_ADMINM', $roles, true)) {
            return new RedirectResponse($this->router->generate('app_admin_dashboard'));
        }

        // ROLE_ENSEIGNANT -> app_enseignant_dashboard
        if (in_array('ROLE_ENSEIGNANT', $roles, true)) {
            return new RedirectResponse($this->router->generate('app_enseignant_dashboard'));
        }

        // Default behavior for ROLE_ETUDIANT and others
        return new RedirectResponse($this->router->generate('front_home'));
    }
}
