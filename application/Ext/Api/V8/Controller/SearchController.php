<?php
namespace Api\V8\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

class SearchController
{
    private $db;

    public function __construct()
    {
        global $db;
        $this->db = $db;
    }

    /**
     * API tìm kiếm theo keyword trong module
     * GET /api/v8/{module}?keyword={keyword}
     */
    public function searchModule(Request $request, Response $response, array $args)
    {
        $module = strtolower($args['module'] ?? ''); // Luôn chuyển thành chữ thường
        $queryParams = $request->getQueryParams();
        $keyword = $queryParams['keyword'] ?? '';

        if (!$module) {
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json')
                            ->write(json_encode(['error' => 'Missing module parameter']));
        }

        if (!$keyword) {
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json')
                            ->write(json_encode(['error' => 'Missing keyword parameter']));
        }

        try {
            // Validation
            if (!$this->isValidModule($module)) {
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json')
                                ->write(json_encode(['error' => 'Invalid module name']));
            }

            $searchResults = $this->performSearch($module, $keyword);
            
            $result = [
                'module' => $module,
                'keyword' => $keyword,
                'data' => $searchResults,
                'meta' => [
                    'total_count' => count($searchResults),
                    'timestamp' => date('Y-m-d H:i:s'),
                    'endpoint' => "/{$module}?keyword={$keyword}"
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
     * Thực hiện tìm kiếm dựa trên module và keyword
     */
    private function performSearch($module, $keyword)
    {
        $tableName = $this->getTableName($module);
        $searchFields = $this->getSearchFields($module);
        
        if (!$tableName || empty($searchFields)) {
            return [];
        }

        // Tạo WHERE clause cho tìm kiếm
        $whereConditions = [];
        
        // Fix SQL escaping - dùng manual escaping thay vì db->quote()
        $escapedKeyword = "'" . $this->db->quote($keyword) . "'";
        $likeKeyword = "'%" . $this->db->quote($keyword) . "%'";
        
        // Tìm kiếm trong các trường thông thường
        foreach ($searchFields as $field) {
            $whereConditions[] = "{$field} LIKE {$likeKeyword}";
        }

        // Xử lý đặc biệt cho email nếu keyword có dạng email
        if ($this->isEmail($keyword)) {
            $emailCondition = $this->buildEmailSearchCondition($module, $keyword);
            if ($emailCondition) {
                $whereConditions[] = $emailCondition;
            }
        }

        $whereClause = implode(' OR ', $whereConditions);
        
        // Tạo câu SQL chính với debug info
        $sql = "SELECT * FROM {$tableName} WHERE ({$whereClause}) AND deleted = 0 LIMIT 100";
        
        // Debug: Log SQL query
        error_log("Search SQL: " . $sql);
        
        $result = $this->db->query($sql);
        $searchResults = [];

        if (!$result) {
            // Debug: Log DB error
            error_log("DB Error: " . $this->db->lastError());
            return [];
        }

        while ($row = $this->db->fetchByAssoc($result)) {
            $searchResults[] = $row;
        }

        return $searchResults;
    }

    /**
     * Lấy tên bảng dựa trên module
     */
    private function getTableName($module)
    {
        $tableMapping = [
            'tasks' => 'tasks',
            'meetings' => 'meetings',
            'users' => 'users',
            'notes' => 'notes',
            'accounts' => 'accounts',
            'contacts' => 'contacts',
            'leads' => 'leads',
            'opportunities' => 'opportunities',
            'calls' => 'calls',
            'emails' => 'emails',
            'projects' => 'project',
            'cases' => 'cases'
        ];

        return $tableMapping[$module] ?? null;
    }

    /**
     * Lấy danh sách trường để tìm kiếm dựa trên module
     */
    private function getSearchFields($module)
    {
        $fieldMapping = [
            'tasks' => ['name', 'id'],
            'meetings' => ['name', 'id'],
            'users' => ['user_name', 'first_name', 'last_name', 'id'],
            'notes' => ['name', 'description', 'id'],
            'accounts' => ['name', 'id', 'phone_office', 'phone_alternate', 'phone_fax'], // SuiteCRM accounts thường dùng 'name'
            'contacts' => ['first_name', 'last_name', 'id', 'phone_home', 'phone_work', 'phone_other', 'phone_fax', 'phone_mobile'],
            'leads' => ['first_name', 'last_name', 'company', 'id'],
            'opportunities' => ['name', 'id'],
            'calls' => ['name', 'id'],
            'emails' => ['name', 'id'],
            'projects' => ['name', 'id'],
            'cases' => ['name', 'id']
        ];

        return $fieldMapping[$module] ?? ['name', 'id'];
    }

    /**
     * Kiểm tra xem keyword có phải là email không
     */
    private function isEmail($keyword)
    {
        return filter_var($keyword, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Tạo điều kiện tìm kiếm cho email
     */
    private function buildEmailSearchCondition($module, $keyword)
    {
        // Chỉ áp dụng cho accounts và contacts
        if (!in_array($module, ['accounts', 'contacts'])) {
            return null;
        }

        $tableName = $this->getTableName($module);
        $moduleCapitalized = ucfirst($module);
        if ($module === 'accounts') {
            $moduleCapitalized = 'Accounts';
        } elseif ($module === 'contacts') {
            $moduleCapitalized = 'Contacts';
        }

        // Fix SQL escaping
        $escapedKeyword = "'" . $this->db->quote($keyword) . "'";
        
        return "{$tableName}.id IN (
            SELECT eabr.bean_id
            FROM email_addr_bean_rel eabr
            JOIN email_addresses ea ON ea.id = eabr.email_address_id
            WHERE eabr.bean_module = '{$moduleCapitalized}'
              AND eabr.deleted = 0
              AND ea.deleted = 0
              AND ea.email_address LIKE {$escapedKeyword}
        )";
    }

    /**
     * Validate module name
     */
    private function isValidModule($module)
    {
        $validModules = [
            'tasks', 'meetings', 'users', 'notes', 'accounts', 
            'contacts', 'leads', 'opportunities', 'calls', 
            'emails', 'projects', 'cases'
        ];

        return in_array($module, $validModules);
    }
}
