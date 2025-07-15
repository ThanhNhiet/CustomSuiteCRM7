<?php
namespace Api\V8\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ListViewController
{
    /**
     * Lấy các field có default = true từ listviewdefs của module
     */
    public function getDefaultFields(Request $request, Response $response, array $args)
    {
        $module = $args['module'] ?? null;
        
        if (!$module) {
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json')
                            ->write(json_encode(['error' => 'Missing module parameter']));
        }

        try {
            $defaultFields = $this->extractDefaultFields($module);
            
            $result = [
                'module' => $module,
                'default_fields' => $defaultFields,
                'count' => count($defaultFields)
            ];

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (Exception $e) {
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json')
                            ->write(json_encode(['error' => $e->getMessage()]));
        }
    }

    /**
     * Trích xuất các field có default = true từ listviewdefs
     */
    private function extractDefaultFields($module)
    {
        $defaultFields = [];
        $listViewDefs = [];
        
        // Đường dẫn tới file listviewdefs của module
        $customPath = "custom/modules/{$module}/metadata/listviewdefs.php";
        $corePath = "modules/{$module}/metadata/listviewdefs.php";
        
        // Ưu tiên file custom trước, sau đó mới đến file core
        if (file_exists($customPath)) {
            include $customPath;
        } elseif (file_exists($corePath)) {
            include $corePath;
        } else {
            throw new Exception("Listviewdefs file not found for module: {$module}");
        }
        
        // Kiểm tra xem $listViewDefs có được định nghĩa không
        if (!isset($listViewDefs[$module]) || !is_array($listViewDefs[$module])) {
            throw new Exception("Invalid listviewdefs structure for module: {$module}");
        }
        
        // Lọc các field có default = true
        foreach ($listViewDefs[$module] as $fieldName => $fieldDef) {
            if (isset($fieldDef['default']) && $fieldDef['default'] === true) {
                $defaultFields[$fieldName] = [
                    'label' => $fieldDef['label'] ?? $fieldName,
                    'width' => $fieldDef['width'] ?? 'auto',
                    'type' => $fieldDef['type'] ?? 'varchar',
                    'link' => $fieldDef['link'] ?? false
                ];
            }
        }
        
        return $defaultFields;
    }
}