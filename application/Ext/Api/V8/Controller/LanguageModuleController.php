<?php
namespace Api\V8\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class LanguageModuleController
{
    /**
     * API lấy ngôn ngữ hệ thống (system language)
     */
    public function getSystemLanguage(Request $request, Response $response, array $args)
    {
        $lang = $args['lang'] ?? 'en_us';

        // sử dụng hàm core SuiteCRM (tự merge core + custom)
        $app_strings = return_application_language($lang);
        $app_list_strings = return_app_list_strings_language($lang);

        $result = [
            'language' => $lang,
            'data' => [
                'app_strings' => $app_strings,
                'app_list_strings' => $app_list_strings,
            ],
        ];

        $response->getBody()->write(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * API lấy ngôn ngữ của module
     */
    public function getModuleLanguage(Request $request, Response $response, array $args)
    {
        $module = $args['module'] ?? null;
        $lang = $args['lang'] ?? 'en_us';

        $languageData = $this->extractLanguageData($module, $lang);

        $result = [
            'module' => $module,
            'language' => $lang,
            'data' => $languageData,
        ];

        $response->getBody()->write(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Trích xuất dữ liệu ngôn ngữ (có merge core + custom)
     */
    private function extractLanguageData($module, $lang)
    {
        // mod_strings tự merge core + custom
        $mod_strings = return_module_language($lang, $module);

        // app_strings và app_list_strings (system-wide)
        $app_strings = return_application_language($lang);
        $app_list_strings = return_app_list_strings_language($lang);

        return [
            'mod_strings' => $mod_strings,
            'app_strings' => $app_strings,
            'app_list_strings' => $app_list_strings,
        ];
    }
}
