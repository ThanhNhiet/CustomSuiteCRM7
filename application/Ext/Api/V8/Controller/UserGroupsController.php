<?php

namespace Api\V8\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// SuiteCRM DB
require_once 'include/database/DBManagerFactory.php';

class UserGroupsController
{
    // ACL Access Level Constants (giống RoleUserController)
    const ACL_ALLOW_ADMIN_DEV = 100;
    const ACL_ALLOW_ADMIN = 99;
    const ACL_ALLOW_DEV = 95;
    const ACL_ALLOW_ALL = 90;
    const ACL_ALLOW_ENABLED = 89;
    const ACL_ALLOW_OWNER = 75;
    const ACL_ALLOW_NORMAL = 1;
    const ACL_ALLOW_DEFAULT = 0;
    const ACL_ALLOW_DISABLED = -98;
    const ACL_ALLOW_NONE = -99;
    /**
     * GET /Api/V8/custom/users/{user_id}/groups[?include_roles=1]
     */
    public function getGroups(Request $request, Response $response, array $args): Response
    {
        $userId = $args['user_id'] ?? null;
        if (!$userId) {
            return $this->json($response, ['error' => 'Missing user_id'], 400);
        }

        $queryParams   = $request->getQueryParams() ?? [];
        $includeRoles  = !empty($queryParams['include_roles']);
        $db            = \DBManagerFactory::getInstance();

        // Kiểm tra user tồn tại
        $userIdQuoted = "'" . $db->quote($userId) . "'";
        $sqlUser = "SELECT id, user_name FROM users WHERE id = {$userIdQuoted} AND deleted = 0";
        $resUser = $db->query($sqlUser);
        $user    = $db->fetchByAssoc($resUser);
        if (!$user) {
            return $this->json($response, ['error' => 'User not found'], 404);
        }

        // Lấy các group của user
        $sqlGroups = "
            SELECT g.id, g.name, g.description
            FROM securitygroups_users gu
            INNER JOIN securitygroups g
                ON g.id = gu.securitygroup_id AND g.deleted = 0
            WHERE gu.user_id = {$userIdQuoted}
              AND gu.deleted = 0
            ORDER BY g.name ASC
        ";
        $resGroups = $db->query($sqlGroups);

        $groups = [];
        $groupIds = [];

        while ($row = $db->fetchByAssoc($resGroups)) {
            $groups[] = [
                'id'          => $row['id'],
                'name'        => $row['name'],
                'description' => $row['description'] ?? ''
            ];
            $groupIds[] = $row['id'];
        }

        // Nếu không có group, trả luôn
        if (!$includeRoles || empty($groupIds)) {
            return $this->json($response, [
                'user'   => ['id' => $user['id'], 'user_name' => $user['user_name']],
                'count'  => count($groups),
                'groups' => $groups,
                'meta'   => [
                    'timestamp' => date('Y-m-d H:i:s'),
                    'endpoint'  => "/users/{$userId}/groups"
                ]
            ]);
        }

        // Lấy Role cho các group (1 query cho tất cả group)
        $quoted = array_map(function ($id) use ($db) {
            return "'" . $db->quote($id) . "'";
        }, $groupIds);
        $inClause = implode(',', $quoted);

        $sqlRoles = "
            SELECT gr.securitygroup_id AS group_id,
                   r.id AS role_id,
                   r.name AS role_name
            FROM securitygroups_acl_roles gr
            INNER JOIN acl_roles r
                ON r.id = gr.role_id AND r.deleted = 0
            WHERE gr.deleted = 0
              AND gr.securitygroup_id IN ({$inClause})
            ORDER BY r.name ASC
        ";
        $resRoles = $db->query($sqlRoles);

        $rolesByGroup = [];
        while ($row = $db->fetchByAssoc($resRoles)) {
            $gid = $row['group_id'];
            if (!isset($rolesByGroup[$gid])) {
                $rolesByGroup[$gid] = [];
            }
            $rolesByGroup[$gid][] = [
                'id'   => $row['role_id'],
                'name' => $row['role_name']
            ];
        }

        // Gắn roles vào từng group
        foreach ($groups as &$g) {
            $g['roles'] = $rolesByGroup[$g['id']] ?? [];
        }
        unset($g);

        return $this->json($response, [
            'user'   => ['id' => $user['id'], 'user_name' => $user['user_name']],
            'count'  => count($groups),
            'groups' => $groups,
            'meta'   => [
                'timestamp' => date('Y-m-d H:i:s'),
                'endpoint'  => "/users/{$userId}/groups?include_roles=" . (int)$includeRoles
            ]
        ]);
    }
    public function getRoles(Request $request, Response $response, array $args): Response
    {
        $groupId = $args['group_id'] ?? '';
        if ($groupId === '') {
            return $this->json($response, ['error' => 'Missing group_id'], 400);
        }

        $db = \DBManagerFactory::getInstance();
        $gid = "'" . $db->quote($groupId) . "'";

        // check group tồn tại
        $rs   = $db->query("SELECT id,name FROM securitygroups WHERE id=$gid AND deleted=0");
        $grp  = $db->fetchByAssoc($rs);
        if (!$grp) {
            return $this->json($response, ['error' => 'Group not found'], 404);
        }

        // lấy role
        $sql = "
            SELECT r.id AS role_id, r.name AS role_name, r.description
            FROM securitygroups_acl_roles gr
            JOIN acl_roles r ON r.id = gr.role_id AND r.deleted = 0
            WHERE gr.securitygroup_id = $gid AND gr.deleted = 0
            ORDER BY r.name ASC";
        $res = $db->query($sql);

        $roles = [];
        while ($row = $db->fetchByAssoc($res)) {
            $roles[] = [
                'id'          => $row['role_id'],
                'name'        => $row['role_name'],
                'description' => $row['description'] ?? ''
            ];
        }

        return $this->json($response, [
            'group' => ['id' => $grp['id'], 'name' => $grp['name']],
            'count' => count($roles),
            'roles' => $roles,
            'meta'  => [
                'timestamp' => date('Y-m-d H:i:s'),
                'endpoint'  => "/security-groups/{$groupId}/roles"
            ]
        ]);
    }
    public function getRoleActions(Request $request, Response $response, array $args): Response
    {
        $roleId = $args['role_id'] ?? null;
        if (!$roleId) {
            return $this->json($response, ['error' => 'Missing role_id'], 400);
        }

        $db = \DBManagerFactory::getInstance();
        $rid = "'" . $db->quote($roleId) . "'";

        // check role
        $sqlRole = "SELECT id, name, description
                    FROM acl_roles
                    WHERE id = {$rid} AND deleted = 0";
        $rsRole  = $db->query($sqlRole);
        $role    = $db->fetchByAssoc($rsRole);

        if (!$role) {
            return $this->json($response, ['error' => 'Role not found'], 404);
        }

        // map level -> label (giống RoleUserController)
        $levels = [
            self::ACL_ALLOW_ADMIN_DEV => 'ADMIN_DEV',
            self::ACL_ALLOW_ADMIN     => 'ADMIN',
            self::ACL_ALLOW_DEV       => 'DEV',
            self::ACL_ALLOW_ALL       => 'ALL',
            self::ACL_ALLOW_ENABLED   => 'ENABLED',
            self::ACL_ALLOW_OWNER     => 'OWNER',
            self::ACL_ALLOW_NORMAL    => 'NORMAL',
            self::ACL_ALLOW_DEFAULT   => 'DEFAULT',
            self::ACL_ALLOW_DISABLED  => 'DISABLED',
            self::ACL_ALLOW_NONE      => 'NONE',
        ];

        // lấy actions của role (giống logic RoleUserController)
        $sqlActions = "SELECT a.id as action_id,
                              a.category AS module,
                              a.name AS action_name,
                              ra.access_override,
                              a.acltype
                       FROM acl_roles_actions ra
                       INNER JOIN acl_actions a
                           ON a.id = ra.action_id AND a.deleted = 0
                       WHERE ra.role_id = {$rid}
                         AND ra.deleted = 0
                       ORDER BY a.category, a.name";
        $rsActions = $db->query($sqlActions);

        $actions = [];
        while ($row = $db->fetchByAssoc($rsActions)) {
            // Dùng access_override như RoleUserController
            $levelValue = (int)$row['access_override'];

            $actions[] = [
                'id'                => $row['action_id'],
                'name'              => $row['action_name'], 
                'category'          => $row['module'],
                'access_override'   => $levelValue,
                'access_level_name' => $levels[$levelValue] ?? 'UNKNOWN',
                'aclvalue'          => $row['acltype'] ?? null,
            ];
        }

        return $this->json($response, [
            'role_id'          => $role['id'],
            'role_name'        => $role['name'],
            'role_description' => $role['description'] ?? '',
            'actions'          => $actions,
            'total_actions'    => count($actions),
            'meta'             => [
                'timestamp' => date('Y-m-d H:i:s'),
                'endpoint'  => "/roles/{$role['id']}/actions",
            ],
        ]);
    }



    private function json(Response $response, $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
