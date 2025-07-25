<?php
namespace Api\V8\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class DetailViewController
{
    /**
     * Lấy các field từ detailviewdefs của module
     */
    public function getDetailFields(Request $request, Response $response, array $args)
    {
        $module = $args['module'] ?? null;
        
        if (!$module) {
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json')
                            ->write(json_encode(['error' => 'Missing module parameter']));
        }

        try {
            $fields = $this->extractDetailFields($module);
            
            $result = [
                'module' => $module,
                'fields' => $fields,
                'count' => count($fields)
            ];

            return $response->withHeader('Content-Type', 'application/json')
                            ->write(json_encode($result));
            
        } catch (Exception $e) {
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json')
                            ->write(json_encode(['error' => $e->getMessage()]));
        }
    }

    /**
     * Trích xuất các field từ detailviewdefs panels
     */
    private function extractDetailFields($module)
    {
        $fields = [];
        $viewdefs = [];
        
        // Đường dẫn tới file detailviewdefs của module
        $customPath = "custom/modules/{$module}/metadata/detailviewdefs.php";
        $corePath = "modules/{$module}/metadata/detailviewdefs.php";
        
        // Ưu tiên file custom trước, sau đó mới đến file core
        if (file_exists($customPath)) {
            include $customPath;
        } elseif (file_exists($corePath)) {
            include $corePath;
        } else {
            throw new Exception("Detailviewdefs file not found for module: {$module}");
        }
        
        // Kiểm tra xem $viewdefs có được định nghĩa không
        if (!isset($viewdefs[$module]['DetailView']['panels']) || !is_array($viewdefs[$module]['DetailView']['panels'])) {
            throw new Exception("Invalid detailviewdefs structure for module: {$module}");
        }
        
        $panels = $viewdefs[$module]['DetailView']['panels'];
        
        // Lọc các field từ panels, bỏ qua LBL_PANEL_ASSIGNMENT
        foreach ($panels as $panelKey => $panelData) {
            // Bỏ qua panel assignment
            if (strtoupper($panelKey) === 'LBL_PANEL_ASSIGNMENT') {
                continue;
            }
            
            if (is_array($panelData)) {
                $this->processPanel($panelData, $fields);
            }
        }
        
        // Loại bỏ duplicate và return
        return array_values(array_unique($fields));
    }

    /**
     * Xử lý một panel để extract fields
     */
    private function processPanel($panelData, &$fields)
    {
        foreach ($panelData as $row) {
            if (is_array($row)) {
                foreach ($row as $fieldData) {
                    if (is_string($fieldData) && !empty($fieldData)) {
                        // Field đơn giản (string)
                        $fields[] = $fieldData;
                    } elseif (is_array($fieldData) && isset($fieldData['name']) && !empty($fieldData['name'])) {
                        // Field phức tạp (array với name)
                        $fields[] = $fieldData['name'];
                    }
                }
            }
        }
    }
}
