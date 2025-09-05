<?php
namespace Api\V8\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * SetupController
 */
class SetupController
{
    /**
     * Check if the current user is an admin
     * 
     * @param string $userId The user ID to check
     * @return bool True if the user is an admin, false otherwise
     */
    private function isUserAdmin($userId)
    {
        global $db;
        
        // Validate user ID
        if (empty($userId)) {
            return false;
        }
        
        try {
            // Escape the userId for security
            $escapedUserId = $db->quote($userId);
            
            // Query to check if user is admin
            $query = "SELECT is_admin FROM users WHERE id = '$escapedUserId' AND deleted = 0";
            $result = $db->query($query);
            
            if ($row = $db->fetchByAssoc($result)) {
                return $row['is_admin'] == '1';
            }
            
            return false;
        } catch (\Exception $e) {
            // Error during database query
            return false;
        }
    }
    
    /**
     * Save client secrets (client_id and client_secret)
     * Only admin users can access this endpoint
     * 
     * @param Request $request The request object
     * @param Response $response The response object
     * @param array $args Route arguments
     * @return Response The response with JSON data
     */
    public function saveSecret(Request $request, Response $response, array $args)
    {
        $userId = $args['user_id'];
        
        // Check if the user is an admin
        if (!$this->isUserAdmin($userId)) {
            $result = [
                'error' => 'Permission denied. Admin access required.'
            ];
            
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')
                           ->withStatus(403);
        }
        
        // Parse the request body
        $input = $request->getParsedBody();
        $client_id = $input['client_id'] ?? '';
        $client_secret = $input['client_secret'] ?? '';
        
        // Validate required fields
        if (!$client_id || !$client_secret) {
            $result = [
                'error' => 'Missing client_id or client_secret'
            ];
            
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')
                           ->withStatus(400);
        }
        
        // Prepare data to save
        $data = [
            'client_id' => $client_id,
            'client_secret' => $client_secret
        ];
        
        // Define the file path
        $filePath = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/../public/data/client_secret.json';
        
        // Create directory if it doesn't exist
        $dirPath = dirname($filePath);
        if (!is_dir($dirPath)) {
            if (!mkdir($dirPath, 0755, true)) {
                $result = [
                    'error' => 'Failed to create directory'
                ];
                
                $response->getBody()->write(json_encode($result));
                return $response->withHeader('Content-Type', 'application/json')
                               ->withStatus(500);
            }
        }
        
        // Write data to the file
        $result = file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        if ($result === false) {
            $result = [
                'error' => 'Failed to save client secret'
            ];
            
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')
                           ->withStatus(500);
        }
        
        // Return success response
        $result = [
            'success' => true,
            'data' => $data
        ];
        
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * Get the list of modules with their access permissions
     * This endpoint is publicly accessible
     * 
     * @param Request $request The request object
     * @param Response $response The response object
     * @param array $args Route arguments
     * @return Response The response with JSON data
     */
    public function getModulesList(Request $request, Response $response, array $args)
    {
        // Define the file path
        $filePath = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/../public/data/list_of_modules.json';
        
        // Check if file exists
        if (!file_exists($filePath)) {
            $result = [
                'error' => 'Modules list file not found'
            ];
            
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')
                           ->withStatus(404);
        }
        
        // Read the file content
        $fileContent = file_get_contents($filePath);
        
        if ($fileContent === false) {
            $result = [
                'error' => 'Failed to read modules list file'
            ];
            
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')
                           ->withStatus(500);
        }
        
        // Parse JSON
        $modulesData = json_decode($fileContent, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $result = [
                'error' => 'Invalid JSON format in modules list file',
                'json_error' => json_last_error_msg()
            ];
            
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')
                           ->withStatus(500);
        }
        
        // Format the response in the requested structure
        $formattedResponse = [
            'data' => [
                'type' => 'modules',
                'attributes' => $modulesData
            ]
        ];
        
        // Return the data in the new format
        $response->getBody()->write(json_encode($formattedResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * Save list of modules with their access permissions
     * Only admin users can access this endpoint
     * 
     * @param Request $request The request object
     * @param Response $response The response object
     * @param array $args Route arguments
     * @return Response The response with JSON data
     */
    public function saveModulesList(Request $request, Response $response, array $args)
    {
        $userId = $args['user_id'];
        
        // Check if the user is an admin
        if (!$this->isUserAdmin($userId)) {
            $result = [
                'error' => 'Permission denied. Admin access required.'
            ];
            
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')
                           ->withStatus(403);
        }
        
        // Get the request body as JSON
        $body = $request->getBody()->getContents();
        $modulesData = json_decode($body, true);
        
        // Validate input
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($modulesData)) {
            $result = [
                'error' => 'Invalid JSON data format',
                'json_error' => json_last_error_msg()
            ];
            
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')
                           ->withStatus(400);
        }
        
        // Define the file path
        $filePath = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/../public/data/list_of_modules.json';
        
        // Create directory if it doesn't exist
        $dirPath = dirname($filePath);
        if (!is_dir($dirPath)) {
            if (!mkdir($dirPath, 0755, true)) {
                $result = [
                    'error' => 'Failed to create directory'
                ];
                
                $response->getBody()->write(json_encode($result));
                return $response->withHeader('Content-Type', 'application/json')
                               ->withStatus(500);
            }
        }
        
        // Write data to the file
        $result = file_put_contents($filePath, json_encode($modulesData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        if ($result === false) {
            $result = [
                'error' => 'Failed to save modules list'
            ];
            
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')
                           ->withStatus(500);
        }
        
        // Return success response
        $result = [
            'success' => true,
            'message' => 'Modules list saved successfully'
        ];
        
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
