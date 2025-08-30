<?php
namespace Api\V8\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class EnumController
{
    public function getModuleEnumOptions(Request $request, Response $response, array $args)
    {
        $moduleName = $args['module'];
        $queryParams = $request->getQueryParams();
        $fields = isset($queryParams['fields']) ? explode(',', $queryParams['fields']) : [];
        $lang = $queryParams['lang'] ?? 'en_us';

        global $app_list_strings, $beanList;

        // Load ngôn ngữ theo lang
        $GLOBALS['current_language'] = $lang;
        $app_list_strings = return_app_list_strings_language($lang);

        // Check module tồn tại
        if (!isset($beanList[$moduleName])) {
            $result = [
                'success' => false,
                'message' => "Module '{$moduleName}' not found",
            ];
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Lấy bean (vardefs đã merge custom + core)
        $beanClass = $beanList[$moduleName];
        require_once "modules/{$moduleName}/{$beanClass}.php";
        $bean = new $beanClass();

        $results = [];
        foreach ($fields as $fieldName) {
            $fieldName = trim($fieldName);

            if (!isset($bean->field_defs[$fieldName]) && $fieldName !== 'parent_type') {
                $results[$fieldName] = [
                    'success' => false,
                    'message' => "Field '{$fieldName}' not found in module '{$moduleName}'",
                ];
                continue;
            }

            $fieldDef = $bean->field_defs[$fieldName] ?? [];
            $optionKey = null;

            // Nếu là enum hoặc parent_type
            if (
                ($fieldDef['type'] ?? '') === 'enum' && !empty($fieldDef['options'])
                || $fieldName === 'parent_type'
            ) {
                if ($fieldName === 'parent_type') {
                    // Ưu tiên dùng 'options' nếu có trong vardef
                    if (!empty($fieldDef['options'])) {
                        $optionKey = $fieldDef['options'];
                    } else {
                        // fallback danh sách thường gặp
                        $candidates = [
                            'record_type_display_notes',
                            'record_type_display',
                            'parent_type_display',
                        ];
                        foreach ($candidates as $key) {
                            if (isset($app_list_strings[$key])) {
                                $optionKey = $key;
                                break;
                            }
                        }
                    }
                } else {
                    $optionKey = $fieldDef['options'];
                }

                $values = $optionKey ? ($app_list_strings[$optionKey] ?? null) : null;

                if ($values) {
                    $results[$fieldName] = [
                        'options_key' => $optionKey,
                        'values' => $values,
                    ];
                } else {
                    $results[$fieldName] = [
                        'success' => false,
                        'message' => "Option for '{$fieldName}' not found in language '{$lang}'",
                    ];
                }
            } else {
                $results[$fieldName] = [
                    'success' => false,
                    'message' => "Field '{$fieldName}' is not an enum type",
                ];
            }
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'module' => $moduleName,
            'lang' => $lang,
            'fields' => $results,
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
