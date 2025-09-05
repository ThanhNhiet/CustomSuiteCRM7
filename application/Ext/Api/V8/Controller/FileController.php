<?php
namespace Api\V8\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use SugarBean;

/**
 * FileController
 * 
 * Custom API endpoint for serving files from upload directory
 * API endpoint: GET /file/{moduleName}/{id}
 */
class FileController
{
    /**
     * Get file based on module and record ID
     * 
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function getFile(Request $request, Response $response, array $args)
    {
        $moduleName = $args['module'];
        $id = $args['id'];
        $downloadParam = $request->getQueryParams()['download'] ?? false;
        $previewParam = $request->getQueryParams()['preview'] ?? false;
        
        // Validate input
        if (empty($moduleName) || empty($id)) {
            return $this->errorResponse($response, 'Missing required parameters', 400);
        }
        
        // Convert module name to lowercase (as mentioned in requirements)
        $moduleNameLower = strtolower($moduleName);
        
        // 1. Check if file exists directly with the ID
        $uploadDir = realpath(dirname(__FILE__) . '/../../../../../../upload/');
        $exactFilePath = $this->findFile($uploadDir, $id);
        
        // If direct ID match not found, try with _photo suffix
        if (empty($exactFilePath)) {
            $exactFilePath = $this->findFile($uploadDir, $id . '_photo');
        }
        
        if (empty($exactFilePath)) {
            return $this->errorResponse($response, "File not found for ID: {$id}", 404);
        }
        
        // Get the file's MIME type
        $mimeType = $this->getMimeType($exactFilePath, $moduleNameLower, $id);
        
        // Get the filename (with extension)
        $filename = $this->getFilename($exactFilePath, $moduleNameLower, $id);
        
        // Check if the file is an image for direct preview
        $isImage = $this->isImageFile($mimeType);
        
        // Generate file URL
        $baseUrl = $this->getBaseUrl();
        $fileUrl = rtrim($baseUrl, '/') . '/upload/' . basename($exactFilePath);
        
        // Check if we need to stream the file directly (download or preview)
        if ($downloadParam === 'true' || $previewParam === 'true') {
            $fileContents = file_get_contents($exactFilePath);
            if ($fileContents === false) {
                return $this->errorResponse($response, "Unable to read file contents", 500);
            }
            
            // Set appropriate headers
            $response = $response->withHeader('Content-Type', $mimeType);
            
            // For images and preview mode, always use inline disposition
            if ($isImage && $previewParam === 'true') {
                // For image preview, always show inline
                $response = $response
                    ->withHeader('Content-Disposition', 'inline; filename="' . $filename . '"')
                    ->withHeader('Content-Length', filesize($exactFilePath))
                    ->withHeader('Cache-Control', 'public, max-age=86400'); // Cache for one day
            } else if ($downloadParam === 'true') {
                // Force download
                $response = $response
                    ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                    ->withHeader('Content-Length', filesize($exactFilePath));
            } else {
                // Default behavior for non-images or when no specific param is set
                $response = $response
                    ->withHeader('Content-Disposition', 'inline; filename="' . $filename . '"')
                    ->withHeader('Content-Length', filesize($exactFilePath));
            }
            
            // Write file content to the response body
            $response->getBody()->write($fileContents);
            return $response;
        }
        
        // If not downloading/previewing, return JSON metadata
        // Get the full filename from the exact file path
        $fileName = basename($exactFilePath);
        
        // Construct native SuiteCRM URL with the exact filename
        $baseUrl = $this->getBaseUrl();
        
        // Use our simplified file preview handler
        $nativeUrl = $baseUrl . "/custom/public/file_preview.php?id={$fileName}&type={$moduleName}&preview=true";
        
        // Add a download link that forces the file to download
        $downloadUrl = $baseUrl . "/custom/public/file_preview.php?id={$fileName}&type={$moduleName}&preview=false";
        
        // Add a native SuiteCRM download link using entryPoint as fallback option
        // This is the standard SuiteCRM download mechanism
        $originalId = $id;
        if (empty(strpos($fileName, '_photo')) && strpos($fileName, $id) === 0) {
            $originalId = $id; // Use as is if the filename doesn't contain _photo
        } elseif (strpos($fileName, '_photo') !== false) {
            $originalId = $id . '_photo'; // Add _photo suffix if the filename has it
        }
        $downloadUrlNative = $baseUrl . "/index.php?entryPoint=download&id={$originalId}&type={$moduleName}";
        
        // Return response
        $result = [
            'success' => true,
            'mime_type' => $mimeType,
            'filename' => $filename,
            'is_image' => $isImage,
            'native_url' => $nativeUrl,
            'download_url' => $downloadUrl,
            'download_url_native' => $downloadUrlNative
        ];
        
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * Find a file in the upload directory that matches the ID pattern
     * 
     * @param string $directory
     * @param string $searchPattern
     * @return string|null
     */
    private function findFile($directory, $searchPattern)
    {
        if (!is_dir($directory)) {
            return null;
        }
        
        // Get all files in the directory
        $files = scandir($directory);
        
        foreach ($files as $file) {
            // Skip directory entries
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            // Check if the file contains the search pattern
            if (strpos($file, $searchPattern) === 0) {
                return $directory . '/' . $file;
            }
        }
        
        return null;
    }
    
