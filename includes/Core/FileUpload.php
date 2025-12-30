<?php
namespace ProConsultancy\Core;

class FileUpload {
    
    /**
     * Upload a file
     * 
     * @param string $fieldName Form field name
     * @param string $uploadDir Target directory
     * @param array $options Upload options
     * @return array ['success' => bool, 'filename' => string, 'path' => string, 'error' => string]
     */
    public function upload(string $fieldName, string $uploadDir, array $options = []): array {
        
        // Check if file uploaded
        if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'error' => 'No file uploaded or upload error occurred'
            ];
        }
        
        $file = $_FILES[$fieldName];
        
        // Validate file type
        if (isset($options['allowed_types'])) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($ext, $options['allowed_types'])) {
                return [
                    'success' => false,
                    'error' => 'Invalid file type. Allowed: ' . implode(', ', $options['allowed_types'])
                ];
            }
        }
        
        // Validate file size
        if (isset($options['max_size']) && $file['size'] > $options['max_size']) {
            $maxMB = round($options['max_size'] / 1024 / 1024, 2);
            return [
                'success' => false,
                'error' => "File too large. Maximum size: {$maxMB}MB"
            ];
        }
        
        // Generate filename
        if ($options['generate_unique_name'] ?? false) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'file_' . time() . '_' . uniqid() . '.' . $ext;
        } else {
            $filename = basename($file['name']);
        }
        
        // Ensure upload directory exists
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $targetPath = $uploadDir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return [
                'success' => false,
                'error' => 'Failed to move uploaded file'
            ];
        }
        
        return [
            'success' => true,
            'filename' => $filename,
            'path' => $targetPath,
            'size' => $file['size'],
            'type' => $file['type']
        ];
    }
}