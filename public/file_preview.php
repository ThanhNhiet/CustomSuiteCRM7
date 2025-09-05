<?php
/**
 * Custom download file handler for direct image preview
 * This file handles displaying images inline instead of forcing downloads
 */

// Define the upload directory
$upload_dir = realpath(dirname(dirname(dirname(__FILE__))) . '/upload');

// Include helper for database operations
require_once(dirname(dirname(__FILE__)) . '/include/file_helper.php');

/**
 * Process file download/preview request
 */
function processFileRequest() {
    global $upload_dir;
    
    // Check for required parameters
    if (empty($_REQUEST['id'])) {
        header("HTTP/1.0 400 Bad Request");
        die("Missing required ID parameter");
    }
    
    $file_id = $_REQUEST['id'];
    $module_type = isset($_REQUEST['type']) ? $_REQUEST['type'] : null;
    
    // Get the file path
    $file_path = $upload_dir . '/' . $file_id;
    
    // Check if file exists
    if (!file_exists($file_path)) {
        header("HTTP/1.0 404 Not Found");
        die("File not found: " . basename($file_path));
    }
    
    // Try to get file info from database
    $file_info = null;
    if ($module_type) {
        $file_info = getFileInfoFromDb($module_type, $file_id);
    }
    
    // Get MIME type - prefer DB info if available
    $mime_type = ($file_info && !empty($file_info['mime_type'])) 
        ? $file_info['mime_type'] 
        : mime_content_type($file_path);
        
    if (empty($mime_type)) {
        $mime_type = 'application/octet-stream';
    }
    
    // Set the filename for download headers - prefer DB info if available
    if ($file_info && !empty($file_info['filename'])) {
        $filename = $file_info['filename'];
    } else {
        $filename = basename($file_path);
    }
    
    // Check if it's an image type
    $isImage = (strpos($mime_type, 'image/') === 0);
    
    // Get preview parameter - explicitly check the value
    $preview = isset($_REQUEST['preview']) && $_REQUEST['preview'] === 'true';
    
    // Set appropriate headers
    
    // Handle disposition based on preview flag
    if ($preview) {
        // For preview mode - set correct MIME type for viewing
        header("Content-Type: $mime_type");
        header("Content-Length: " . filesize($file_path));
        header("Content-Disposition: inline; filename=\"$filename\"");
        header("Cache-Control: public, max-age=86400"); // Cache for one day
    } else {
        // For download mode - force download with octet-stream for ALL file types
        // This ensures browser will ALWAYS download and never try to display
        header("Content-Type: application/octet-stream"); // Generic binary data
        header("Content-Length: " . filesize($file_path));
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Content-Transfer-Encoding: binary");
        header("Pragma: public");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Expires: 0");
    }
    
    // Output file content
    readfile($file_path);
    
    // End execution to prevent any additional output
    exit();
}

// Process the request
processFileRequest();
