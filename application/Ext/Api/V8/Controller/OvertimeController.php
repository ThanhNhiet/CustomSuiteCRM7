<?php
namespace Api\V8\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class OvertimeController
{
    /**
     * Get overtime records by emp_no with pagination
     * Includes job_title, department, and division information
     * 
     * Query: SELECT * FROM sgt_overtime 
     *        LEFT JOIN sgt_job_title ON sgt_overtime.sgt_job_title_id_c = sgt_job_title.id
     *        LEFT JOIN sgt_departments ON sgt_overtime.sgt_departments_id_c = sgt_departments.id
     *        LEFT JOIN sgt_divisions ON sgt_overtime.sgt_divisions_id_c = sgt_divisions.id
     *        WHERE emp_no = '10002' AND deleted = 0
     * 
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function getOvertimeByEmployee(Request $request, Response $response, array $args)
    {

        try {
            $queryParams = $request->getQueryParams();
            
            // Validate required parameters - support both emp_no and ma_nv
            $empNo = $queryParams['emp_no'] ?? $queryParams['ma_nv'] ?? null;
            if (empty($empNo)) {
                return $this->errorResponse($response, 'Parameter "emp_no" or "ma_nv" is required', 400);
            }
            
            // Pagination parameters
            $page = isset($queryParams['page']) ? max(1, (int)$queryParams['page']) : 1;
            $limit = isset($queryParams['limit']) ? max(1, min((int)$queryParams['limit'], 100)) : 20;
            $offset = ($page - 1) * $limit;
            
            // Build WHERE clause
            $where = "WHERE sgt_overtime.emp_no = '{$empNo}' AND sgt_overtime.deleted = 0";
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM sgt_overtime {$where}";
            global $db;
            $countResult = $db->query($countSql, true);
            $countRow = $db->fetchByAssoc($countResult);
            $totalRecords = (int)$countRow['total'];
            
            // Get paginated data with LEFT JOINs for related tables
            $sql = "SELECT 
                        sgt_overtime.id,
                        sgt_overtime.name,
                        sgt_overtime.ot_date,
                        sgt_overtime.from_hour,
                        sgt_overtime.to_hour,
                        sgt_overtime.approval,
                        sgt_overtime.emp_no,
                        sgt_overtime.sgt_job_title_id_c,
                        sgt_job_title.name as job_title,
                        sgt_overtime.sgt_departments_id_c,
                        sgt_departments.name as department,
                        sgt_overtime.sgt_divisions_id_c,
                        sgt_divisions.name as division,
                        sgt_overtime.date_entered,
                        sgt_overtime.date_modified,
                        sgt_overtime.assigned_user_id,
                        sgt_overtime.created_by
                    FROM sgt_overtime
                    LEFT JOIN sgt_job_title ON sgt_overtime.sgt_job_title_id_c = sgt_job_title.id
                    LEFT JOIN sgt_departments ON sgt_overtime.sgt_departments_id_c = sgt_departments.id
                    LEFT JOIN sgt_divisions ON sgt_overtime.sgt_divisions_id_c = sgt_divisions.id
                    {$where}
                    ORDER BY sgt_overtime.ot_date DESC
                    LIMIT {$limit} OFFSET {$offset}";
            
            $result = $db->query($sql, true);
            
            $records = [];
            while ($row = $db->fetchByAssoc($result)) {
                $records[] = $row;
            }
            
            // Calculate pagination metadata
            $totalPages = ceil($totalRecords / $limit);
            
            // Return response
            $responseData = [
                'success' => true,
                'data' => $records,
                'pagination' => [
                    'currentPage' => $page,
                    'pageSize' => $limit,
                    'totalRecords' => $totalRecords,
                    'totalPages' => $totalPages,
                    'hasNextPage' => $page < $totalPages,
                    'hasPrevPage' => $page > 1
                ],
                'filters' => [
                    'emp_no' => $empNo
                ]
            ];
            
            $response->getBody()->write(json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error retrieving overtime records: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get overtime records by date range with pagination
     * 
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function getOvertimeByDateRange(Request $request, Response $response, array $args)
    {
        try {
            $queryParams = $request->getQueryParams();
            
            // Validate required parameters
            if (empty($queryParams['from_date'])) {
                return $this->errorResponse($response, 'Parameter "from_date" is required', 400);
            }
            
            if (empty($queryParams['to_date'])) {
                return $this->errorResponse($response, 'Parameter "to_date" is required', 400);
            }
            
            $fromDate = $queryParams['from_date'];
            $toDate = $queryParams['to_date'];
            
            // Validate date format
            if (!$this->isValidDate($fromDate)) {
                return $this->errorResponse($response, 'Invalid "from_date" format. Expected YYYY-MM-DD', 400);
            }
            
            if (!$this->isValidDate($toDate)) {
                return $this->errorResponse($response, 'Invalid "to_date" format. Expected YYYY-MM-DD', 400);
            }
            
            // Pagination parameters
            $page = isset($queryParams['page']) ? max(1, (int)$queryParams['page']) : 1;
            $limit = isset($queryParams['limit']) ? max(1, min((int)$queryParams['limit'], 100)) : 20;
            $offset = ($page - 1) * $limit;
            
            // Build WHERE clause
            $where = "WHERE ot_date >= '{$fromDate}' AND ot_date <= '{$toDate}' AND deleted = 0";
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM sgt_overtime {$where}";
            global $db;
            $countResult = $db->query($countSql, true);
            $countRow = $db->fetchByAssoc($countResult);
            $totalRecords = (int)$countRow['total'];
            
            // Get paginated data
            $sql = "SELECT * FROM sgt_overtime {$where} ORDER BY ot_date DESC LIMIT {$limit} OFFSET {$offset}";
            
            $result = $db->query($sql, true);
            
            $records = [];
            while ($row = $db->fetchByAssoc($result)) {
                $records[] = $row;
            }
            
            // Calculate pagination metadata
            $totalPages = ceil($totalRecords / $limit);
            
            // Return response
            $responseData = [
                'success' => true,
                'data' => $records,
                'pagination' => [
                    'currentPage' => $page,
                    'pageSize' => $limit,
                    'totalRecords' => $totalRecords,
                    'totalPages' => $totalPages,
                    'hasNextPage' => $page < $totalPages,
                    'hasPrevPage' => $page > 1
                ],
                'filters' => [
                    'from_date' => $fromDate,
                    'to_date' => $toDate
                ]
            ];
            
            $response->getBody()->write(json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error retrieving overtime records: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get overtime records by emp_no and date range with pagination
     * 
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function getOvertimeByEmployeeAndDateRange(Request $request, Response $response, array $args)
    {
        try {
            $queryParams = $request->getQueryParams();
            
            // Validate required parameters - support both emp_no and ma_nv
            $empNo = $queryParams['emp_no'] ?? $queryParams['ma_nv'] ?? null;
            if (empty($empNo)) {
                return $this->errorResponse($response, 'Parameter "emp_no" or "ma_nv" is required', 400);
            }
            
            if (empty($queryParams['from_date'])) {
                return $this->errorResponse($response, 'Parameter "from_date" is required', 400);
            }
            
            if (empty($queryParams['to_date'])) {
                return $this->errorResponse($response, 'Parameter "to_date" is required', 400);
            }
            
            $fromDate = $queryParams['from_date'];
            $toDate = $queryParams['to_date'];
            
            // Validate date format
            if (!$this->isValidDate($fromDate)) {
                return $this->errorResponse($response, 'Invalid "from_date" format. Expected YYYY-MM-DD', 400);
            }
            
            if (!$this->isValidDate($toDate)) {
                return $this->errorResponse($response, 'Invalid "to_date" format. Expected YYYY-MM-DD', 400);
            }
            
            // Pagination parameters
            $page = isset($queryParams['page']) ? max(1, (int)$queryParams['page']) : 1;
            $limit = isset($queryParams['limit']) ? max(1, min((int)$queryParams['limit'], 100)) : 20;
            $offset = ($page - 1) * $limit;
            
            // Build WHERE clause
            $where = "WHERE emp_no = '{$empNo}' AND ot_date >= '{$fromDate}' AND ot_date <= '{$toDate}' AND deleted = 0";
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM sgt_overtime {$where}";
            global $db;
            $countResult = $db->query($countSql, true);
            $countRow = $db->fetchByAssoc($countResult);
            $totalRecords = (int)$countRow['total'];
            
            // Get paginated data
            $sql = "SELECT * FROM sgt_overtime {$where} ORDER BY ot_date DESC LIMIT {$limit} OFFSET {$offset}";
            
            $result = $db->query($sql, true);
            
            $records = [];
            while ($row = $db->fetchByAssoc($result)) {
                $records[] = $row;
            }
            
            // Calculate pagination metadata
            $totalPages = ceil($totalRecords / $limit);
            
            // Return response
            $responseData = [
                'success' => true,
                'data' => $records,
                'pagination' => [
                    'currentPage' => $page,
                    'pageSize' => $limit,
                    'totalRecords' => $totalRecords,
                    'totalPages' => $totalPages,
                    'hasNextPage' => $page < $totalPages,
                    'hasPrevPage' => $page > 1
                ],
                'filters' => [
                    'emp_no' => $empNo,
                    'from_date' => $fromDate,
                    'to_date' => $toDate
                ]
            ];
            
            $response->getBody()->write(json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error retrieving overtime records: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Validate date format (YYYY-MM-DD)
     * 
     * @param string $date
     * @return bool
     */
    private function isValidDate($date)
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
    
    /**
     * Return error response
     * 
     * @param Response $response
     * @param string $message
     * @param int $statusCode
     * @return Response
     */
    private function errorResponse($response, $message, $statusCode = 400)
    {
        $responseData = [
            'success' => false,
            'message' => $message
        ];
        
        $response->getBody()->write(json_encode($responseData, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
    }
}
