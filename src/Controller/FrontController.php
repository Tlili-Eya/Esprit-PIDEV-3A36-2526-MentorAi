<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
#[Route('/', name: 'front_')]
class FrontController extends AbstractController
{
    #[Route('affiche', name: 'affiche')]
    public function affiche(): Response
    {
        return $this->render('front/affiche.html.twig');
    }

    #[Route('home', name: 'home')]
    public function home(): Response
    {
        return $this->render('front/home.html.twig');
    }

    #[Route('about', name: 'about')]
    public function about(): Response
    {
        return $this->render('front/about.html.twig');
    }


    #[Route('course-details', name: 'course_details')]
    public function courseDetails(): Response
    {
        return $this->render('front/course-details.html.twig');
    }

    #[Route('instructor-profile', name: 'instructor_profile')]
    public function instructorProfile(): Response
    {
        return $this->render('front/instructor-profile.html.twig');
    }

    

    #[Route('pricing', name: 'pricing')]
    public function pricing(): Response
    {
        return $this->render('front/pricing.html.twig');
    }

    #[Route('checkout/{plan}', name: 'checkout')]
    public function checkout(string $plan, UrlGeneratorInterface $generator): Response
    {
        \Stripe\Stripe::setApiKey($this->getParameter('stripe_secret_key'));

        $price = 7500;
        if ($plan === 'business') {
            $price = 13500;
        } elseif ($plan === 'plus') {
            $price = 7500;
        }

        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => ucfirst($plan) . ' Plan Subscription',
                    ],
                    'unit_amount' => $price,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => $generator->generate('front_checkout_success', [], UrlGeneratorInterface::ABSOLUTE_URL) . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $generator->generate('front_pricing', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);

        return $this->redirect($session->url, 303);
    }

    #[Route('checkout-success', name: 'checkout_success')]
    public function checkoutSuccess(Request $request, MailerInterface $mailer): Response
    {
        $sessionId = $request->query->get('session_id');

        if ($sessionId) {
            \Stripe\Stripe::setApiKey($this->getParameter('stripe_secret_key'));
            
            try {
                $session = \Stripe\Checkout\Session::retrieve($sessionId);
                $customerEmail = $session->customer_details->email;
                $amountTotal = $session->amount_total / 100;

                if ($customerEmail) {
                    $email = (new Email())
                        ->from('MentorAI <hejerh666@gmail.com>')
                        ->to($customerEmail)
                        ->subject('Confirmation de votre paiement')
                        ->html('<p>Merci beaucoup pour votre paiement de ' . $amountTotal . ' dt pour l\'abonnement.</p><p>Votre transaction a &eacute;t&eacute; effectu&eacute;e avec succ&egrave;s.</p>');

                    $mailer->send($email);
                }
            } catch (\Exception $e) {
            }
        }

        return $this->render('front/checkout_success.html.twig');
    }

    #[Route('privacy', name: 'privacy')]
    public function privacy(): Response
    {
        return $this->render('front/privacy.html.twig');
    }

    #[Route('terms', name: 'terms')]
    public function terms(): Response
    {
        return $this->render('front/terms.html.twig');
    }

    #[Route('blog', name: 'blog')]
    public function blog(): Response
    {
        return $this->render('front/blog.html.twig');
    }

    #[Route('blog-details', name: 'blog_details')]
    public function blogDetails(): Response
    {
        return $this->render('front/blog-details.html.twig');
    }

    #[Route('contact', name: 'contact')]
    public function contact(): Response
    {
        return $this->render('front/contact.html.twig');
    }

    #[Route('enroll', name: 'enroll')]
    public function enroll(): Response
    {
        return $this->render('front/enroll.html.twig');
    }

    #[Route('starter', name: 'starter')]
    public function starter(): Response
    {
        return $this->render('front/starter-page.html.twig');
    }

    #[Route('404', name: '404')]
    public function error404(): Response
    {
        return $this->render('front/404.html.twig');
    
    }

    #[Route('ai-embauche', name: 'ai_embauche')]
    public function aiEmbauche(): Response
    {
        return $this->render('front/ai-embauche.html.twig');
    
    }

    #[Route('jeux', name: 'jeux')]
    public function jeux(): Response
    {
        return $this->render('front/jeux.html.twig');
    
    }

  #[Route('preferences', name: 'preferences')]
    public function preferences(): Response
    {
        return $this->render('front/preferences.html.twig');
    }




}
