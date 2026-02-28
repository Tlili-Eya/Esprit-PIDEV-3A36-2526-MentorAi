<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\ExpressionLanguage\Expression;

#[Route('/front')]
class RedirectController extends AbstractController
{
    #[Route('/admin', name: 'front_admin')]
    // Allow ROLE_ADMINM or ROLE_ADMINISTRATEUR
    #[IsGranted(new Expression("is_granted('ROLE_ADMINISTRATEUR') or is_granted('ROLE_ADMINM')"))] 
    #[IsGranted(new Expression("is_granted('ROLE_ADMINM') or is_granted('ROLE_ADMINISTRATEUR')"))] 
    public function admin(): Response
    {
        return $this->render('front/admin.html.twig');
    }

        #[Route('/adminm', name: 'front_adminm')]
    // Allow ROLE_ADMINM or ROLE_ADMINISTRATEUR
    #[IsGranted(new Expression("is_granted('ROLE_ADMINM') or is_granted('ROLE_ADMINISTRATEUR')"))] 
    public function adminm(): Response
    {
        return $this->render('front/admin/dashboard.html.twig');
    }

    #[Route('/enseignant', name: 'front_enseignant')]
    #[IsGranted('ROLE_ENSEIGNANT')]
    public function enseignant(): Response
    {
        return $this->render('front/enseignant/dashboard.html.twig');
        return $this->render('front/enseignant.html.twig');
    }
}
