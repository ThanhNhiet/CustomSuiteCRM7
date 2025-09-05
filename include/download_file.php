<?php
/**
 * Custom download file handler to override SuiteCRM's default behavior
 * This file adds proper headers for image previews
 */

// Include the original download_file.php
require_once('include/download_file.php');

/**
 * Override the default download function to handle image previews
 *
 * @param string $id The ID of the file to download
 * @param string|null $type The module type associated with the file
 * @return void
 */
function customDownloadFile($id, $type = null)
{
    // Get the file path
    $file_path = "upload/$id";
    
    // Check if file exists
    if (!file_exists($file_path)) {
        header("HTTP/1.0 404 Not Found");
        die("File not found: $file_path");
    }
    
    // Get file mime type
    $mime_type = mime_content_type($file_path);
    
    // Set the filename for download headers
    $filename = basename($file_path);
    
    // Check if it's an image type
    $isImage = (strpos($mime_type, 'image/') === 0);
    
    // Get preview parameter
    $preview = isset($_REQUEST['preview']) && $_REQUEST['preview'] === 'true';
    
    // Set appropriate headers
    header("Content-Type: $mime_type");
    header("Content-Length: " . filesize($file_path));
    
    // For images, use inline disposition by default
    if ($isImage || $preview) {
        // Use inline disposition for images and preview requests
        header("Content-Disposition: inline; filename=\"$filename\"");
        header("Cache-Control: public, max-age=86400"); // Cache for one day
    } else {
        // For non-images, force download
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Pragma: public");
        header("Cache-Control: max-age=0");
    }
    
    // Output file content
    readfile($file_path);
    
    // End execution to prevent any additional output
    exit();
}

// Check if we should process this request with our custom function
if (isset($_REQUEST['custom']) && $_REQUEST['custom'] === 'true') {
    // Call our custom download function
    customDownloadFile($_REQUEST['id'], $_REQUEST['type'] ?? null);
}
