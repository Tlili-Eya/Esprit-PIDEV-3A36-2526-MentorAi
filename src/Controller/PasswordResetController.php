<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PasswordResetController extends AbstractController
{
    /**
     * Étape 1 : Formulaire de demande de réinitialisation (saisie email)
     */
    #[Route('/reset-password', name: 'app_forgot_password')]
    public function request(Request $request, UtilisateurRepository $userRepository, EntityManagerInterface $em, MailerInterface $mailer): Response
    {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $user = $userRepository->findOneBy(['email.value' => $email]);

            if ($user) {
                // Générer un token unique et sécurisé
                $token = bin2hex(random_bytes(32));
                
                // Définir le token et son expiration (1 heure)
                $user->setResetToken($token);
                $expiresAt = new \DateTime('+1 hour');
                $user->updateResetTokenExpiresAt($expiresAt);
                
                $em->flush();

                // Générer l'URL absolue de réinitialisation
                $resetUrl = $this->generateUrl('app_reset_password', 
                    ['token' => $token], 
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                // Envoyer l'email
                $emailMessage = (new Email())
                    ->from('noreply@mentorai.com')
                    ->to($user->getEmail())
                    ->subject('Réinitialisation de votre mot de passe - MentorAI')
                    ->html($this->renderView('emails/reset_password.html.twig', [
                        'user' => $user,
                        'resetUrl' => $resetUrl,
                        'expiresAt' => $expiresAt,
                    ]));

                try {
                    $mailer->send($emailMessage);
                    $this->addFlash('success', 'Un email de réinitialisation a été envoyé. Vérifiez votre boîte (y compris spam/promotions).');
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Erreur lors de l\'envoi de l\'email : ' . $e->getMessage());
                }
            } else {
                // Sécurité : même message même si l'email n'existe pas
                $this->addFlash('success', 'Si cet email existe dans notre système, vous recevrez un lien de réinitialisation.');
            }

            return $this->redirectToRoute('app_forgot_password');
        }

        return $this->render('security/forgot_password.html.twig');
    }

    /**
     * Étape 2 : Formulaire de réinitialisation (saisie nouveau mot de passe)
     */
    #[Route('/reset-password/{token}', name: 'app_reset_password')]
    public function reset(
        string $token,
        Request $request,
        UtilisateurRepository $userRepository,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response
    {
        $user = $userRepository->findOneBy(['resetToken' => $token]);

        if (!$user) {
            $this->addFlash('error', 'Lien invalide ou expiré.');
            return $this->redirectToRoute('app_forgot_password');
        }

        // Vérifier l'expiration
        if (!$user->isResetTokenValid()) {
            $this->addFlash('error', 'Ce lien a expiré. Veuillez refaire une demande.');
            return $this->redirectToRoute('app_forgot_password');
        }

        // Token depuis POST (si soumis) pour sécurité
        $submittedToken = $request->request->get('token', $token);
        if ($request->isMethod('POST') && $submittedToken !== $token) {
            $this->addFlash('error', 'Token invalide dans la soumission.');
            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            $newPassword = $request->request->get('password');
            $confirmPassword = $request->request->get('confirm_password');

            if (empty($newPassword) || strlen($newPassword) < 6) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 6 caractères.');
            } elseif ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'Les deux mots de passe ne correspondent pas.');
            } else {
                // Hashage et sauvegarde du nouveau mot de passe
                $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
                $user->setMdp($hashedPassword);
                
                // Nettoyage du token
                $user->setResetToken(null);
                $user->updateResetTokenExpiresAt(null);
                
                $em->flush();

                $this->addFlash('success', 'Votre mot de passe a été modifié avec succès ! Connectez-vous maintenant.');
                return $this->redirectToRoute('app_login');  // ← REDIRECTION VERS TA PAGE LOGIN
            }
        }

        return $this->render('security/reset_password.html.twig', [
            'token' => $token,
        ]);
    }
}