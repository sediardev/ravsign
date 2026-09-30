<?php

namespace App\Services;

use App\Models\Document;
use App\Models\SignField;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

/**
 * Builds the final PDF of a completed document: the original pages with each
 * signature stamped where its field sits.
 */
class SignedPdfBuilder
{
    /** Space kept between a field's border and its signature, in points (as on screen). */
    private const PADDING = 3;

    /**
     * Stamp the signatures onto the original and store the result.
     * Returns the storage path of the signed PDF.
     */
    public function build(Document $document): string
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($document->original_path)) {
            throw new RuntimeException("The original PDF of document {$document->id} is missing.");
        }

        $fieldsByPage = $document->fields()->get()->groupBy('page');

        $pdf = new Fpdi('P', 'pt');
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);

        $pages = $pdf->setSourceFile(StreamReader::createByString($disk->get($document->original_path)));

        for ($number = 1; $number <= $pages; $number++) {
            $template = $pdf->importPage($number);
            $size = $pdf->getTemplateSize($template);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($template);

            foreach ($fieldsByPage->get($number - 1, collect()) as $field) {
                $this->stamp($pdf, $field, $size['width'], $size['height']);
            }
        }

        $path = 'signed/'.Str::uuid().'.pdf';
        $disk->put($path, $pdf->Output('S'));

        if ($document->signed_path !== null) {
            $disk->delete($document->signed_path);
        }

        $document->update(['signed_path' => $path]);

        return $path;
    }

    /**
     * Where a signature image goes, in points from the top-left of the page.
     *
     * A field's position is stored as a percentage of the page. The image is
     * scaled to fit inside the field, keeping its proportions, and centered.
     *
     * @return array{x: float, y: float, width: float, height: float}
     */
    public function placement(
        float $xPercent,
        float $yPercent,
        float $fieldWidth,
        float $fieldHeight,
        float $pageWidth,
        float $pageHeight,
        int $imageWidth,
        int $imageHeight,
    ): array {
        $left = $xPercent / 100 * $pageWidth;
        $top = $yPercent / 100 * $pageHeight;

        $availableWidth = $fieldWidth - 2 * self::PADDING;
        $availableHeight = $fieldHeight - 2 * self::PADDING;

        $scale = min($availableWidth / max($imageWidth, 1), $availableHeight / max($imageHeight, 1));
        $width = $imageWidth * $scale;
        $height = $imageHeight * $scale;

        return [
            'x' => $left + ($fieldWidth - $width) / 2,
            'y' => $top + ($fieldHeight - $height) / 2,
            'width' => $width,
            'height' => $height,
        ];
    }

    private function stamp(Fpdi $pdf, SignField $field, float $pageWidth, float $pageHeight): void
    {
        if ($field->value_path !== null) {
            $this->stampImage($pdf, $field, $pageWidth, $pageHeight);
        } elseif ($field->value_text !== null) {
            $this->stampText($pdf, $field, $pageWidth, $pageHeight);
        }
    }

    private function stampImage(Fpdi $pdf, SignField $field, float $pageWidth, float $pageHeight): void
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($field->value_path)) {
            return;
        }

        $bytes = $disk->get($field->value_path);
        $info = getimagesizefromstring($bytes);

        if ($info === false) {
            return;
        }

        $box = $this->placement($field->x, $field->y, $field->width, $field->height, $pageWidth, $pageHeight, $info[0], $info[1]);

        // FPDF reads images from files, so hand it a temporary one.
        $temp = tempnam(sys_get_temp_dir(), 'sig');

        try {
            file_put_contents($temp, $bytes);
            $pdf->Image($temp, $box['x'], $box['y'], $box['width'], $box['height'], 'PNG');
        } finally {
            @unlink($temp);
        }
    }

    private function stampText(Fpdi $pdf, SignField $field, float $pageWidth, float $pageHeight): void
    {
        $fieldWidth = $field->width;
        $fieldHeight = $field->height;

        $pdf->SetFont('Helvetica', 'I', 14);
        $pdf->SetTextColor(26, 53, 96);
        $pdf->SetXY(
            $field->x / 100 * $pageWidth + self::PADDING,
            $field->y / 100 * $pageHeight,
        );

        // Core fonts use Windows-1252, so convert the UTF-8 text.
        $pdf->Cell(
            $fieldWidth - 2 * self::PADDING,
            $fieldHeight,
            (string) iconv('UTF-8', 'windows-1252//TRANSLIT', $field->value_text),
            0,
            0,
            'L',
        );
    }
}
