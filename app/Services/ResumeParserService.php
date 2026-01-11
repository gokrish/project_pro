<?php

namespace App\Services;

use Smalot\PdfParser\Parser;

class ResumeParserService
{
    public function extractText(string $filePath): string
    {
        $mime = mime_content_type($filePath);

        if ($mime === 'application/pdf') {
            try {
                $parser = new Parser();
                $pdf = $parser->parseFile($filePath);
                return $pdf->getText();
            } catch (\Exception $e) {
                return "Error parsing PDF: " . $e->getMessage();
            }
        }

        // Simple text fallback for plain text files
        if (str_starts_with($mime, 'text/')) {
            return file_get_contents($filePath);
        }

        // TODO: Add DOCX support later (requires zip extension/more libs logic)

        return "Unsupported file type: $mime";
    }
}
