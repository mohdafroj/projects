<?php

namespace App\Modules\Synopsis\Services;

use App\Modules\Synopsis\Models\SynopsisDocument;

class SynopsisPdfService
{
    public function render(SynopsisDocument $document): string
    {
        $title = $this->pdfText($document->title ?: 'Synopsis');
        $body = $this->pdfText($document->body ?: '');
        $lines = array_slice(explode("\n", wordwrap($body, 86, "\n")), 0, 42);
        $streamLines = ["BT", "/F1 14 Tf", "50 780 Td", "({$title}) Tj", "/F1 10 Tf"];

        foreach ($lines as $line) {
            $streamLines[] = '0 -16 Td';
            $streamLines[] = '('.$line.') Tj';
        }

        $streamLines[] = 'ET';
        $stream = implode("\n", $streamLines);
        $objects = [
            '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
            '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj',
            '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj',
            '4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj',
            '5 0 obj << /Length '.strlen($stream)." >> stream\n{$stream}\nendstream endobj",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object."\n";
        }
        $xrefAt = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= 'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xrefAt}\n%%EOF\n";

        return $pdf;
    }

    private function pdfText(string $text): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $ascii);
    }
}
