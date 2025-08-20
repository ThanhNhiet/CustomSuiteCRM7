<?php

namespace Api\V8\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Include các class của SuiteCRM
require_once 'include/database/DBManagerFactory.php';

class SecurityGroupController
{
    public function getMembers(Request $request, Response $response, array $args): Response
    {
        $groupId = $args['group_id'] ?? null;

        if (!$groupId) {
            return $this->jsonResponse($response, ['error' => 'Missing group_id'], 400);
        }

        $db = \DBManagerFactory::getInstance();
        
        // Sửa lại cách quote để đảm bảo có dấu ngoặc kép
        $groupIdQuoted = "'" . $db->quote($groupId) . "'";

        // Lấy thông tin security group
        $sqlGroup = "SELECT id, name, description FROM securitygroups WHERE id = {$groupIdQuoted} AND deleted = 0";
        $resultGroup = $db->query($sqlGroup);
        $group = $db->fetchByAssoc($resultGroup);

        if (!$group) {
            return $this->jsonResponse($response, ['error' => 'Security Group not found'], 404);
        }

        // Lấy danh sách thành viên - chỉ lấy id và name
        $sqlMembers = "SELECT u.id, u.user_name, u.first_name, u.last_name
                       FROM securitygroups_users sgu
                       LEFT JOIN users u ON sgu.user_id = u.id
                       WHERE sgu.securitygroup_id = {$groupIdQuoted}
                       AND sgu.deleted = 0
                       ORDER BY u.user_name ASC";
        $resultMembers = $db->query($sqlMembers);

        $members = [];

        while ($row = $db->fetchByAssoc($resultMembers)) {
            // Chỉ thêm nếu user tồn tại
            if ($row['id']) {
                $fullName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                $members[] = [
                    'id' => $row['id'],
                    'name' => $fullName ?: ($row['user_name'] ?? 'Unknown User')
                ];
            }
        }

        // Trả về JSON với format chính xác như yêu cầu
        return $this->jsonResponse($response, [
            'group_id' => $groupId,
            'group_name' => $group['name'],
            'group_description' => $group['description'] ?? '',
            'members' => $members,
            'total_members' => count($members),
            'meta' => [
                'timestamp' => date('Y-m-d H:i:s'),
                'endpoint' => "/security-groups/{$groupId}/members"
            ]
        ]);
    }

    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
