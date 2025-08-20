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
     * GET /api/v8/{module}?keyword={keyword}&fields={fields}&page={page}&limit={limit}
     */
    public function searchModule(Request $request, Response $response, array $args)
    {
        $module = strtolower($args['module'] ?? ''); // Luôn chuyển thành chữ thường
        $queryParams = $request->getQueryParams();
        $keyword = $queryParams['keyword'] ?? '';
        $fieldsParam = $queryParams['fields'] ?? ''; // Thêm parameter fields
        $page = (int)($queryParams['page'] ?? 1); // Page number (bắt đầu từ 1)
        $limit = (int)($queryParams['limit'] ?? 5); // Default 5 records per page
        // Đọc filter dạng filter[field]=value
        $filters = [];
        foreach ($queryParams as $key => $value) {
            if (preg_match('/^filter\[(.+)\]$/', $key, $matches)) {
                $filters[$matches[1]] = $value;
            }
        }

        if (!$module) {
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json')
                            ->write(json_encode(['error' => 'Missing module parameter']));
        }

        if (!$keyword) {
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json')
                            ->write(json_encode(['error' => 'Missing keyword parameter']));
        }

        // Validate pagination parameters
        if ($page < 1) $page = 1;
        if ($limit < 1 || $limit > 100) $limit = 5; // Default 5 records per page, max 100

        try {
            // Validation
            if (!$this->isValidModule($module)) {
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json')
                                ->write(json_encode(['error' => 'Invalid module name']));
            }

            // Parse fields parameter
            $searchFields = $this->parseFields($module, $fieldsParam);

            // Calculate offset for pagination
            $offset = ($page - 1) * $limit;

            $searchResults = $this->performSearch($module, $keyword, $searchFields, $limit, $offset, $filters);
            $totalCount = $this->getTotalSearchCount($module, $keyword, $searchFields, $filters);
            $totalPages = ceil($totalCount / $limit);
            
            $result = [
                'module' => $module,
                'keyword' => $keyword,
                'fields' => $searchFields,
                'data' => $searchResults,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total_records' => $totalCount,
                    'total_pages' => $totalPages,
                    'has_next' => $page < $totalPages,
                    'has_prev' => $page > 1
                ],
                'meta' => [
                    'total_count' => count($searchResults),
                    'timestamp' => date('Y-m-d H:i:s'),
                    'endpoint' => "/{$module}?fields=" . implode(',', $searchFields) . "&keyword={$keyword}&page={$page}&limit={$limit}"
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
     * Parse fields parameter hoặc sử dụng fields mặc định
     * Luôn bao gồm id, name và date_entered trong kết quả
     */
    private function parseFields($module, $fieldsParam)
    {
        $requiredFields = ['id', 'date_entered']; // Luôn bao gồm id và date_entered
        
        // Thêm name field tùy theo module
        $nameField = $this->getNameField($module);
        if ($nameField && !in_array($nameField, $requiredFields)) {
            $requiredFields[] = $nameField;
        }
        
        if (!empty($fieldsParam)) {
            // Parse fields từ parameter: "id,name,phone_mobile" -> ['id', 'name', 'phone_mobile']
            $customFields = array_map('trim', explode(',', $fieldsParam));
            $customFields = array_filter($customFields); // Loại bỏ empty strings
            
            // Merge với required fields và loại bỏ duplicates
            $allFields = array_unique(array_merge($requiredFields, $customFields));
            return $allFields;
        }
        
        // Sử dụng fields mặc định nếu không có parameter, nhưng vẫn merge với required fields
        $defaultFields = $this->getSearchFields($module);
        return array_unique(array_merge($requiredFields, $defaultFields));
    }

    /**
     * Lấy tên trường name chính của module
     */
    private function getNameField($module)
    {
        $nameFieldMapping = [
            'accounts' => 'name',
            'contacts' => 'first_name', // Hoặc có thể combine first_name + last_name
            'leads' => 'first_name',
            'users' => 'user_name',
            'opportunities' => 'name',
            'tasks' => 'name',
            'meetings' => 'name',
            'calls' => 'name',
            'notes' => 'name',
            'emails' => 'name',
            'projects' => 'name',
            'cases' => 'name'
        ];

        return $nameFieldMapping[$module] ?? 'name';
    }

    /**
     * Thực hiện tìm kiếm dựa trên module, keyword và custom fields với pagination
     */
    private function performSearch($module, $keyword, $searchFields = null, $limit = 5, $offset = 0, $filters = [])
    {
        $tableName = $this->getTableName($module);
        
        // Sử dụng searchFields được truyền vào hoặc lấy mặc định
        if ($searchFields === null) {
            $searchFields = $this->getSearchFields($module);
        }
        
        if (!$tableName || empty($searchFields)) {
            return [];
        }

        // Tạo SELECT clause chỉ với các fields được yêu cầu
        $selectFields = implode(', ', array_map(function($field) {
            return "`{$field}`"; // Wrap field names in backticks for safety
        }, $searchFields));

        // Tạo WHERE clause cho tìm kiếm
        $whereConditions = [];
        
        // Fix SQL escaping - dùng manual escaping thay vì db->quote()
        $escapedKeyword = "'" . $this->db->quote($keyword) . "'";
        $likeKeyword = "'%" . $this->db->quote($keyword) . "%'";
        
        // Tìm kiếm trong các trường được chỉ định
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

        // Thêm filter vào WHERE clause nếu có
        $filterConditions = [];
        if (!empty($filters)) {
            foreach ($filters as $field => $value) {
                $filterConditions[] = "{$field} = '" . $this->db->quote($value) . "'";
            }
        }
        $whereClause = implode(' OR ', $whereConditions);
        $fullWhere = "({$whereClause}) AND deleted = 0";
        if (!empty($filterConditions)) {
            $fullWhere .= " AND " . implode(' AND ', $filterConditions);
        }
        // Tạo câu SQL chính với pagination và chỉ SELECT các fields được yêu cầu
        $sql = "SELECT {$selectFields} FROM {$tableName} WHERE {$fullWhere} LIMIT {$limit} OFFSET {$offset}";
        
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
     * Đếm tổng số records cho pagination
     */
    private function getTotalSearchCount($module, $keyword, $searchFields, $filters = [])
    {
        $tableName = $this->getTableName($module);
        
        if (!$tableName || empty($searchFields)) {
            return 0;
        }

        // Tạo WHERE clause cho tìm kiếm (giống performSearch)
        $whereConditions = [];
        $likeKeyword = "'%" . $this->db->quote($keyword) . "%'";
        
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

        // Thêm filter vào WHERE clause nếu có
        $filterConditions = [];
        if (!empty($filters)) {
            foreach ($filters as $field => $value) {
                $filterConditions[] = "{$field} = '" . $this->db->quote($value) . "'";
            }
        }
        $whereClause = implode(' OR ', $whereConditions);
        $fullWhere = "({$whereClause}) AND deleted = 0";
        if (!empty($filterConditions)) {
            $fullWhere .= " AND " . implode(' AND ', $filterConditions);
        }
        // Câu SQL đếm
        $sql = "SELECT COUNT(*) as total FROM {$tableName} WHERE {$fullWhere}";
        
        $result = $this->db->query($sql);
        if (!$result) {
            return 0;
        }

        $row = $this->db->fetchByAssoc($result);
        return (int)($row['total'] ?? 0);
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
