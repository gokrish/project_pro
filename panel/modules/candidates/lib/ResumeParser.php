<?php
/**
 * Resume Parser - Basic Version
 * Extracts text from PDF/DOCX and parses basic fields
 * 
 * @version 1.0 - Basic (No AI)
 */

namespace ProConsultancy\Candidates;

use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpWord\IOFactory;

class ResumeParser
{
    private $filePath;
    private $fileType;
    private $rawText = '';
    private $parsedData = [];
    
    public function __construct($filePath)
    {
        $this->filePath = $filePath;
        $this->fileType = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    }
    
    /**
     * Main parse method
     */
    public function parse()
    {
        try {
            // Step 1: Extract text
            $this->extractText();
            
            // Step 2: Parse fields
            $this->parseName();
            $this->parseEmail();
            $this->parsePhone();
            $this->parseLinkedIn();
            $this->parseLocation();
            
            return [
                'success' => true,
                'data' => $this->parsedData,
                'raw_text' => $this->rawText,
                'parse_status' => 'success'
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'parse_status' => 'failed'
            ];
        }
    }
    
    /**
     * Extract text from PDF or DOCX
     */
    private function extractText()
    {
        if ($this->fileType === 'pdf') {
            $this->extractTextFromPDF();
        } elseif ($this->fileType === 'docx') {
            $this->extractTextFromDOCX();
        } else {
            throw new \Exception('Unsupported file type: ' . $this->fileType);
        }
        
        // Clean text
        $this->rawText = $this->cleanText($this->rawText);
    }
    
    /**
     * Extract text from PDF
     */
    private function extractTextFromPDF()
    {
        $parser = new PdfParser();
        $pdf = $parser->parseFile($this->filePath);
        $this->rawText = $pdf->getText();
    }
    
    /**
     * Extract text from DOCX
     */
    private function extractTextFromDOCX()
    {
        $phpWord = IOFactory::load($this->filePath);
        $text = '';
        
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= $element->getText() . "\n";
                } elseif (method_exists($element, 'getElements')) {
                    foreach ($element->getElements() as $childElement) {
                        if (method_exists($childElement, 'getText')) {
                            $text .= $childElement->getText() . "\n";
                        }
                    }
                }
            }
        }
        
        $this->rawText = $text;
    }
    
    /**
     * Clean extracted text
     */
    private function cleanText($text)
    {
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Remove special characters but keep basic punctuation
        $text = preg_replace('/[^\w\s@.+\-()\/,;:]/', '', $text);
        
        return trim($text);
    }
    
    /**
     * Parse candidate name (from first few lines)
     */
    private function parseName()
    {
        // Get first 5 lines
        $lines = explode("\n", $this->rawText);
        $firstLines = array_slice($lines, 0, 5);
        
        foreach ($firstLines as $line) {
            $line = trim($line);
            
            // Skip empty lines
            if (empty($line)) continue;
            
            // Name is usually: 2-4 words, first letters capitalized
            // Exclude lines with emails, phones, or URLs
            if (preg_match('/^[A-Z][a-z]+\s+[A-Z][a-z]+(\s+[A-Z][a-z]+)?$/', $line) &&
                !preg_match('/@|http|www|\d{3,}/', $line)) {
                $this->parsedData['name'] = $line;
                return;
            }
        }
        
        // Fallback: take first non-empty line
        foreach ($firstLines as $line) {
            $line = trim($line);
            if (!empty($line) && strlen($line) < 50) {
                $this->parsedData['name'] = $line;
                return;
            }
        }
    }
    
    /**
     * Parse email
     */
    private function parseEmail()
    {
        // Regex for email
        if (preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', 
                       $this->rawText, $matches)) {
            $this->parsedData['email'] = strtolower($matches[0]);
        }
    }
    
    /**
     * Parse phone number (Belgium format)
     */
    private function parsePhone()
    {
        // Belgium phone patterns
        $patterns = [
            '/\+32\s*\d{1,2}\s*\d{3}\s*\d{2}\s*\d{2}/',  // +32 4 123 45 67
            '/00\s*32\s*\d{1,2}\s*\d{3}\s*\d{2}\s*\d{2}/', // 0032 4 123 45 67
            '/0\d{1,2}\s*\d{3}\s*\d{2}\s*\d{2}/',         // 04 123 45 67
            '/\d{3,4}\s*\d{2}\s*\d{2}\s*\d{2}/'           // 123 45 67 89
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $this->rawText, $matches)) {
                // Clean phone number
                $phone = preg_replace('/\s+/', '', $matches[0]);
                $this->parsedData['phone'] = $phone;
                return;
            }
        }
    }
    
    /**
     * Parse LinkedIn URL
     */
    private function parseLinkedIn()
    {
        if (preg_match('/(?:https?:\/\/)?(?:www\.)?linkedin\.com\/in\/[\w\-]+\/?/i', 
                       $this->rawText, $matches)) {
            $this->parsedData['linkedin_url'] = $matches[0];
        }
    }
    
    /**
     * Parse location
     */
    private function parseLocation()
    {
        $locations = [
            'Belgium', 'Brussels', 'Antwerp', 'Ghent', 'Bruges', 'Leuven',
            'Netherlands', 'Amsterdam', 'Rotterdam',
            'Luxembourg',
            'Germany', 'Berlin', 'Munich',
            'France', 'Paris',
            'India', 'Bangalore', 'Mumbai', 'Hyderabad'
        ];
        
        // Default to Belgium
        $this->parsedData['location'] = 'Belgium';
    }
    
    /**
     * Get raw text (for manual review)
     */
    public function getRawText()
    {
        return $this->rawText;
    }
    
    /**
     * Get parsed data
     */
    public function getParsedData()
    {
        return $this->parsedData;
    }
}