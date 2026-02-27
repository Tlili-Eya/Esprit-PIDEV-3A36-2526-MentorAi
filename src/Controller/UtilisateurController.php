<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Form\UtilisateurType;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/utilisateur')]
final class UtilisateurController extends AbstractController
{
    public function __construct(
        private readonly UtilisateurRepository $utilisateurRepository,
    ) {}

    /**
     * Returns true if another user already owns a photo with the same content.
     * Uses SHA-256 file hashing so renaming a file doesn't bypass the check.
     *
     * @param \Symfony\Component\HttpFoundation\File\UploadedFile $file
     * @param int|null $excludeUserId  Skip this user's current photo (for edit/profil)
     */
    private function isPhotoDuplicate(
        \Symfony\Component\HttpFoundation\File\UploadedFile $file,
        ?int $excludeUserId = null
    ): bool {
        $newHash   = hash_file('sha256', $file->getPathname());
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/pdp';

        foreach ($this->utilisateurRepository->findAll() as $user) {
            if ($excludeUserId !== null && $user->getId() === $excludeUserId) {
                continue;
            }
            if (!$user->getPdpUrl()) {
                continue;
            }
            $path = $uploadDir . '/' . $user->getPdpUrl();
            if (file_exists($path) && hash_file('sha256', $path) === $newHash) {
                return true;
            }
        }

        return false;
    }

    #[Route(name: 'app_utilisateur_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('utilisateur/index.html.twig', [
            'utilisateurs' => $this->utilisateurRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_utilisateur_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response
    {
        $utilisateur = new Utilisateur();
        $form = $this->createForm(UtilisateurType::class, $utilisateur);
        $form->handleRequest($request);

        // NUCLEAR LOGGING
        if ($request->isMethod('POST')) {
            file_put_contents(
                __DIR__ . '/../../var/log/mentor_debug.log',
                sprintf("[%s] POST /new detected. Secret: %s. IP: %s\n", date('H:i:s'), $request->request->get('submission_secret'), $request->getClientIp()),
                FILE_APPEND
            );
        }

        if ($request->request->get('submission_secret') === 'STRICT_MANUAL_v4_GOLD' && $form->isSubmitted() && $form->isValid()) {
            file_put_contents(__DIR__ . '/../../var/log/mentor_debug.log', sprintf("[%s] VALIDATION SUCCESS. Persisting user...\n", date('H:i:s')), FILE_APPEND);
            
            $plainPassword = $form->get('mdp')->getData();
            
            if ($plainPassword) {
                $hashedPassword = $passwordHasher->hashPassword($utilisateur, $plainPassword);
                $utilisateur->setMdp($hashedPassword);
            } else {
                file_put_contents(__DIR__ . '/../../var/log/mentor_debug.log', sprintf("[%s] ERROR: Password missing\n", date('H:i:s')), FILE_APPEND);
                $this->addFlash('error', 'Le mot de passe est obligatoire pour créer un compte.');
                return $this->redirectToRoute('back_administrateur', [], Response::HTTP_SEE_OTHER);
            }

            if (!$utilisateur->getDateInscription()) {
                $utilisateur->setDateInscription(new \DateTime());
            }

            if (!$utilisateur->getRole()) {
                $utilisateur->setRole('etudiant');
            }

            $photoFile = $form->get('pdp_url')->getData();
            if ($photoFile) {
                if ($this->isPhotoDuplicate($photoFile)) {
                    $this->addFlash('error', 'Cette photo de profil est déjà utilisée par un autre utilisateur.');
                    return $this->redirectToRoute('back_administrateur', [], Response::HTTP_SEE_OTHER);
                }
                $newFilename = uniqid() . '.' . $photoFile->guessExtension();
                $photoFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads/pdp',
                    $newFilename
                );
                $utilisateur->setPdpUrl($newFilename);
            }

            $entityManager->persist($utilisateur);
            $entityManager->flush();
            file_put_contents(__DIR__ . '/../../var/log/mentor_debug.log', sprintf("[%s] FLUSH COMPLETED. User ID: %s\n", date('H:i:s'), $utilisateur->getId()), FILE_APPEND);

            // ✅ MODIFIÉ: Message de succès et redirection vers administrateur
            $this->addFlash('success', 'Instructor created successfully!');

            return $this->redirectToRoute('back_administrateur', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('utilisateur/new.html.twig', [
            'utilisateur' => $utilisateur,
            'form' => $form,
        ]);
    }

    // ✅ CRITIQUE: /profil DOIT ÊTRE AVANT /{id} pour éviter les conflits de routes
    #[Route('/profil', name: 'app_profil', methods: ['GET', 'POST'])]
    public function profil(
        Request $request, 
        EntityManagerInterface $entityManager, 
        UserPasswordHasherInterface $passwordHasher,
        \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface $tokenStorage
    ): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Créer le formulaire de profil (différent de UtilisateurType pour la sécurité)
        $form = $this->createForm(\App\Form\ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hacher le mot de passe seulement s'il est modifié
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                $user->setMdp($hashedPassword);
            }

            $photoFile = $form->get('pdp_url')->getData();
            if ($photoFile) {
                if ($this->isPhotoDuplicate($photoFile, $user->getId())) {
                    $this->addFlash('error', 'Cette photo de profil est déjà utilisée par un autre utilisateur.');
                    return $this->redirectToRoute('app_profil', [], Response::HTTP_SEE_OTHER);
                }
                $newFilename = uniqid() . '.' . $photoFile->guessExtension();
                $photoFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads/pdp',
                    $newFilename
                );
                $user->setPdpUrl($newFilename);
            }

