<?php

namespace App\Support;

use App\Models\Pago;

require_once app_path('Lib/FPDF/fpdf.php');

/**
 * Comprobante de pago en PDF (librería FPDF incluida en app/Lib/FPDF).
 * Es un comprobante interno de PetCare: no reemplaza a la factura fiscal del SIN.
 */
final class ComprobantePdf
{
    private const MORADO = [91, 63, 214];

    public static function generar(Pago $pago): string
    {
        $t = fn (?string $s) => iconv('UTF-8', 'windows-1252//TRANSLIT', (string) $s);
        $pdf = new \FPDF('P', 'mm', 'Letter');
        $pdf->SetTitle('Factura '.$pago->numero, true);
        $pdf->SetAuthor('PetCare', true);
        $pdf->AddPage();
        $pdf->SetMargins(18, 18, 18);

        // Encabezado
        $pdf->SetFillColor(...self::MORADO);
        $pdf->Rect(0, 0, 216, 34, 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY(18, 9);
        $pdf->SetFont('Helvetica', 'B', 22);
        $pdf->Cell(100, 10, 'PetCare');
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetXY(18, 19);
        $pdf->Cell(100, 6, $t('Plataforma de asistencia veterinaria · La Paz, Bolivia'));
        $pdf->SetXY(120, 9);
        $pdf->SetFont('Helvetica', 'B', 13);
        $pdf->Cell(78, 8, 'FACTURA', 0, 2, 'R');
        $pdf->SetFont('Helvetica', '', 11);
        $pdf->Cell(78, 6, $t('N.º '.$pago->numero), 0, 2, 'R');

        // Datos
        $pdf->SetTextColor(29, 26, 59);
        $pdf->SetXY(18, 44);
        $fila = function (string $k, string $v) use ($pdf, $t) {
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->Cell(48, 7, $t($k));
            $pdf->SetFont('Helvetica', '', 10);
            $pdf->MultiCell(0, 7, $t($v));
        };
        $fila('Fecha de aprobación:', $pago->revisado_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i'));
        $fila('Nombre / Razón social:', $pago->facturar_a);
        $fila('CI / NIT:', $pago->documento);
        $fila('Cliente PetCare:', $pago->user->name.' · '.$pago->user->email);
        $fila('Método de pago:', $pago->metodoTexto());

        // Detalle
        $pdf->Ln(4);
        $pdf->SetFillColor(239, 235, 255);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(130, 9, ' '.$t('Concepto'), 0, 0, 'L', true);
        $pdf->Cell(50, 9, $t('Importe (Bs)').' ', 0, 1, 'R', true);
        $pdf->SetFont('Helvetica', '', 10);
        $y = $pdf->GetY();
        $pdf->MultiCell(130, 7, ' '.$t($pago->detalle()));
        $alto = $pdf->GetY() - $y;
        $pdf->SetXY(148, $y);
        $pdf->Cell(50, $alto, number_format($pago->monto, 2, ',', '.').' ', 0, 1, 'R');
        if ($pago->concepto === 'plan' && $pago->modalidad === 'anual') {
            $pdf->SetFont('Helvetica', 'I', 9);
            $pdf->Cell(0, 6, ' '.$t('Pago anual: 12 meses de cobertura pagando 10.'), 0, 1);
        }
        $pdf->SetDrawColor(...self::MORADO);
        $pdf->Line(18, $pdf->GetY() + 2, 198, $pdf->GetY() + 2);
        $pdf->Ln(5);
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->Cell(130, 9, ' TOTAL PAGADO');
        $pdf->Cell(50, 9, 'Bs '.number_format($pago->monto, 2, ',', '.').' ', 0, 1, 'R');

        // Vigencia
        $pdf->Ln(6);
        $pdf->SetFont('Helvetica', '', 10);
        if ($pago->concepto === 'plan' && $pago->mascota?->afiliacionActiva) {
            $a = $pago->mascota->afiliacionActiva;
            $pdf->MultiCell(0, 6, $t("Plan vigente para {$pago->mascota->nombre} del {$a->fecha_inicio->format('d/m/Y')} al {$a->fecha_fin->format('d/m/Y')}. "
                ."Cobertura del {$a->plan->porcentaje} % en los servicios incluidos, hasta Bs ".number_format($a->plan->tope_mensual, 0, ',', '.')
                .' al mes y Bs '.number_format($a->plan->tope_anual, 0, ',', '.').' al año.'));
        } elseif ($pago->concepto === 'suscripcion' && $pago->veterinaria?->suscripcion_hasta) {
            $pdf->MultiCell(0, 6, $t("Suscripción de {$pago->veterinaria->nombre} vigente hasta el {$pago->veterinaria->suscripcion_hasta->format('d/m/Y')}."));
        }

        // Pie
        $pdf->SetY(-40);
        $pdf->SetFont('Helvetica', 'I', 8.5);
        $pdf->SetTextColor(107, 104, 144);
        $pdf->MultiCell(0, 5, $t('Factura emitida por PetCare. Pago verificado por: '.($pago->revisor?->name ?? 'Administración PetCare').'.'), 0, 'C');
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->MultiCell(0, 5, $t('Documento de demostración – sin validez fiscal.'), 0, 'C');

        return $pdf->Output('S');
    }
}
