<?php

namespace App\Controller;

use App\Repository\UtilisateurRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/face')]
final class FaceLoginController extends AbstractController
{
    public function __construct(
        private readonly HttpClientInterface      $httpClient,
        private readonly UtilisateurRepository    $utilisateurRepository,
        private readonly TokenStorageInterface    $tokenStorage,
    ) {}

    #[Route('/login', name: 'face_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data          = json_decode($request->getContent(), true);
        $capturedImage = $data['image'] ?? null;

        if (!$capturedImage) {
            return new JsonResponse(['success' => false, 'message' => 'Image manquante.'], 400);
        }

        // Strip data-URL prefix  (e.g. "data:image/jpeg;base64,...")
        if (str_contains($capturedImage, ',')) {
            $capturedImage = explode(',', $capturedImage)[1];
        }

        // Collect all users that have a profile photo on disk
        $usersData = [];
        foreach ($this->utilisateurRepository->findAll() as $user) {
            if (!$user->getPdpUrl()) {
                continue;
            }
            $photoPath = $this->getParameter('kernel.project_dir')
                . '/public/uploads/pdp/'
                . $user->getPdpUrl();

            if (!file_exists($photoPath)) {
                continue;
            }

            $usersData[] = [
                'user_id'      => $user->getId(),
                'role'         => $user->getRole(),
                'photo_base64' => base64_encode(file_get_contents($photoPath)),
            ];
        }

        if (empty($usersData)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Aucun utilisateur avec photo de profil enregistrée.',
            ], 404);
        }

        // Call Python microservice
        try {
            $response = $this->httpClient->request('POST', 'http://127.0.0.1:5001/api/face-login', [
                'json'    => [
                    'captured_image' => $capturedImage,
                    'users'          => $usersData,
                ],
                'timeout' => 30,
            ]);
            $result = $response->toArray(false);
        } catch (\Exception) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Service de reconnaissance faciale indisponible. Utilisez email / mot de passe.',
            ], 503);
        }

        if (empty($result['success'])) {
            return new JsonResponse([
                'success' => false,
                'message' => $result['message'] ?? 'Visage non reconnu.',
            ]);
        }

        // Find the matched user
        $user = $this->utilisateurRepository->find((int) $result['user_id']);
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Utilisateur introuvable.'], 404);
        }

        // Programmatic Symfony login (session-based)
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $this->tokenStorage->setToken($token);
        $request->getSession()->set('_security_main', serialize($token));

        // Redirect based on role
        $redirect = $user->getRole() === 'adminm'
            ? $this->generateUrl('back_administrateur')
            : $this->generateUrl('front_home');

        return new JsonResponse([
            'success'    => true,
            'redirect'   => $redirect,
            'confidence' => $result['confidence'],
            'user'       => $user->getPrenom() . ' ' . $user->getNom(),
        ]);
    }
}