            $entityManager->flush();

            // Rafraîchir le token de sécurité pour mettre à jour la session
            $token = new \Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($user, 'main', $user->getRoles());
            $tokenStorage->setToken($token);

            $this->addFlash('success', 'Profil mis à jour avec succès !');

            return $this->redirectToRoute('app_profil', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('utilisateur/profil.html.twig', [
            'utilisateur' => $user,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_utilisateur_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(
        Request $request, 
        Utilisateur $utilisateur, 
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response
    {
        $form = $this->createForm(UtilisateurType::class, $utilisateur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('mdp')->getData();

            if ($plainPassword) {
                $hashedPassword = $passwordHasher->hashPassword($utilisateur, $plainPassword);
                $utilisateur->setMdp($hashedPassword);
            }

            $photoFile = $form->get('pdp_url')->getData();
            if ($photoFile) {
                if ($this->isPhotoDuplicate($photoFile, $utilisateur->getId())) {
                    $msg = 'Cette photo de profil est déjà utilisée par un autre utilisateur.';
                    if ($request->isXmlHttpRequest()) {
                        return new JsonResponse(['status' => 'error', 'message' => $msg], 422);
                    }
                    $this->addFlash('error', $msg);
                    return $this->redirectToRoute('back_administrateur', [], Response::HTTP_SEE_OTHER);
                }
                $newFilename = uniqid() . '.' . $photoFile->guessExtension();
                $photoFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads/pdp',
                    $newFilename
                );
                $utilisateur->setPdpUrl($newFilename);
            }

            $entityManager->flush();
            $this->addFlash('success', 'Instructor updated successfully!');

            // ✅ Si c'est une requête AJAX (depuis le modal), on renvoie du JSON
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['status' => 'success']);
            }

            // ✅ Sinon redirection classique
            return $this->redirectToRoute('back_administrateur', [], Response::HTTP_SEE_OTHER);
        }

        // Si le formulaire est soumis mais invalide en AJAX
        if ($request->isXmlHttpRequest() && $form->isSubmitted() && !$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }

            return new JsonResponse([
                'status' => 'error',
                'message' => implode(' ', $errors) ?: 'Validation error. Please check your data.'
            ], 422);
        }

        return $this->render('utilisateur/edit.html.twig', [
            'utilisateur' => $utilisateur,
            'form' => $form,
        ]);
    }

    // ✅ ROUTE DELETE SÉPARÉE avec validation CSRF
    #[Route('/{id}/delete', name: 'app_utilisateur_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Utilisateur $utilisateur, EntityManagerInterface $entityManager): Response
    {
        // ✅ Vérification du token CSRF
        $token = $request->request->get('_token');
        
        if ($this->isCsrfTokenValid('delete'.$utilisateur->getId(), $token)) {
            // ✅ MODIFIÉ: Changement de statut textuel
            $utilisateur->setStatus('desactiver');
            $entityManager->flush();

            // Si l'utilisateur se supprime lui-même, on le déconnecte
            if ($this->getUser() === $utilisateur) {
                $request->getSession()->invalidate();
                $this->container->get('security.token_storage')->setToken(null);
                return $this->redirectToRoute('app_login');
            }

            $this->addFlash('success', 'Compte désactivé avec succès.');
        } else {
            $this->addFlash('error', 'Security error. Please try again.');
        }

        return $this->redirectToRoute('back_administrateur', [], Response::HTTP_SEE_OTHER);
    }

    // ✅ ROUTE GÉNÉRIQUE /{id} DOIT ÊTRE EN DERNIER (sinon elle capture tout)
    #[Route('/{id}', name: 'app_utilisateur_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Utilisateur $utilisateur): Response
    {
        return $this->render('utilisateur/show.html.twig', [
            'utilisateur' => $utilisateur,
        ]);
    }
}
