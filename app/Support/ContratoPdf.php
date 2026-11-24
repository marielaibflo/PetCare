<?php

namespace App\Support;

use App\Models\Afiliacion;

require_once app_path('Lib/FPDF/fpdf.php');

/** Contrato de afiliación en PDF (FPDF), con los datos reales de la afiliación. */
final class ContratoPdf
{
    public static function generar(Afiliacion $a): string
    {
        $a->loadMissing(['plan', 'mascota.propietario', 'veterinaria']);
        $t = fn (?string $s) => iconv('UTF-8', 'windows-1252//TRANSLIT', (string) $s);
        $d = Contrato::datos($a);
        $pdf = new \FPDF('P', 'mm', 'Letter');
        $pdf->SetTitle('Contrato '.$a->contrato_numero, true);
        $pdf->SetAuthor('PetCare', true);
        $pdf->SetMargins(20, 18, 20);
        $pdf->SetAutoPageBreak(true, 22);
        $pdf->AddPage();

        $pdf->SetFillColor(91, 63, 214);
        $pdf->Rect(0, 0, 216, 30, 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY(20, 8);
        $pdf->SetFont('Helvetica', 'B', 20);
        $pdf->Cell(90, 9, 'PetCare');
        $pdf->SetXY(110, 8);
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->Cell(86, 7, $t('CONTRATO DE AFILIACIÓN'), 0, 2, 'R');
        $pdf->SetFont('Helvetica', '', 10.5);
        $pdf->Cell(86, 6, $t('N.º '.$a->contrato_numero), 0, 2, 'R');

        $pdf->SetTextColor(29, 26, 59);
        $pdf->SetY(38);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->MultiCell(0, 5.5, $t('Aceptado en línea el '.($a->contrato_at ?? $a->created_at)->format('d/m/Y \a \l\a\s H:i').'. Mascota: '.$d['mascota'].' · Plan: '.$d['plan'].' · Veterinaria: '.$d['veterinaria'].'.'));
        $pdf->Ln(3);
        foreach (Contrato::clausulas($d) as $i => [$titulo, $texto]) {
            $pdf->SetFont('Helvetica', 'B', 10.5);
            $pdf->SetTextColor(68, 41, 181);
            $pdf->Cell(0, 5.6, $t(($i + 1).'. '.$titulo), 0, 1);
            $pdf->SetFont('Helvetica', '', 9.5);
            $pdf->SetTextColor(29, 26, 59);
            $pdf->MultiCell(0, 4.7, $t($texto));
            $pdf->Ln(1);
        }
        $pdf->Ln(4);
        if ($pdf->GetY() > 242) {
            $pdf->AddPage();
        }
        $y = $pdf->GetY();
        $pdf->SetDrawColor(180, 175, 210);
        $pdf->Line(25, $y + 12, 95, $y + 12);
        $pdf->Line(121, $y + 12, 191, $y + 12);
        $pdf->SetXY(25, $y + 13);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->Cell(70, 5, $t($d['dueno'].' · Titular (aceptación en línea)'), 0, 0, 'C');
        $pdf->SetXY(121, $y + 13);
        $pdf->Cell(70, 5, 'PetCare', 0, 0, 'C');
        $pdf->SetAutoPageBreak(false);
        $pdf->SetY(-18);
        $pdf->SetFont('Helvetica', 'I', 8);
        $pdf->SetTextColor(107, 104, 144);
        $pdf->Cell(0, 5, $t('Documento de demostración – sin validez legal.'), 0, 0, 'C');

        return $pdf->Output('S');
    }
}
