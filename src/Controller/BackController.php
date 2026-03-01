<?php

namespace App\Controller;

use App\Repository\UtilisateurRepository;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin', name: 'back_')]
final class BackController extends AbstractController
{
    #[Route('/dashboard', name: 'dashboard')]
    public function dashboard(): Response
    {
        return $this->render('back/dashboard.html.twig');
    }

    #[Route('/about', name: 'about')]
    public function about(): Response
    {
        return $this->render('back/about.html.twig');
    }

    #[Route('/course-details', name: 'course_details')]
    public function courseDetails(): Response
    {
        return $this->render('back/course-details.html.twig');
    }

    #[Route('/instructor-profile', name: 'instructor_profile')]
    public function instructorProfile(): Response
    {
        return $this->render('back/instructor-profile.html.twig');
    }

    #[Route('/pricing', name: 'pricing')]
    public function pricing(): Response
    {
        $transactions = [];

        /** @var string|null $stripeSecretKey */
        $stripeSecretKey = $this->getParameter('stripe_secret_key');

        if (!$stripeSecretKey) {
            $this->addFlash('warning', 'Clé secrète Stripe non configurée.');
            return $this->render('back/pricing.html.twig', [
                'transactions' => $transactions,
            ]);
        }

        try {
            Stripe::setApiKey($stripeSecretKey);

            $paymentIntents = PaymentIntent::all(['limit' => 100]);

            foreach ($paymentIntents->data as $intent) {
                $transactions[] = [
                    'id' => $intent->id,
                    'amount' => $intent->amount / 100,
                    'currency' => strtoupper($intent->currency),
                    'status' => $intent->status,
                    'email' => $intent->receipt_email
                        ?? ($intent->customer ? 'Customer ID: ' . $intent->customer : 'N/A'),
                    'created' => (new \DateTimeImmutable())
                        ->setTimestamp($intent->created)
                        ->format('d/m/Y H:i'),
                    'description' => $intent->description ?? 'No description',
                ];
            }
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur Stripe : ' . $e->getMessage());
        }

        return $this->render('back/pricing.html.twig', [
            'transactions' => $transactions,
        ]);
    }

    #[Route('/blog', name: 'blog')]
    public function blog(): Response
    {
        return $this->render('back/blog.html.twig');
    }

    #[Route('/blog-details', name: 'blog_details')]
    public function blogDetails(): Response
    {
        return $this->render('back/blog-details.html.twig');
    }

    #[Route('/contact', name: 'contact')]
    public function contact(): Response
    {
        return $this->render('back/contact.html.twig');
    }

    #[Route('/administrateur', name: 'administrateur')]
    public function administrateur(UtilisateurRepository $utilisateurRepository): Response
    {
        return $this->render('back/administrateur.html.twig', [
            'utilisateurs' => $utilisateurRepository->findAll(),
        ]);
    }
}