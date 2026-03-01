<?php

namespace App\Controller;

use App\Entity\Objectif;
use App\Entity\Utilisateur;
use App\Enum\Statutobj;
use App\Repository\ObjectifRepository;
use App\Repository\UtilisateurRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin', name: 'back_')]
class BackObjectifController extends AbstractController
{
    /**
     * Liste tous les objectifs (ou filtrés par utilisateur)
     */
    #[Route('/events', name: 'events', methods: ['GET'])]
    public function index(
        ObjectifRepository $objectifRepo,
        UtilisateurRepository $utilisateurRepo,
        Request $request
    ): Response {
        $titre = trim((string) $request->query->get('titre', ''));
        $utilisateurId = $request->query->getInt('utilisateurId', 0);

        $queryBuilder = $objectifRepo->createQueryBuilder('o');

        // Filtre par titre
        if ($titre !== '') {
            $queryBuilder->andWhere('o.titre LIKE :titre')
                ->setParameter('titre', '%' . $titre . '%');
        }

        // Filtre par utilisateur (quand on clique sur "Voir les objectifs")
        if ($utilisateurId > 0) {
            $queryBuilder->andWhere('o.utilisateur = :utilisateur')
                ->setParameter('utilisateur', $utilisateurId);
        }

        // Tri
        $sort = $request->query->get('sort', 'datedebut');
        $orderBy = match ($sort) {
            'titre'     => 'titre',
            'datefin'   => 'datefin',
            'statut'    => 'statut',
            default     => 'datedebut',
        };

        $queryBuilder->orderBy('o.' . $orderBy, 'ASC');

        $objectifs = $queryBuilder->getQuery()->getResult();

        // Compteurs
        $total = count($objectifs);
        $atteints = $enCours = $abandonnes = 0;

        foreach ($objectifs as $objectif) {
            $statut = $objectif->getStatut()?->value;
            if ($statut === Statutobj::Atteint->value) {
                $atteints++;
            } elseif ($statut === Statutobj::EnCours->value) {
                $enCours++;
            } elseif ($statut === Statutobj::Abandonner->value) {
                $abandonnes++;
            }
        }

        // Récupérer l'utilisateur si filtré (pour afficher son nom)
        $utilisateur = null;
        if ($utilisateurId > 0) {
            $utilisateur = $utilisateurRepo->find($utilisateurId);
        }

        return $this->render('back/events.html.twig', [
            'objectifs'     => $objectifs,
            'total'         => $total,
            'atteints'      => $atteints,
            'enCours'       => $enCours,
            'abandonnes'    => $abandonnes,
            'utilisateur'   => $utilisateur,
            'utilisateurId' => $utilisateurId,
        ]);
    }

    /**
     * Export Excel de tous les objectifs
     */
    #[Route('/events/export/excel', name: 'events_export_excel')]
    public function exportExcel(ObjectifRepository $repo): Response
    {
        $objectifs = $repo->findAll();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'N°');
        $sheet->setCellValue('B1', 'ID Objectif');
        $sheet->setCellValue('C1', 'Titre');
        $sheet->setCellValue('D1', 'Date début');
        $sheet->setCellValue('E1', 'Date fin');
        $sheet->setCellValue('F1', 'Statut');

        $row = 2;
        foreach ($objectifs as $i => $objectif) {
            $sheet->setCellValue('A'.$row, $i + 1);
            $sheet->setCellValue('B'.$row, 'PPD' . $objectif->getId());
            $sheet->setCellValue('C'.$row, $objectif->getTitre());
            $sheet->setCellValue('D'.$row, $objectif->getDatedebut()?->format('d/m/Y') ?? '');
            $sheet->setCellValue('E'.$row, $objectif->getDatefin()?->format('d/m/Y') ?? '');
            $sheet->setCellValue('F'.$row, $objectif->getStatut()->value);
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'objectifs_admin_' . date('Y-m-d') . '.xlsx';
        $tempFile = sys_get_temp_dir() . '/' . $filename;
        $writer->save($tempFile);

        $response = new BinaryFileResponse($tempFile);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);

        return $response;
    }

    /**
     * Export Word de tous les objectifs
     */
    #[Route('/events/export/word', name: 'events_export_word')]
    public function exportWord(ObjectifRepository $repo): Response
    {
        $objectifs = $repo->findAll();

        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        $section->addTitle('Liste des Objectifs (Admin)', 1);
        $section->addTextBreak(1);

        $table = $section->addTable();
        $table->addRow();
        $table->addCell(2000)->addText('N°', ['bold' => true]);
        $table->addCell(3000)->addText('ID Objectif', ['bold' => true]);
        $table->addCell(5000)->addText('Titre', ['bold' => true]);
        $table->addCell(3000)->addText('Date début', ['bold' => true]);
        $table->addCell(3000)->addText('Date fin', ['bold' => true]);
        $table->addCell(2500)->addText('Statut', ['bold' => true]);

        $i = 1;
        foreach ($objectifs as $objectif) {
            $table->addRow();
            $table->addCell(2000)->addText((string) $i++);
            $table->addCell(3000)->addText('PPD' . $objectif->getId());
            $table->addCell(5000)->addText($objectif->getTitre());
            $table->addCell(3000)->addText($objectif->getDatedebut()?->format('d/m/Y') ?? '');
            $table->addCell(3000)->addText($objectif->getDatefin()?->format('d/m/Y') ?? '');
            $table->addCell(2500)->addText($objectif->getStatut()->value);
        }

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $filename = 'objectifs_admin_' . date('Y-m-d') . '.docx';
        $tempFile = sys_get_temp_dir() . '/' . $filename;
        $writer->save($tempFile);

        $response = new BinaryFileResponse($tempFile);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);

        return $response;
    }

    /**
     * Détail d'un objectif
     */
    #[Route('/events/{id}', name: 'events_show', methods: ['GET'])]
    public function show(?Objectif $objectif): Response
    {
        if (!$objectif) {
            $this->addFlash('danger', 'Objectif introuvable.');
            return $this->redirectToRoute('back_events');
        }

        return $this->render('back/objectif_show.html.twig', [
            'objectif' => $objectif,
        ]);
    }
    #[Route('/objectifs-utilisateurs', name: 'objectif_utilisateur', methods: ['GET'])]
public function utilisateursObjectifs(
    UtilisateurRepository $utilisateurRepo
): Response {
    $utilisateurs = $utilisateurRepo->findAll(); // ou findBy([], ['nom' => 'ASC']) pour trier

    return $this->render('back/objectifs_utilisateurs.html.twig', [
        'utilisateurs' => $utilisateurs,
    ]);
}
}