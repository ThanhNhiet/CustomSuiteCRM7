<?php
/**
 * Standalone API để lấy danh sách ngôn ngữ khả dụng
 * URL: {{suitecrm.url}}/custom/api_languages.php
 * Không cần authentication
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

function scanAvailableLanguages() {
    // Thử nhiều đường dẫn có thể đến thư mục install/language
    $possiblePaths = [
        '../install/language',
        './install/language', 
        'install/language',
        '../../install/language'
    ];
    
    $languageDir = null;
    foreach ($possiblePaths as $path) {
        if (is_dir($path)) {
            $languageDir = $path;
            break;
        }
    }
    
    $languages = [];
    
    if (!$languageDir) {
        // Debug: trả về thông tin đường dẫn hiện tại
        return [
            'error' => 'Language directory not found',
            'current_dir' => getcwd(),
            'tested_paths' => $possiblePaths
        ];
    }
    
    $files = scandir($languageDir);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        
        $filePath = $languageDir . '/' . $file;
        
        // Kiểm tra nếu là file .lang.php (ví dụ: en_us.lang.php, vi_VN.lang.php)
        if (is_file($filePath) && preg_match('/^([a-z]{2}_[a-zA-Z]{2})\.lang\.php$/', $file, $matches)) {
            $languageCode = $matches[1]; // Lấy phần en_us, vi_VN
            $languages[] = $languageCode;
        }
    }
    
    // Sắp xếp alphabetically
    sort($languages);
    
    return $languages;
}

try {
    $languages = scanAvailableLanguages();
    echo json_encode($languages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
