<?php

namespace App\Services;

use App\Models\SbcSignup;
use setasign\Fpdi\Fpdi;

class CertificateService
{
    private string $templatePath;

    public function __construct()
    {
        // Store the blank certificate template PDF here on the server
        $this->templatePath = storage_path('app/private/certificates/template.pdf');
    }

    public function generate(SbcSignup $signup): string
    {
        if (! file_exists($this->templatePath)) {
            throw new \RuntimeException(
                'Certificate template not found at: ' . $this->templatePath .
                "\nUpload SAMPLE.pdf to storage/app/private/certificates/template.pdf on the server."
            );
        }

        $name = strtoupper(trim($signup->first_name . ' ' . $signup->surname));

        $pdf = new Fpdi('L', 'mm', 'A4');
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        // Import the blank template as background
        $pdf->setSourceFile($this->templatePath);
        $templateId = $pdf->importPage(1);
        $pdf->useTemplate($templateId, 0, 0, 297, 210);

        // ── Name overlay ──────────────────────────────────────────────────
        // Coordinates calibrated for A4 landscape (297 × 210 mm)
        // Adjust NAME_Y if the text sits too high/low on the template
        $nameY      = 88;     // mm from top  — tweak this
        $nameStartX = 15;     // mm from left — text block starts here
        $nameWidth  = 215;    // mm — available width before the dark swoosh
        $nameFontSz = 36;     // pt — reduce if a long name overflows

        // Dynamically shrink font for very long names
        $maxWidth = $nameWidth * 2.83465; // convert mm to points approx
        while ($nameFontSz > 20) {
            $charWidth = $nameFontSz * 0.55 * strlen($name);
            if ($charWidth <= $maxWidth) break;
            $nameFontSz -= 2;
        }

        $pdf->SetFont('Helvetica', 'B', $nameFontSz);
        $pdf->SetTextColor(232, 116, 34);   // #e87422 orange
        $pdf->SetXY($nameStartX, $nameY);
        $pdf->Cell($nameWidth, 14, $name, 0, 0, 'C');

        return $pdf->Output('S'); // return as string
    }
}
