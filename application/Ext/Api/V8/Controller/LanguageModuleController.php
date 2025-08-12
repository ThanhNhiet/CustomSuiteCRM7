<?php
namespace Api\V8\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

class LanguageModuleController
{
    // Authentication settings - có thể tắt/bật dễ dàng
    const ENABLE_AUTHENTICATION = false; // Tắt để test
    const ENABLE_LOGGING = true;
    
    // Fallback tokens nếu cần
    const FALLBACK_TOKENS = [
        'suitecrm_api_key_2025',
        'lang_api_secret_token', 
        'secure_language_api_123',
        'demo_token_for_testing'
    ];

    public function __construct()
    {
        // Không cần config file - sử dụng constants
    }

    /**
     * API để lấy ngôn ngữ hệ thống (system language) theo format RESTful
     * GET /api/v8/system/language/lang={lang}
     */
    public function getSystemLanguage(Request $request, Response $response, array $args)
    {
        $lang = $args['lang'] ?? 'en_us';

        try {
            // Kiểm tra API Token trước
            if (!$this->validateApiToken($request)) {
                return $response->withStatus(401)->withHeader('Content-Type', 'application/json')
                                ->write(json_encode(['error' => 'Unauthorized: Invalid or missing API token']));
            }

            // Validation
            if (!$this->isValidLanguage($lang)) {
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json')
                                ->write(json_encode(['error' => 'Invalid language code']));
            }

            // Chỉ lấy system language
            $app_strings = [];
            $app_list_strings = [];
            $this->loadSystemLanguage($lang, $app_strings, $app_list_strings);
            
            $result = [
                'language' => $lang,
                'data' => [
                    'app_strings' => $app_strings,
                    'app_list_strings' => $app_list_strings
                ],
                'meta' => [
                    'app_strings_count' => count($app_strings),
                    'app_list_strings_count' => count($app_list_strings),
                    'timestamp' => date('Y-m-d H:i:s'),
                    'endpoint' => "/system/language/lang={$lang}"
                ]
            ];

            $response->getBody()->write(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (Exception $e) {
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json')
                            ->write(json_encode(['error' => $e->getMessage()]));
        }
    }

    /**
     * API để lấy ngôn ngữ của module theo format RESTful
     * GET /api/v8/{module}/language/lang={lang}
     */
    public function getModuleLanguage(Request $request, Response $response, array $args)
    {
        $module = $args['module'] ?? null;
        $lang = $args['lang'] ?? 'en_us';
        
        if (!$module) {
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json')
                            ->write(json_encode(['error' => 'Missing module parameter']));
        }

        try {
            // Kiểm tra API Token trước
            if (!$this->validateApiToken($request)) {
                return $response->withStatus(401)->withHeader('Content-Type', 'application/json')
                                ->write(json_encode(['error' => 'Unauthorized: Invalid or missing API token']));
            }

            // Validation
            if (!$this->isValidModule($module)) {
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json')
                                ->write(json_encode(['error' => 'Invalid module name']));
            }

            if (!$this->isValidLanguage($lang)) {
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json')
                                ->write(json_encode(['error' => 'Invalid language code']));
            }

            $languageData = $this->extractLanguageData($module, $lang);
            
            $result = [
                'module' => $module,
                'language' => $lang,
                'data' => $languageData,
                'meta' => [
                    'mod_strings_count' => count($languageData['mod_strings']),
                    'app_strings_count' => count($languageData['app_strings']),
                    'app_list_strings_count' => count($languageData['app_list_strings']),
                    'timestamp' => date('Y-m-d H:i:s'),
                    'endpoint' => "/{$module}/language/lang={$lang}"
                ]
            ];

            $response->getBody()->write(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (Exception $e) {
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json')
                            ->write(json_encode(['error' => $e->getMessage()]));
        }
    }

    /**
     * Trích xuất dữ liệu ngôn ngữ từ các file language
     */
    private function extractLanguageData($module, $lang)
    {
        $mod_strings = [];
        $app_strings = [];
        $app_list_strings = [];

        // 1. Tải file ngôn ngữ hệ thống
        $this->loadSystemLanguage($lang, $app_strings, $app_list_strings);

        // 2. Tải ngôn ngữ module
        $this->loadModuleLanguage($module, $lang, $mod_strings);

        // 3. Tải ngôn ngữ custom module
        $this->loadCustomModuleLanguage($module, $lang, $mod_strings);

        return [
            'mod_strings' => $mod_strings,
            'app_strings' => $app_strings,
            'app_list_strings' => $app_list_strings
        ];
    }

    /**
     * Tải ngôn ngữ hệ thống
     */
    private function loadSystemLanguage($lang, &$app_strings, &$app_list_strings)
    {
        // Chỉ lấy file từ core path
        $corePath = "include/language/{$lang}.lang.php";
        
        if (file_exists($corePath)) {
            include $corePath;
        }
    }

    /**
     * Tải ngôn ngữ module
     */
    private function loadModuleLanguage($module, $lang, &$mod_strings)
    {
        // Chỉ lấy file từ core path
        $corePath = "modules/{$module}/language/{$lang}.lang.php";
        
        if (file_exists($corePath)) {
            include $corePath;
        }
    }

    /**
     * Tải ngôn ngữ custom module
     */
    private function loadCustomModuleLanguage($module, $lang, &$mod_strings)
    {
        // custom\modules\Accounts\Ext\Language
        $corePath = "custom/modules/{$module}/Ext/language/{$lang}.lang.ext.php";
        if (file_exists($corePath)) {
            include $corePath;
        }
    }

    /**
     * Validate module name
     */
    private function isValidModule($module)
    {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $module)) {
            return false;
        }

        // Kiểm tra module có tồn tại không (chỉ check core path)
        $corePath = "modules/{$module}";
        
        return is_dir($corePath);
    }

    /**
     * Validate language code
     */
    private function isValidLanguage($lang)
    {
        // Kiểm tra format ngôn ngữ (en_us, vi_VN, en_GB, etc.)
        return preg_match('/^[a-z]{2}_[a-zA-Z]{2}$/', $lang);
    }

    /**
     * Validate API Token
     */
    private function validateApiToken(Request $request)
    {
        // Nếu authentication bị tắt
        if (!self::ENABLE_AUTHENTICATION) {
            return true;
        }

        // Lấy token từ Authorization header (Bearer token)
        $authHeader = $request->getHeaderLine('Authorization');
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = trim($matches[1]);
            return $this->validateSuiteCRMToken($token);
        }

        // Kiểm tra token từ X-API-Key header
        $apiKey = $request->getHeaderLine('X-API-Key');
        if (!empty($apiKey)) {
            return $this->validateSuiteCRMToken($apiKey);
        }

        // Kiểm tra token từ query parameter (backup method)
        $queryParams = $request->getQueryParams();
        $queryToken = $queryParams['access_token'] ?? '';
        if (!empty($queryToken)) {
            return $this->validateSuiteCRMToken($queryToken);
        }

        return false;
    }

    /**
     * Validate SuiteCRM OAuth Token
     */
    private function validateSuiteCRMToken($token)
    {
        try {
            // Gọi SuiteCRM API để validate token
            $validateUrl = "http://localhost/suitecrm7/Api/V8/me";
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "Authorization: Bearer {$token}\r\n" .
                               "Content-Type: application/json\r\n",
                    'timeout' => 10
                ]
            ]);

            $response = @file_get_contents($validateUrl, false, $context);
            
            if ($response !== false) {
                $data = json_decode($response, true);
                if (isset($data['data']) && isset($data['data']['id'])) {
                    $this->logApiCall($token, 'SuiteCRM Token', $data['data']['attributes']['user_name'] ?? 'unknown');
                    return true;
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            // Fallback: check custom tokens nếu SuiteCRM token validation fails
            return in_array($token, self::FALLBACK_TOKENS);
        }
    }

    /**
     * Log API calls nếu được bật trong config
     */
    private function logApiCall($token, $authMethod, $userName = 'unknown')
    {
        if (!self::ENABLE_LOGGING) {
            return;
        }

        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'auth_method' => $authMethod,
            'user_name' => $userName,
            'token' => substr($token, 0, 8) . '...', // Chỉ log 8 ký tự đầu
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];

        // Ghi log vào file
        $logFile = __DIR__ . '/../Logs/api_calls.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, json_encode($logData) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
