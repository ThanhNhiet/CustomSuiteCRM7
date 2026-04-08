<?php
namespace Api\V8\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class LeaveController
{
    /**
     * Get leave records by ma_nv with pagination
     * 
     * Query: SELECT id, name, ma_nv, tungay, denngay, loai_vm, tinhtrang, tongngay, assigned_user_id 
     *        FROM sgt_leave WHERE ma_nv = '10002' AND deleted = 0
     * 
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function getLeaveByEmployee(Request $request, Response $response, array $args)
    {
        try {
            $queryParams = $request->getQueryParams();
            
            // Validate required parameters
            if (empty($queryParams['ma_nv'])) {
                return $this->errorResponse($response, 'Parameter "ma_nv" is required', 400);
            }
            
            $maNv = $queryParams['ma_nv'];
            
            // Pagination parameters
            $page = isset($queryParams['page']) ? max(1, (int)$queryParams['page']) : 1;
            $limit = isset($queryParams['limit']) ? max(1, min((int)$queryParams['limit'], 100)) : 20;
            $offset = ($page - 1) * $limit;
            
            // Build WHERE clause
            $where = "WHERE ma_nv = '{$maNv}' AND deleted = 0";
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM sgt_leave {$where}";
            global $db;
            $countResult = $db->query($countSql, true);
            $countRow = $db->fetchByAssoc($countResult);
            $totalRecords = (int)$countRow['total'];
            
            // Get paginated data
            $sql = "SELECT id, name, ma_nv, tungay, denngay, loai_vm, tinhtrang, tongngay, assigned_user_id 
                    FROM sgt_leave 
                    {$where}
                    ORDER BY tungay DESC
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
                    'ma_nv' => $maNv
                ]
            ];
            
            $response->getBody()->write(json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error retrieving leave records: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get leave records by ma_nv and tinhtrang = 'daduyet' for the week containing the given date
     * Returns all approved leave records for an employee during the Monday-Sunday week containing the specified date
     * 
     * Query: SELECT id, name, ma_nv, tungay, denngay, loai_vm, tinhtrang, tongngay, assigned_user_id 
     *        FROM sgt_leave WHERE ma_nv = '10002' AND deleted = 0 AND tinhtrang = 'daduyet'
     *        AND tungay >= 'YYYY-MM-DD' AND denngay <= 'YYYY-MM-DD'
     * 
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function getLeaveByEmployeeAndStatus(Request $request, Response $response, array $args)
    {
        try {
            $queryParams = $request->getQueryParams();
            
            // Validate required parameters
            if (empty($queryParams['ma_nv'])) {
                return $this->errorResponse($response, 'Parameter "ma_nv" is required', 400);
            }
            
            if (empty($queryParams['leave_date'])) {
                return $this->errorResponse($response, 'Parameter "leave_date" is required', 400);
            }
            
            $maNv = $queryParams['ma_nv'];
            $leaveDate = $queryParams['leave_date'];
            $tinhTrang = $queryParams['tinhtrang'] ?? 'daduyet'; // Default to 'daduyet' if not provided
            
            // Validate date format
            if (!$this->isValidDate($leaveDate)) {
                return $this->errorResponse($response, 'Invalid date format. Expected YYYY-MM-DD', 400);
            }
            
            // Parse the date and calculate week boundaries
            $date = new \DateTime($leaveDate);
            
            // Get Monday of this week (start of week)
            $dayOfWeek = $date->format('N'); // 1 = Monday, 7 = Sunday
            $daysToMonday = $dayOfWeek - 1;
            $mondayDate = clone $date;
            $mondayDate->modify('-' . $daysToMonday . ' day');
            
            // Get Sunday of this week (end of week)
            $sundayDate = clone $mondayDate;
            $sundayDate->modify('+6 day');
            
            $weekStart = $mondayDate->format('Y-m-d');
            $weekEnd = $sundayDate->format('Y-m-d');
            
            // Pagination parameters
            $page = isset($queryParams['page']) ? max(1, (int)$queryParams['page']) : 1;
            $limit = isset($queryParams['limit']) ? max(1, min((int)$queryParams['limit'], 100)) : 20;
            $offset = ($page - 1) * $limit;
            
            // Build WHERE clause - check if leave overlaps with the week
            $where = "WHERE ma_nv = '{$maNv}' 
                        AND deleted = 0 
                        AND tinhtrang = '{$tinhTrang}' 
                        AND tungay <= '{$leaveDate}' 
                        AND denngay >= '{$leaveDate}'";
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM sgt_leave {$where}";
            global $db;
            $countResult = $db->query($countSql, true);
            $countRow = $db->fetchByAssoc($countResult);
            $totalRecords = (int)$countRow['total'];
            
            // Get paginated data
            $sql = "SELECT id, name, ma_nv, tungay, denngay, loai_vm, tinhtrang, tongngay, assigned_user_id 
                    FROM sgt_leave 
                    {$where}
                    ORDER BY tungay ASC
                    LIMIT {$limit} OFFSET {$offset}";
            
            $result = $db->query($sql, true);
            
            $records = [];
            while ($row = $db->fetchByAssoc($result)) {
                $records[] = $row;
            }
            
            // Calculate pagination metadata
            $totalPages = ceil($totalRecords / $limit);
            
            // Calculate previous and next week dates
            $prevWeekDates = $this->getWeekDates($weekStart, -1);
            $nextWeekDates = $this->getWeekDates($weekStart, 1);
            
            // Get base URL for navigation links
            $baseUrl = '/Api/V8/custom/leave/getByEmployeeAndStatus';
            $prevUrl = $baseUrl . '?ma_nv=' . urlencode($maNv) . '&leave_date=' . $prevWeekDates['start'] . '&tinhtrang=' . urlencode($tinhTrang);
            $nextUrl = $baseUrl . '?ma_nv=' . urlencode($maNv) . '&leave_date=' . $nextWeekDates['start'] . '&tinhtrang=' . urlencode($tinhTrang);
            
            // Return response
            $responseData = [
                'success' => true,
                'data' => $records,
                'count' => count($records),
                'week_info' => [
                    'week_start' => $weekStart,
                    'week_end' => $weekEnd,
                    'leave_date' => $leaveDate
                ],
                'prev' => $prevUrl,
                'next' => $nextUrl,
                'pagination' => [
                    'currentPage' => $page,
                    'pageSize' => $limit,
                    'totalRecords' => $totalRecords,
                    'totalPages' => $totalPages,
                    'hasNextPage' => $page < $totalPages,
                    'hasPrevPage' => $page > 1,
                    'prev_week_dates' => $prevWeekDates,
                    'next_week_dates' => $nextWeekDates
                ],
                'filters' => [
                    'ma_nv' => $maNv,
                    'tinhtrang' => $tinhTrang
                ]
            ];
            
            $response->getBody()->write(json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error retrieving leave records: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get attendance records by week for an employee
     * Returns attendance records for the entire week containing the given date
     * Supports pagination
     * 
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function getAttendanceByDate(Request $request, Response $response, array $args)
    {
        try {
            $queryParams = $request->getQueryParams();
            
            // Validate required parameters
            if (empty($queryParams['ma_nv'])) {
                return $this->errorResponse($response, 'Parameter "ma_nv" is required', 400);
            }
            
            if (empty($queryParams['attendance_date'])) {
                return $this->errorResponse($response, 'Parameter "attendance_date" is required', 400);
            }
            
            $maNv = $queryParams['ma_nv'];
            $attendanceDate = $queryParams['attendance_date'];
            
            // Validate date format
            if (!$this->isValidDate($attendanceDate)) {
                return $this->errorResponse($response, 'Invalid date format. Expected YYYY-MM-DD', 400);
            }
            
            // Parse the date and calculate week boundaries
            $date = new \DateTime($attendanceDate);
            
            // Get Monday of this week (start of week)
            $dayOfWeek = $date->format('N'); // 1 = Monday, 7 = Sunday
            $daysToMonday = $dayOfWeek - 1;
            $mondayDate = clone $date;
            $mondayDate->modify('-' . $daysToMonday . ' day');
            
            // Get Sunday of this week (end of week)
            $sundayDate = clone $mondayDate;
            $sundayDate->modify('+6 day');
            
            $weekStart = $mondayDate->format('Y-m-d');
            $weekEnd = $sundayDate->format('Y-m-d');
            
            // Pagination parameters
            $page = isset($queryParams['page']) ? max(1, (int)$queryParams['page']) : 1;
            $limit = isset($queryParams['limit']) ? max(1, min((int)$queryParams['limit'], 100)) : 20;
            $offset = ($page - 1) * $limit;
            
            // Build WHERE clause
            $where = "WHERE ma_nv = '{$maNv}' AND deleted = 0 AND attendance_date >= '{$weekStart}' AND attendance_date <= '{$weekEnd}'";
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM sgt_attendance {$where}";
            global $db;
            $countResult = $db->query($countSql, true);
            $countRow = $db->fetchByAssoc($countResult);
            $totalRecords = (int)$countRow['total'];
            
            // Get paginated data
            $sql = "SELECT id, ma_nv, attendance_date, time_in, time_out 
                    FROM sgt_attendance 
                    {$where}
                    ORDER BY attendance_date ASC
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
                'count' => count($records),
                'pagination' => [
                    'currentPage' => $page,
                    'pageSize' => $limit,
                    'totalRecords' => $totalRecords,
                    'totalPages' => $totalPages,
                    'hasNextPage' => $page < $totalPages,
                    'hasPrevPage' => $page > 1
                ],
                'week_info' => [
                    'week_start' => $weekStart,
                    'week_end' => $weekEnd,
                    'attendance_date' => $attendanceDate
                ],
                'filters' => [
                    'ma_nv' => $maNv
                ]
            ];
            
            $response->getBody()->write(json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error retrieving attendance records: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get leave records by date range with pagination
     * 
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function getLeaveByDateRange(Request $request, Response $response, array $args)
    {
        try {
            $queryParams = $request->getQueryParams();
            
            // Validate required parameters
            if (empty($queryParams['tungay'])) {
                return $this->errorResponse($response, 'Parameter "tungay" (from date) is required', 400);
            }
            
            if (empty($queryParams['denngay'])) {
                return $this->errorResponse($response, 'Parameter "denngay" (to date) is required', 400);
            }
            
            $tuNgay = $queryParams['tungay'];
            $denNgay = $queryParams['denngay'];
            
            // Validate date format
            if (!$this->isValidDate($tuNgay)) {
                return $this->errorResponse($response, 'Invalid "tungay" format. Expected YYYY-MM-DD', 400);
            }
            
            if (!$this->isValidDate($denNgay)) {
                return $this->errorResponse($response, 'Invalid "denngay" format. Expected YYYY-MM-DD', 400);
            }
            
            // Pagination parameters
            $page = isset($queryParams['page']) ? max(1, (int)$queryParams['page']) : 1;
            $limit = isset($queryParams['limit']) ? max(1, min((int)$queryParams['limit'], 100)) : 20;
            $offset = ($page - 1) * $limit;
            
            // Build WHERE clause
            $where = "WHERE tungay >= '{$tuNgay}' AND denngay <= '{$denNgay}' AND deleted = 0";
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM sgt_leave {$where}";
            global $db;
            $countResult = $db->query($countSql, true);
            $countRow = $db->fetchByAssoc($countResult);
            $totalRecords = (int)$countRow['total'];
            
            // Get paginated data
            $sql = "SELECT id, name, ma_nv, tungay, denngay, loai_vm, tinhtrang, tongngay, assigned_user_id 
                    FROM sgt_leave 
                    {$where}
                    ORDER BY tungay DESC
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
                    'tungay' => $tuNgay,
                    'denngay' => $denNgay
                ]
            ];
            
            $response->getBody()->write(json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error retrieving leave records: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get leave records by ma_nv and date range with pagination
     * 
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function getLeaveByEmployeeAndDateRange(Request $request, Response $response, array $args)
    {
        try {
            $queryParams = $request->getQueryParams();
            
            // Validate required parameters
            if (empty($queryParams['ma_nv'])) {
                return $this->errorResponse($response, 'Parameter "ma_nv" is required', 400);
            }
            
            if (empty($queryParams['tungay'])) {
                return $this->errorResponse($response, 'Parameter "tungay" (from date) is required', 400);
            }
            
            if (empty($queryParams['denngay'])) {
                return $this->errorResponse($response, 'Parameter "denngay" (to date) is required', 400);
            }
            
            $maNv = $queryParams['ma_nv'];
            $tuNgay = $queryParams['tungay'];
            $denNgay = $queryParams['denngay'];
            
            // Validate date format
            if (!$this->isValidDate($tuNgay)) {
                return $this->errorResponse($response, 'Invalid "tungay" format. Expected YYYY-MM-DD', 400);
            }
            
            if (!$this->isValidDate($denNgay)) {
                return $this->errorResponse($response, 'Invalid "denngay" format. Expected YYYY-MM-DD', 400);
            }
            
            // Pagination parameters
            $page = isset($queryParams['page']) ? max(1, (int)$queryParams['page']) : 1;
            $limit = isset($queryParams['limit']) ? max(1, min((int)$queryParams['limit'], 100)) : 20;
            $offset = ($page - 1) * $limit;
            
            // Build WHERE clause
            $where = "WHERE ma_nv = '{$maNv}' AND tungay >= '{$tuNgay}' AND denngay <= '{$denNgay}' AND deleted = 0";
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM sgt_leave {$where}";
            global $db;
            $countResult = $db->query($countSql, true);
            $countRow = $db->fetchByAssoc($countResult);
            $totalRecords = (int)$countRow['total'];
            
            // Get paginated data
            $sql = "SELECT id, name, ma_nv, tungay, denngay, loai_vm, tinhtrang, tongngay, assigned_user_id 
                    FROM sgt_leave 
                    {$where}
                    ORDER BY tungay DESC
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
                    'ma_nv' => $maNv,
                    'tungay' => $tuNgay,
                    'denngay' => $denNgay
                ]
            ];
            
            $response->getBody()->write(json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error retrieving leave records: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get week dates (Monday and Sunday) for a given date and offset
     * 
     * @param string $dateStr Date in YYYY-MM-DD format
     * @param int $weekOffset Week offset (positive or negative)
     * @return array Array with 'start' and 'end' keys
     */
    private function getWeekDates($dateStr, $weekOffset = 0)
    {
        $date = new \DateTime($dateStr);
        
        // Apply week offset
        if ($weekOffset != 0) {
            $date->modify($weekOffset . ' week');
        }
        
        // Get Monday of this week (start of week)
        $dayOfWeek = $date->format('N'); // 1 = Monday, 7 = Sunday
        $daysToMonday = $dayOfWeek - 1;
        $mondayDate = clone $date;
        $mondayDate->modify('-' . $daysToMonday . ' day');
        
        // Get Sunday of this week (end of week)
        $sundayDate = clone $mondayDate;
        $sundayDate->modify('+6 day');
        
        return [
            'start' => $mondayDate->format('Y-m-d'),
            'end' => $sundayDate->format('Y-m-d')
        ];
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
