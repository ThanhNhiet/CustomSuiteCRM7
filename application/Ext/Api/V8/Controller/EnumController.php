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

            if (!isset($bean->field_defs[$fieldName])) {
                $results[$fieldName] = [
                    'success' => false,
                    'message' => "Field '{$fieldName}' not found in module '{$moduleName}'",
                ];
                continue;
            }

            $fieldDef = $bean->field_defs[$fieldName];

            // Nếu là enum và có options
            if (($fieldDef['type'] ?? '') === 'enum' && !empty($fieldDef['options'])) {
                $optionKey = $fieldDef['options'];
                $values = $app_list_strings[$optionKey] ?? null;

                if ($values) {
                    $results[$fieldName] = [
                        'options_key' => $optionKey,
                        'values' => $values,
                    ];
                } else {
                    $results[$fieldName] = [
                        'message' => "Option '{$optionKey}' not found in language '{$lang}'",
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
