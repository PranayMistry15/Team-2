<?php

class ImageUploader {
    private $uploadDir = '../uploads/products/';
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private $maxFileSize = 5242880; // 5mb
    
    public function __construct() {
        // just a fallback 
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
    
    public function uploadMultiple($files) {
        $uploadedFiles = [];
        $errors = [];
        
        // Check if files were uploaded
        if (empty($files['name'][0])) {
            return ['success' => true, 'files' => []];
        }
        
        // Process each file
        $fileCount = count($files['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            
            // Skip if no file
            if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            
            // Check for upload errors
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                $errors[] = "Error uploading file: " . $files['name'][$i];
                continue;
            }
            
            // Validate file type
            $fileType = $files['type'][$i];
            if (!in_array($fileType, $this->allowedTypes)) {
                $errors[] = "Invalid file type for: " . $files['name'][$i] . ". Only JPEG, PNG, GIF, WEBP allowed.";
                continue;
            }
            
            // Validate file size
            if ($files['size'][$i] > $this->maxFileSize) {
                $errors[] = "File too large: " . $files['name'][$i] . ". Max size 5MB.";
                continue;
            }
            
            // Generate unique filename
            $extension = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
            $filename = uniqid('product_', true) . '.' . $extension;
            $targetPath = $this->uploadDir . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($files['tmp_name'][$i], $targetPath)) {
                $uploadedFiles[] = '/uploads/products/' . $filename;
            } else {
                $errors[] = "Failed to save file: " . $files['name'][$i];
            }
        }
        
        return [
            'success' => empty($errors),
            'files' => $uploadedFiles,
            'errors' => $errors
        ];
    }
    
    public function deleteImage($imagePath) {
        $fullPath = '..' . $imagePath;
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        return false;
    }
    
    public function deleteMultiple($imagePaths) {
        foreach ($imagePaths as $path) {
            $this->deleteImage($path);
        }
        return true;
    }
}
?>
