<?php
// src/Service/ExcelExportService.php

namespace App\Service;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class ExcelExportService
{
    /**
     * @param array<int, array<int, string|int|float|null>> $data
     * @param array<int, string> $headers
     */
    public function exportToExcel(array $data, string $filename, array $headers = []): Response
    {
        $spreadsheet = new Spreadsheet();
        /** @var Worksheet $sheet */
        $sheet = $spreadsheet->getActiveSheet();

        /* ================== En-têtes ================== */
        foreach ($headers as $index => $header) {
            $column = chr(ord('A') + $index);
            $sheet->setCellValue($column . '1', $header);
        }

        /* ================== Données ================== */
        $row = 2;
        foreach ($data as $item) {
            foreach (array_values($item) as $index => $value) {
                $column = chr(ord('A') + $index);
                $sheet->setCellValue($column . $row, $value);
            }
            $row++;
        }

        /* ================== Style en-têtes ================== */
        if (!empty($headers)) {
            $lastColumn = chr(ord('A') + count($headers) - 1);

           $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
    'font' => [
        'bold' => true,
        'color' => ['argb' => 'FFFFFFFF'],
    ],
    'fill' => [
        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
        'startColor' => ['argb' => 'FF102C59'],
    ],
]);

            /* ================== Auto-size ================== */
            foreach (range('A', $lastColumn) as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
        }

        /* ================== Génération fichier ================== */
        $writer = new Xlsx($spreadsheet);

        $tempFile = tempnam(sys_get_temp_dir(), 'excel_');
        if ($tempFile === false) {
            throw new \RuntimeException('Impossible de créer un fichier temporaire');
        }

        $writer->save($tempFile);

        $content = file_get_contents($tempFile);
        if ($content === false) {
            unlink($tempFile);
            throw new \RuntimeException('Impossible de lire le fichier Excel');
        }

        $response = new Response($content);

        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename . '.xlsx'
        );

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', $disposition);

        unlink($tempFile);

        return $response;
    }
}