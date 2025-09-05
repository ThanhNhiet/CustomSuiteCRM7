<?php
/**
 * Custom download entry point to override SuiteCRM's default behavior
 * This file adds proper headers for image previews
 */

// Define SuiteCRM root directory
define('SUITECRM_ROOT', realpath(dirname(dirname(dirname(__FILE__)))));

// Check if we're handling a download request
if (isset($_REQUEST['id'])) {
    // Include our custom download handler
    $download_file_path = SUITECRM_ROOT . '/custom/include/download_file.php';
    
    if (!file_exists($download_file_path)) {
        die("Error: Required file not found at: " . $download_file_path);
    }
    
    require_once($download_file_path);
    
    // Call our custom download function
    customDownloadFile($_REQUEST['id'], $_REQUEST['type'] ?? null);
} else {
    // Invalid request
    header("HTTP/1.0 400 Bad Request");
    die("Missing required parameters");
}
