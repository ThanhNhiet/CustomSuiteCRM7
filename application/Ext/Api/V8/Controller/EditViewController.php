<?php
namespace Api\V8\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class EditViewController
{
    public function getEditFields(Request $request, Response $response, array $args)
    {
        $module = $args['module'] ?? null;

        if (!$module) {
            $response->getBody()->write(json_encode(['error' => 'Missing module parameter']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $fields = $this->extractEditFields($module);
            $response->getBody()->write(json_encode($fields, JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    private function extractEditFields($module)
    {
        $fields = [];
        $viewdefs = [];

        $customPath = "custom/modules/{$module}/metadata/editviewdefs.php";
        $corePath = "modules/{$module}/metadata/editviewdefs.php";

        if (file_exists($customPath)) {
            include $customPath;
        } elseif (file_exists($corePath)) {
            include $corePath;
        } else {
            throw new \Exception("EditViewDefs file not found for module: {$module}");
        }

        if (!isset($viewdefs[$module]['EditView']['panels']) || !is_array($viewdefs[$module]['EditView']['panels'])) {
            throw new \Exception("Invalid editviewdefs structure for module: {$module}");
        }

        $panels = $viewdefs[$module]['EditView']['panels'];

        foreach ($panels as $panelKey => $panelData) {
            if (is_array($panelData)) {
                $this->processPanel($panelData, $fields);
            }
        }

        return $fields;
    }

    private function processPanel($panelData, &$fields)
    {
        foreach ($panelData as $row) {
            if (!is_array($row) || empty($row)) continue;

            foreach ($row as $fieldData) {
                if (is_string($fieldData) && !empty($fieldData)) {
                    // Trường chỉ có tên, không có nhãn -> sinh label mặc định
                    $fields[$fieldData] = 'LBL_' . strtoupper($fieldData);
                } elseif (is_array($fieldData) && isset($fieldData['name'])) {
                    $fieldName = $fieldData['name'];
                    $label = "";

                    if (!empty($fieldData['customLabel']) && preg_match("/label='([^']+)'/", $fieldData['customLabel'], $matches)) {
                        $label = $matches[1];
                    } elseif (!empty($fieldData['label'])) {
                        $label = $fieldData['label'];
                    } else {
                        // Nếu không có label, sinh label mặc định
                        $label = 'LBL_' . strtoupper($fieldName);
                    }

                    $fields[$fieldName] = $label;
                }
            }
        }
    }
}
