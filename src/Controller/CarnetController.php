<?php

namespace App\Controller;

use App\Entity\Carnet;
use App\Repository\CarnetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class CarnetController extends AbstractController
{
    #[Route('/blog', name: 'front_blog', methods: ['GET'])]
    public function blog(Request $request, CarnetRepository $carnetRepository): Response
    {
        $user = $this->getUser();
        $criteria = [];
        if ($user) {
            $criteria = ['Utilisateurs' => $user];
        }

        $notes = $carnetRepository->findBy(
            $criteria,
            ['dateModification' => 'DESC']
        );

        $payload = [];
        foreach ($notes as $note) {
            $created = $note->getDateCreation();
            $modified = $note->getDateModification();
            $displayDate = $modified ?? $created;

            $payload[] = [
                'id' => $note->getId(),
                'titre' => $note->getTitre(),
                'contenu' => $note->getContenu(),
                'date_creation' => $created?->format('Y-m-d H:i'),
                'date_modification' => $modified?->format('Y-m-d H:i'),
                'date_affichee' => $displayDate?->format('Y-m-d H:i'),
            ];
        }

        $selectedNote = null;
        $mode = (string) $request->query->get('mode', 'list');
        $noteId = $request->query->get('id');

        if ($noteId) {
            foreach ($payload as $note) {
                if ((string) $note['id'] === (string) $noteId) {
                    $selectedNote = $note;
                    $mode = $mode === 'edit' ? 'edit' : 'read';
                    break;
                }
            }
        }

        if ($mode === 'edit' && !$selectedNote) {
            $mode = 'edit';
        }

        return $this->render('front/blog.html.twig', [
            'notes' => $payload,
            'selected_note' => $selectedNote,
            'mode' => $mode,
            'errors' => [],
        ]);
    }

    #[Route('/blog/save', name: 'front_blog_save', methods: ['POST'])]
    public function save(
        Request $request,
        EntityManagerInterface $em,
        CarnetRepository $carnetRepository
    ): Response {
        $user = $this->getUser();
        $noteId = $request->request->get('id');
        $titre = trim((string) $request->request->get('titre'));
        $contenu = (string) $request->request->get('contenu');

        $errors = [];
        if ($titre === '') {
            $errors[] = 'Le titre est obligatoire.';
        }

        if (!empty($errors)) {
            $user = $this->getUser();
            $criteria = [];
            if ($user) {
                $criteria = ['Utilisateurs' => $user];
            }

            $notes = $carnetRepository->findBy(
                $criteria,
                ['dateModification' => 'DESC']
            );

            $payload = [];
            foreach ($notes as $note) {
                $created = $note->getDateCreation();
                $modified = $note->getDateModification();
                $displayDate = $modified ?? $created;

                $payload[] = [
                    'id' => $note->getId(),
                    'titre' => $note->getTitre(),
                    'contenu' => $note->getContenu(),
                    'date_creation' => $created?->format('Y-m-d H:i'),
                    'date_modification' => $modified?->format('Y-m-d H:i'),
                    'date_affichee' => $displayDate?->format('Y-m-d H:i'),
                ];
            }

            return $this->render('front/blog.html.twig', [
                'notes' => $payload,
                'selected_note' => [
                    'id' => $noteId,
                    'titre' => $titre,
                    'contenu' => $contenu,
                ],
                'mode' => 'edit',
                'errors' => $errors,
            ]);
        }

        $now = new \DateTime();

        $note = null;
        if ($noteId) {
            $note = $em->getRepository(Carnet::class)->find($noteId);
            if (!$note) {
                return new Response('Note introuvable.', 404);
            }
        }

        if (!$note) {
            $note = new Carnet();
            $note->setDateCreation($now);
            if ($user) {
                $note->setUtilisateurs($user);
            }
        }

        $attachmentsMeta = [];
        $projectDir = $this->getParameter('kernel.project_dir');
        $uploadDir = $projectDir . '/public/uploads/carnet';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $attachments = $request->files->get('attachments', []);
        foreach ($attachments as $attachment) {
            if (!$attachment) {
                continue;
            }

            $mimeType = $attachment->getMimeType() ?? 'application/octet-stream';
            $filename = $attachment->getClientOriginalName() ?: 'fichier';
            $ext = pathinfo($filename, PATHINFO_EXTENSION) ?: $this->guessExtension($mimeType);
            $safeName = uniqid('carnet_', true) . ($ext ? '.' . $ext : '');
            $attachment->move($uploadDir, $safeName);
            $publicPath = '/uploads/carnet/' . $safeName;

            $attachmentsMeta[] = [
                'name' => $filename,
                'mime' => $mimeType,
                'path' => $publicPath,
            ];
        }

        $contenu = $this->replaceDataUrlsWithFiles(
            $contenu,
            $uploadDir,
            $attachmentsMeta
        );

        $note->setTitre($titre);
        $note->setContenu($contenu);

        if (!empty($attachmentsMeta)) {
            $note->setAttachments($attachmentsMeta);
        }
        $note->setDateModification($now);

        $em->persist($note);
        $em->flush();

        return $this->redirectToRoute('front_blog');
    }

    #[Route('/blog/delete', name: 'front_blog_delete', methods: ['POST'])]
    public function delete(Request $request, EntityManagerInterface $em): Response
    {
        $noteId = $request->request->get('id');
        if ($noteId) {
            $note = $em->getRepository(Carnet::class)->find($noteId);
            if ($note) {
                $em->remove($note);
                $em->flush();
            }
        }

        return $this->redirectToRoute('front_blog');
    }

    private function replaceDataUrlsWithFiles(string $html, string $uploadDir, array &$attachmentsMeta): string
    {
        if (trim($html) === '') {
            return $html;
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $this->replaceDataUrlInNodes($dom, 'img', 'src', $uploadDir, $attachmentsMeta);
        $this->replaceDataUrlInNodes($dom, 'source', 'src', $uploadDir, $attachmentsMeta);
        $this->replaceDataUrlInNodes($dom, 'audio', 'src', $uploadDir, $attachmentsMeta);
        $this->replaceDataUrlInNodes($dom, 'video', 'src', $uploadDir, $attachmentsMeta);
        $this->replaceDataUrlInNodes($dom, 'iframe', 'src', $uploadDir, $attachmentsMeta);
        $this->replaceDataUrlInNodes($dom, 'a', 'href', $uploadDir, $attachmentsMeta);

        $html = $dom->saveHTML();
        libxml_clear_errors();

        return $html;
    }

    private function replaceDataUrlInNodes(\DOMDocument $dom, string $tag, string $attr, string $uploadDir, array &$attachmentsMeta): void
    {
        $nodes = $dom->getElementsByTagName($tag);
        foreach ($nodes as $node) {
            if (!$node->hasAttribute($attr)) {
                continue;
            }
            $value = $node->getAttribute($attr);
            if (!str_starts_with($value, 'data:')) {
                continue;
            }

            $saved = $this->saveDataUrl($value, $uploadDir);
            if (!$saved) {
                continue;
            }

            $node->setAttribute($attr, $saved['path']);
            $attachmentsMeta[] = [
                'name' => $saved['name'],
                'mime' => $saved['mime'],
                'path' => $saved['path'],
            ];
        }
    }

    private function saveDataUrl(string $dataUrl, string $uploadDir): ?array
    {
        if (!preg_match('/^data:(.*?);base64,(.*)$/', $dataUrl, $matches)) {
            return null;
        }

        $mime = $matches[1];
        $data = base64_decode($matches[2], true);
        if ($data === false) {
            return null;
        }

        $ext = $this->guessExtension($mime);
        $safeName = uniqid('carnet_', true) . ($ext ? '.' . $ext : '');
        file_put_contents($uploadDir . '/' . $safeName, $data);

        return [
            'name' => $safeName,
            'mime' => $mime,
            'path' => '/uploads/carnet/' . $safeName,
        ];
    }

    private function guessExtension(string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'audio/mpeg' => 'mp3',
            'audio/webm' => 'webm',
            'audio/ogg' => 'ogg',
            'video/mp4' => 'mp4',
            'application/pdf' => 'pdf',
            default => '',
        };
    }

    #[Route('/blog/upload-audio', name: 'front_blog_upload_audio', methods: ['POST'])]
    public function uploadAudio(Request $request): JsonResponse
    {
        $file = $request->files->get('audio');
        if (!$file) {
            return new JsonResponse(['error' => 'Fichier audio manquant.'], 422);
        }

        $mimeType = $file->getMimeType() ?? 'audio/webm';
        $ext = $this->guessExtension($mimeType) ?: 'webm';
        $projectDir = $this->getParameter('kernel.project_dir');
        $uploadDir = $projectDir . '/public/uploads/carnet';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $safeName = uniqid('carnet_audio_', true) . '.' . $ext;
        $file->move($uploadDir, $safeName);

        return new JsonResponse([
            'url' => '/uploads/carnet/' . $safeName,
            'mime' => $mimeType,
        ]);
    }
}
