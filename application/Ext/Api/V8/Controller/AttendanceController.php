<?php
namespace Api\V8\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AttendanceController
{
    /**
     * Get attendance records by attendance_date and emp_no
     * 
     * Query: SELECT id FROM sgt_attendance WHERE attendance_date ='2026-03-26' AND emp_no='10004'
     * 
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function getAttendanceByDateAndEmployee(Request $request, Response $response, array $args)
    {
        try {
            $queryParams = $request->getQueryParams();
            
            // Validate required parameters
            if (empty($queryParams['attendance_date'])) {
                return $this->errorResponse($response, 'Parameter "attendance_date" is required', 400);
            }
            
            if (empty($queryParams['emp_no'])) {
                return $this->errorResponse($response, 'Parameter "emp_no" is required', 400);
            }
            
            $attendanceDate = $queryParams['attendance_date'];
            $empNo = $queryParams['emp_no'];
            
            // Validate date format (YYYY-MM-DD)
            if (!$this->isValidDate($attendanceDate)) {
                return $this->errorResponse($response, 'Invalid date format. Expected YYYY-MM-DD', 400);
            }
            
            // Query the database
            $sql = "SELECT id, emp_no, attendance_date 
                    FROM sgt_attendance 
                    WHERE attendance_date = '{$attendanceDate}' 
                    AND emp_no = '{$empNo}' AND deleted = 0";
            
            global $db;
            $result = $db->query($sql, true);
            
            $records = [];
            while ($row = $db->fetchByAssoc($result)) {
                $records[] = $row;
            }
            
            // Return response
            $responseData = [
                'success' => true,
                'data' => $records,
                'count' => count($records),
                'filters' => [
                    'attendance_date' => $attendanceDate,
                    'emp_no' => $empNo
                ]
            ];
            
            $response->getBody()->write(json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error retrieving attendance records: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get attendance records by week with pagination
     * Returns attendance records for the entire week containing the given date
     * Supports week offset to navigate between weeks
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
            
            if (empty($queryParams['attendance_date'])) {
                return $this->errorResponse($response, 'Parameter "attendance_date" is required', 400);
            }
            
            $attendanceDate = $queryParams['attendance_date'];
            $weekOffset = isset($queryParams['week_offset']) ? (int)$queryParams['week_offset'] : 0;
            
            if (!$this->isValidDate($attendanceDate)) {
                return $this->errorResponse($response, 'Invalid date format. Expected YYYY-MM-DD', 400);
            }
            
            // Parse the date and calculate week boundaries
            $date = new \DateTime($attendanceDate);
            
            // Apply week offset (each offset = 7 days)
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
            
            $weekStart = $mondayDate->format('Y-m-d');
            $weekEnd = $sundayDate->format('Y-m-d');
            
            // Query the database for the entire week
            $sql = "SELECT id, emp_no, attendance_date,time_in, time_out 
                    FROM sgt_attendance 
                    WHERE attendance_date >= '{$weekStart}' 
                    AND attendance_date <= '{$weekEnd}'
                    AND deleted = 0
                    ORDER BY attendance_date ASC, emp_no ASC";
            
            global $db;
            $result = $db->query($sql, true);
            
            $records = [];
            while ($row = $db->fetchByAssoc($result)) {
                $records[] = $row;
            }
            
            // Calculate previous and next week dates
            $prevWeekDates = $this->getWeekDates($weekStart, -1);
            $nextWeekDates = $this->getWeekDates($weekStart, 1);
            
            // Get base URL for navigation links
            $baseUrl = '/Api/V8/custom/attendance/getByDate';
            $prevUrl = $baseUrl . '?attendance_date=' . $prevWeekDates['start'];
            $nextUrl = $baseUrl . '?attendance_date=' . $nextWeekDates['start'];
            
            $responseData = [
                'success' => true,
                'data' => $records,
                'count' => count($records),
                'week_info' => [
                    'week_start' => $weekStart,
                    'week_end' => $weekEnd,
                    'current_week_offset' => $weekOffset
                ],
                'prev' => $prevUrl,
                'next' => $nextUrl,
                'pagination' => [
                    'prev_week_dates' => $prevWeekDates,
                    'next_week_dates' => $nextWeekDates
                ]
            ];
            
            $response->getBody()->write(json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error retrieving attendance records: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get attendance records by emp_no only
     * 
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function getAttendanceByEmployee(Request $request, Response $response, array $args)
    {
        try {
            $queryParams = $request->getQueryParams();
            
            if (empty($queryParams['emp_no'])) {
                return $this->errorResponse($response, 'Parameter "emp_no" is required', 400);
            }
            
            $empNo = $queryParams['emp_no'];
            
            // Optional: add limit to prevent large datasets
            $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 100;
            
            $sql = "SELECT id, emp_no, attendance_date 
                    FROM sgt_attendance 
                    WHERE emp_no = '{$empNo}'
                    ORDER BY attendance_date DESC
                    LIMIT {$limit}";
            
            global $db;
            $result = $db->query($sql, true);
            
            $records = [];
            while ($row = $db->fetchByAssoc($result)) {
                $records[] = $row;
            }
            
            $responseData = [
                'success' => true,
                'data' => $records,
                'count' => count($records),
                'filters' => [
                    'emp_no' => $empNo,
                    'limit' => $limit
                ]
            ];
            
            $response->getBody()->write(json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error retrieving attendance records: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Calculate week start and end dates based on offset
     * 
     * @param string $dateString Base date in YYYY-MM-DD format
     * @param int $weekOffset Number of weeks to offset
     * @return array With keys 'start' and 'end'
     */
    private function getWeekDates($dateString, $weekOffset = 0)
    {
        $date = new \DateTime($dateString);
        
        if ($weekOffset != 0) {
            $date->modify($weekOffset . ' week');
        }
        
        $dayOfWeek = $date->format('N');
        $daysToMonday = $dayOfWeek - 1;
        $mondayDate = clone $date;
        $mondayDate->modify('-' . $daysToMonday . ' day');
        
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
