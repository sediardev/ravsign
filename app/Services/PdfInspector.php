<?php

namespace App\Services;

use setasign\Fpdi\Fpdi;
use Throwable;

class PdfInspector
{
    /**
     * Number of pages of a PDF file, or null when FPDI cannot read it
     * (corrupt, encrypted or using compression FPDI free cannot parse).
     */
    public function pageCount(string $path): ?int
    {
        try {
            return (new Fpdi)->setSourceFile($path);
        } catch (Throwable) {
            return null;
        }
    }
}
