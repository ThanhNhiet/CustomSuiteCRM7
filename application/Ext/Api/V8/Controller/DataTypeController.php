<?php
namespace Api\V8\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class DataTypeController
{
    // Get enum
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
        
        // Kiểm tra custom vardefs trước
        $customVardefsFile = "custom/modules/{$moduleName}/Ext/Vardefs/vardefs.ext.php";
        $coreVardefsFile = "modules/{$moduleName}/vardefs.php";
        
        $customVardefs = [];
        if (file_exists($customVardefsFile)) {
            // Load custom vardefs file
            require_once($customVardefsFile);
            if (isset($GLOBALS['dictionary'][$moduleName]) || isset($GLOBALS['dictionary'][$beanList[$moduleName]])) {
                $objectName = isset($GLOBALS['dictionary'][$moduleName]) ? $moduleName : $beanList[$moduleName];
                $customVardefs = $GLOBALS['dictionary'][$objectName]['fields'] ?? [];
            }
        }
        
        // Nếu không có custom hoặc không tìm thấy field trong custom, thì dùng core
        if (empty($customVardefs) || (count($fields) > 0 && !$this->fieldsExistInVardefs($fields, $customVardefs))) {
            // Lấy bean (vardefs đã merge custom + core)
            $bean = \BeanFactory::newBean($moduleName);
            $fieldDefs = $bean->field_defs;
        } else {
            $fieldDefs = $customVardefs;
        }

        $results = [];
        foreach ($fields as $fieldName) {
            $fieldName = trim($fieldName);

            if (!isset($fieldDefs[$fieldName]) && $fieldName !== 'parent_type') {
                $results[$fieldName] = [
                    'success' => false,
                    'message' => "Field '{$fieldName}' not found in module '{$moduleName}'",
                ];
                continue;
            }

            $fieldDef = $fieldDefs[$fieldName] ?? [];
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

    //Get relate
    public function getModuleRelateType(Request $request, Response $response, array $args){
        $moduleName = $args['module'];
        $queryParams = $request->getQueryParams();
        $fields = isset($queryParams['fields']) ? explode(',', $queryParams['fields']) : [];

        global $beanList;

        // Check if module exists
        if (!isset($beanList[$moduleName])) {
            $result = [
                'success' => false,
                'message' => "Module '{$moduleName}' not found",
            ];
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        // Kiểm tra custom vardefs trước
        $customVardefsFile = "custom/modules/{$moduleName}/Ext/Vardefs/vardefs.ext.php";
        $coreVardefsFile = "modules/{$moduleName}/vardefs.php";
        
        $customVardefs = [];
        if (file_exists($customVardefsFile)) {
            // Load custom vardefs file
            require_once($customVardefsFile);
            if (isset($GLOBALS['dictionary'][$moduleName]) || isset($GLOBALS['dictionary'][$beanList[$moduleName]])) {
                $objectName = isset($GLOBALS['dictionary'][$moduleName]) ? $moduleName : $beanList[$moduleName];
                $customVardefs = $GLOBALS['dictionary'][$objectName]['fields'] ?? [];
            }
        }
        
        // Nếu không có custom hoặc không tìm thấy field trong custom, thì dùng core
        if (empty($customVardefs) || (count($fields) > 0 && !$this->fieldsExistInVardefs($fields, $customVardefs))) {
            // Tạo bean để lấy vardefs đã merge
            $bean = \BeanFactory::newBean($moduleName);
            if (!$bean) {
                $result = [
                    'success' => false,
                    'message' => "Could not create bean for module '{$moduleName}'",
                ];
                $response->getBody()->write(json_encode($result));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
            
            // Sử dụng field_defs từ bean
            $fieldDefs = $bean->field_defs;
        } else {
            $fieldDefs = $customVardefs;
        }
        
        $results = [];
        
        // Nếu không có field nào được chỉ định, lấy tất cả các field có thuộc tính link, table, module
        if (empty($fields)) {
            foreach ($fieldDefs as $fieldName => $fieldDef) {
                if (isset($fieldDef['type']) && $fieldDef['type'] === 'relate' && 
                    (!empty($fieldDef['link']) || !empty($fieldDef['table']) || !empty($fieldDef['module']))) {
                    
                    $results[$fieldName] = $this->extractRelationshipInfo($fieldDef);
                }
            }
        } else {
            // Nếu có field được chỉ định, chỉ lấy thông tin của các field đó
            foreach ($fields as $fieldName) {
                $fieldName = trim($fieldName);
                
                if (!isset($fieldDefs[$fieldName])) {
                    $results[$fieldName] = [
                        'success' => false,
                        'message' => "Field '{$fieldName}' not found in module '{$moduleName}'",
                    ];
                    continue;
                }
                
                $fieldDef = $fieldDefs[$fieldName];
                
                if (isset($fieldDef['type']) && $fieldDef['type'] === 'relate') {
                    $results[$fieldName] = $this->extractRelationshipInfo($fieldDef);
                } else {
                    $results[$fieldName] = [
                        'success' => false,
                        'message' => "Field '{$fieldName}' is not a relate type",
                    ];
                }
            }
        }
        
        $responseData = [
            'success' => true,
            'module' => $moduleName,
            'fields' => $results,
        ];
        
        $response->getBody()->write(json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * Kiểm tra xem các field có tồn tại trong vardefs không
     * 
     * @param array $fields Danh sách các field cần kiểm tra
     * @param array $vardefs Vardefs để kiểm tra
     * @return bool True nếu tất cả các field đều tồn tại trong vardefs
     */
    private function fieldsExistInVardefs($fields, $vardefs) {
        foreach ($fields as $field) {
            if (!isset($vardefs[$field])) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Trích xuất thông tin quan hệ từ field definition
     * 
     * @param array $fieldDef Field definition
     * @return array Thông tin quan hệ
     */
    private function extractRelationshipInfo($fieldDef) {
        $info = [
            'success' => true
        ];
        
        // Lấy module
        if (!empty($fieldDef['module'])) {
            $info['module_relate'] = $fieldDef['module'];
        }
        
        // Lấy link
        if (!empty($fieldDef['link'])) {
            $info['link'] = $fieldDef['link'];
        }
        
        // Lấy table
        if (!empty($fieldDef['table'])) {
            $info['table'] = $fieldDef['table'];
        }
        
        return $info;
    }
}
