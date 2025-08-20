<?php

namespace Api\V8\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Include các class của SuiteCRM
require_once 'modules/ACLRoles/ACLRole.php';
require_once 'modules/ACLActions/ACLAction.php';
require_once 'include/database/DBManagerFactory.php';

class RoleUserController
{
    // ACL Access Level Constants
    const ACL_ALLOW_ADMIN_DEV = 100;
    const ACL_ALLOW_ADMIN = 99;
    const ACL_ALLOW_ALL = 90;
    const ACL_ALLOW_ENABLED = 89;
    const ACL_ALLOW_DEV = 95;
    const ACL_ALLOW_OWNER = 75;
    const ACL_ALLOW_NORMAL = 1;
    const ACL_ALLOW_DEFAULT = 0;
    const ACL_ALLOW_DISABLED = -98;
    const ACL_ALLOW_NONE = -99;

    private function getACLLevelName($level): string
    {
        $levels = [
            self::ACL_ALLOW_ADMIN_DEV => 'ADMIN_DEV',
            self::ACL_ALLOW_ADMIN => 'ADMIN',
            self::ACL_ALLOW_DEV => 'DEV',
            self::ACL_ALLOW_ALL => 'ALL',
            self::ACL_ALLOW_ENABLED => 'ENABLED',
            self::ACL_ALLOW_OWNER => 'OWNER',
            self::ACL_ALLOW_NORMAL => 'NORMAL',
            self::ACL_ALLOW_DEFAULT => 'DEFAULT',
            self::ACL_ALLOW_DISABLED => 'DISABLED',
            self::ACL_ALLOW_NONE => 'NONE'
        ];
        
        return $levels[$level] ?? 'UNKNOWN';
    }

    public function getUserRoles(Request $request, Response $response, array $args): Response
    {
        $userId = $args['user_id'] ?? null;

        if (!$userId) {
            return $this->jsonResponse($response, ['error' => 'Missing user_id'], 400);
        }

        $db = \DBManagerFactory::getInstance();
        
        // Sửa lại cách quote để đảm bảo có dấu ngoặc kép
        $userIdQuoted = "'" . $db->quote($userId) . "'";

        // Lấy danh sách roles của user
        $sqlRoles = "SELECT DISTINCT role_id FROM acl_roles_users WHERE user_id = {$userIdQuoted}";
        $resultRoles = $db->query($sqlRoles);

        $roles = [];

        while ($row = $db->fetchByAssoc($resultRoles)) {
            $roleId = $row['role_id'];
            $roleIdQuoted = "'" . $db->quote($roleId) . "'";

            // Lấy thông tin role
            $role = new \ACLRole();
            if (!$role->retrieve($roleId)) {
                continue;
            }

            // Lấy actions của tất cả modules với tất cả access levels
            $sqlActions = "SELECT ara.action_id, ara.access_override, aa.category, aa.name as action_name
                           FROM acl_roles_actions ara
                           JOIN acl_actions aa ON ara.action_id = aa.id
                           WHERE ara.role_id = {$roleIdQuoted}";
            $resultActions = $db->query($sqlActions);
            
            $actions = [];

            while ($actionRow = $db->fetchByAssoc($resultActions)) {
                $actionId = $actionRow['action_id'];
                $actionIdQuoted = "'" . $db->quote($actionId) . "'";

                // Lấy thông tin action từ bảng acl_actions
                $sqlActionDetail = "SELECT * FROM acl_actions WHERE id = {$actionIdQuoted}";
                $actionDetailResult = $db->query($sqlActionDetail);
                $actionDetail = $db->fetchByAssoc($actionDetailResult);

                if ($actionDetail) {
                    $accessLevel = (int) $actionRow['access_override'];
                    $actions[] = [
                        'id' => $actionDetail['id'],
                        'name' => $actionDetail['name'],
                        'category' => $actionDetail['category'],
                        'access_override' => $accessLevel,
                        'access_level_name' => $this->getACLLevelName($accessLevel),
                        'aclvalue' => $actionDetail['acltype'] ?? null,
                    ];
                }
            }

            // Thêm role vào danh sách
            $roles[] = [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'description' => $role->description,
                'actions' => $actions,
                'total_actions' => count($actions)
            ];
        }

        // Trả về JSON với format chính xác như yêu cầu
        return $this->jsonResponse($response, [
            'user_id' => $userId,
            'roles' => $roles,
            'total_roles' => count($roles),
            'meta' => [
                'timestamp' => date('Y-m-d H:i:s'),
                'endpoint' => "/user/{$userId}/roles"
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