    /**
     * Get MIME type for the file
     * First tries to get it from the module record if available,
     * otherwise falls back to file detection
     * 
     * @param string $filePath
     * @param string $moduleName
     * @param string $id
     * @return string
     */
    private function getMimeType($filePath, $moduleName, $id)
    {
        global $beanList;
        
        // Try to get MIME type from the module record
        if (isset($beanList[$moduleName])) {
            $beanClass = $beanList[$moduleName];
            
            // Load the bean for this record
            $bean = new $beanClass();
            if ($bean->retrieve($id)) {
                if (!empty($bean->file_mime_type)) {
                    return $bean->file_mime_type;
                }
            }
        }
        
        // Fallback: use finfo to determine MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        
        // If still empty, provide a generic default
        if (empty($mimeType)) {
            $mimeType = 'application/octet-stream';
        }
        
        return $mimeType;
    }
    
    /**
     * Get filename with extension
     * First tries to get it from the module record if available,
     * otherwise uses the basename of the file
     * 
     * @param string $filePath
     * @param string $moduleName
     * @param string $id
     * @return string
     */
    private function getFilename($filePath, $moduleName, $id)
    {
        global $beanList;
        
        // Try to get filename from the module record
        if (isset($beanList[$moduleName])) {
            $beanClass = $beanList[$moduleName];
            
            // Load the bean for this record
            $bean = new $beanClass();
            if ($bean->retrieve($id)) {
                if (!empty($bean->filename)) {
                    return $bean->filename;
                }
            }
        }
        
        // Fallback: use basename
        return basename($filePath);
    }
    
    /**
     * Check if the MIME type corresponds to an image
     * 
     * @param string $mimeType
     * @return bool
     */
    private function isImageFile($mimeType)
    {
        return strpos($mimeType, 'image/') === 0;
    }
    
    /**
     * Get base URL for the SuiteCRM instance
     * Dynamically detects the current server's base URL
     * 
     * @return string
     */
    private function getBaseUrl()
    {
        // MOBILE DEVELOPMENT CONFIG - CHANGE THIS FOR MOBILE DEVELOPMENT
        // ---------------------------------------------------------------
        // Set to true when developing with mobile clients on local network
        $mobileDevMode = true;
        
        // Your computer's local network IPv4 address for mobile development
        // For example: "192.168.1.100" - Find this using "ipconfig" (Windows) or "ifconfig" (Mac/Linux)
        $localNetworkIp = "192.168.101.7"; // CHANGE THIS TO YOUR COMPUTER'S IP ADDRESS
        
        // Base path of your SuiteCRM installation
        $suiteBasePath = "/suitecrm7";
        
        // If mobile development mode is on, use the local network IP
        if ($mobileDevMode) {
            $protocol = "http://"; // Usually http for local development
            return $protocol . $localNetworkIp . $suiteBasePath;
        }
        // ---------------------------------------------------------------
        
        // PRODUCTION/NORMAL MODE (Default behavior)
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domainName = $_SERVER['HTTP_HOST'];
        
        // Try to detect base path from script name
        $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseDir = '';
        
        // Extract base path from script name (remove "Api/V8/custom/file/...")
        if (strpos($scriptPath, 'Api') !== false) {
            $baseDir = substr($scriptPath, 0, strpos($scriptPath, 'Api'));
        } else {
            // Fallback: try to detect from REQUEST_URI
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            if (strpos($requestUri, 'Api') !== false) {
                $baseDir = substr($requestUri, 0, strpos($requestUri, 'Api'));
            }
        }
        
        // Remove trailing slash if present
        $baseDir = rtrim($baseDir, '/');
        
        // Use detected base URL or try to read from config
        if (!empty($baseDir)) {
            return $protocol . $domainName . $baseDir;
        }
        
        // If we couldn't detect from URL, try to read from sugar config
        $configFile = dirname(__FILE__) . '/../../../../../../config.php';
        if (file_exists($configFile)) {
            include_once $configFile;
            if (isset($sugar_config) && isset($sugar_config['site_url'])) {
                return rtrim($sugar_config['site_url'], '/');
            }
        }
        
        // Final fallback: use current hostname with default path
        return $protocol . $domainName;
    }
    
    /**
     * Generate error response
     * 
     * @param Response $response
     * @param string $message
     * @param int $statusCode
     * @return Response
     */
    private function errorResponse(Response $response, $message, $statusCode = 400)
    {
        $result = [
            'success' => false,
            'message' => $message,
        ];
        
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
    }
}
