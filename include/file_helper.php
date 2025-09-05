<?php
/**
 * Helper file to get file information from database
 * This prevents duplicating database access code
 */

/**
 * Get database connection information from SuiteCRM configuration
 * 
 * @return array|null Database connection info or null on failure
 */
function getDbInfo() {
    $config_file = dirname(dirname(dirname(__FILE__))) . '/config.php';
    if (!file_exists($config_file)) {
        return null;
    }
    
    // Load minimal configuration
    $sugar_config = array();
    include($config_file);
    
    if (!isset($sugar_config['dbconfig'])) {
        return null;
    }
    
    return [
        'host' => $sugar_config['dbconfig']['db_host_name'],
        'user' => $sugar_config['dbconfig']['db_user_name'],
        'pass' => $sugar_config['dbconfig']['db_password'],
        'name' => $sugar_config['dbconfig']['db_name'],
    ];
}

/**
 * Get file information from database
 * 
 * @param string $module_name Module name
 * @param string $id Record ID
 * @return array|null File info array or null if not found
 */
function getFileInfoFromDb($module_name, $id) {
    // Remove the possible _photo suffix when searching in DB
    $search_id = str_replace('_photo', '', $id);
    
    $db_info = getDbInfo();
    if (!$db_info) {
        return null;
    }
    
    try {
        $conn = new mysqli($db_info['host'], $db_info['user'], $db_info['pass'], $db_info['name']);
        
        if ($conn->connect_error) {
            return null;
        }
        
        // Convert module name to lowercase for table name
        $table_name = strtolower($module_name);
        
        // Query to get file information
        $query = "SELECT filename, file_mime_type FROM {$table_name} WHERE id = ?";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            $conn->close();
            return null;
        }
        
        $stmt->bind_param("s", $search_id);
        $stmt->execute();
        $stmt->bind_result($filename, $mime_type);
        
        $result = null;
        if ($stmt->fetch()) {
            $result = [
                'filename' => $filename,
                'mime_type' => $mime_type
            ];
        }
        
        $stmt->close();
        $conn->close();
        
        return $result;
    } catch (Exception $e) {
        // Error handling
        return null;
    }
}
