<?php

namespace App\Console\Commands;

use FPDF;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:make-sample-pdf')]
#[Description('Generate the 2-page sample PDF used by the seeder and the demo documents')]
class MakeSamplePdf extends Command
{
    private const LEFT = 68;

    private const WIDTH = 476;

    public function handle(): int
    {
        $pdf = new FPDF('P', 'pt', 'Letter');
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(self::LEFT, 72, self::LEFT);
        $pdf->SetTitle($this->text('Acuerdo de prestación de servicios'));

        $this->pageOne($pdf);
        $this->pageTwo($pdf);

        if ($pdf->PageNo() !== 2) {
            $this->error("The sample PDF must have exactly 2 pages, got {$pdf->PageNo()}.");

            return self::FAILURE;
        }

        $directory = public_path('samples');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory.DIRECTORY_SEPARATOR.'acuerdo-servicios.pdf';
        $pdf->Output('F', $path);

        $this->info("Sample PDF written to {$path}");

        return self::SUCCESS;
    }

    private function pageOne(FPDF $pdf): void
    {
        $pdf->AddPage();

        $pdf->SetFont('Helvetica', 'B', 18);
        $pdf->SetTextColor(26, 53, 96);
        $pdf->SetXY(self::LEFT, 72);
        $pdf->Cell(self::WIDTH, 24, $this->text('Acuerdo de prestación de servicios'), 0, 1);

        $this->body($pdf);
        $pdf->SetY(112);
        $pdf->MultiCell(self::WIDTH, 15, $this->text(
            'En Madrid, a 29 de septiembre de 2026, comparecen de una parte Nómada Studio S.L., '
            .'en adelante «el Cliente», y de otra parte el profesional independiente, en adelante '
            .'«el Prestador». Ambas partes se reconocen capacidad legal suficiente para obligarse '
            .'y acuerdan lo siguiente.'
        ));

        $clauses = [
            'Primera. Objeto' => 'El Prestador se compromete a realizar para el Cliente los servicios de diseño y '
                .'desarrollo descritos en la propuesta técnica que se adjunta como anexo y que forma parte '
                .'integrante de este acuerdo.',
            'Segunda. Duración' => 'El presente acuerdo entra en vigor en la fecha de su firma y tendrá una duración '
                .'de seis meses, prorrogable por periodos iguales salvo comunicación en contrario con un '
                .'preaviso de treinta días.',
            'Tercera. Honorarios' => 'El Cliente abonará al Prestador la cantidad mensual de tres mil euros (3.000 €), '
                .'más los impuestos que correspondan, mediante transferencia bancaria en los diez primeros '
                .'días de cada mes.',
            'Cuarta. Confidencialidad' => 'Ambas partes se obligan a mantener en secreto toda la información no pública '
                .'a la que accedan con motivo de este acuerdo, durante su vigencia y durante los dos años '
                .'siguientes a su terminación.',
        ];

        $this->clauses($pdf, $clauses);
        $this->footer($pdf, 1);
    }

    private function pageTwo(FPDF $pdf): void
    {
        $pdf->AddPage();

        $this->clauses($pdf, [
            'Quinta. Propiedad intelectual' => 'Los entregables desarrollados en virtud de este acuerdo serán propiedad '
                .'del Cliente una vez abonados íntegramente los honorarios correspondientes.',
            'Sexta. Resolución' => 'Cualquiera de las partes podrá resolver el acuerdo por incumplimiento grave de la '
                .'otra, previa comunicación escrita y un plazo de quince días para subsanarlo.',
            'Séptima. Legislación aplicable' => 'Este acuerdo se rige por la legislación española. Para cualquier '
                .'controversia las partes se someten a los juzgados y tribunales de Madrid.',
        ], 72);

        $this->body($pdf);
        $pdf->SetXY(self::LEFT, 400);
        $pdf->MultiCell(self::WIDTH, 15, $this->text(
            'Y en prueba de conformidad, las partes firman el presente acuerdo en la fecha indicada '
            .'en el encabezamiento.'
        ));

        // Signature fields sit at x = 11.1 % and 56.9 % of the width, y = 63.6 % of the height.
        $this->signatureBlock($pdf, 68, 'Por el Cliente');
        $this->signatureBlock($pdf, 348, 'Por el Prestador');

        $this->footer($pdf, 2);
    }

    private function signatureBlock(FPDF $pdf, float $x, string $label): void
    {
        $fieldTop = 503.7;
        $fieldHeight = 58;
        $fieldWidth = 176;

        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetTextColor(26, 53, 96);
        $pdf->SetXY($x, $fieldTop - 24);
        $pdf->Cell($fieldWidth, 14, $this->text($label));

        $lineY = $fieldTop + $fieldHeight + 4;
        $pdf->SetDrawColor(107, 112, 120);
        $pdf->SetLineWidth(0.6);
        $pdf->Line($x, $lineY, $x + $fieldWidth, $lineY);

        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(107, 112, 120);
        $pdf->SetXY($x, $lineY + 3);
        $pdf->Cell($fieldWidth, 12, $this->text('Nombre y fecha'));
    }

    /**
     * @param  array<string, string>  $clauses
     */
    private function clauses(FPDF $pdf, array $clauses, ?float $startY = null): void
    {
        if ($startY !== null) {
            $pdf->SetY($startY);
        } else {
            $pdf->SetY($pdf->GetY() + 14);
        }

        foreach ($clauses as $title => $text) {
            $pdf->SetX(self::LEFT);
            $pdf->SetFont('Helvetica', 'B', 11);
            $pdf->SetTextColor(26, 53, 96);
            $pdf->Cell(self::WIDTH, 18, $this->text($title), 0, 1);

            $this->body($pdf);
            $pdf->SetX(self::LEFT);
            $pdf->MultiCell(self::WIDTH, 15, $this->text($text));
            $pdf->SetY($pdf->GetY() + 10);
        }
    }

    private function body(FPDF $pdf): void
    {
        $pdf->SetFont('Helvetica', '', 11);
        $pdf->SetTextColor(46, 52, 62);
    }

    private function footer(FPDF $pdf, int $page): void
    {
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(107, 112, 120);
        $pdf->SetXY(self::LEFT, 740);
        $pdf->Cell(self::WIDTH, 12, $this->text("Acuerdo de prestación de servicios — Nómada Studio · Página {$page} de 2"), 0, 0, 'C');
    }

    /** FPDF core fonts use ISO-8859-1/Windows-1252, so convert the UTF-8 text. */
    private function text(string $text): string
    {
        return iconv('UTF-8', 'windows-1252//TRANSLIT', $text);
    }
}
