<?php
namespace Api\V8\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PushTokenController
{
    public function saveToken(Request $request, Response $response, array $args)
    {
        global $db;

        // Kiểm tra và tạo bảng nếu chưa tồn tại
        $this->ensureTableExists($db);

        $body = $request->getParsedBody();
        $userId = $body['user_id'] ?? null;
        $expoToken = $body['expo_token'] ?? null;
        $platform = $body['platform'] ?? 'unknown';

        if (!$userId || !$expoToken) {
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json')
                            ->write(json_encode(['error' => 'Missing user_id or expo_token']));
        }

        // 1. Kiểm tra token và user_id
        $escapedUserId = "'" . $db->quote($userId) . "'";
        $escapedToken = "'" . $db->quote($expoToken) . "'";
        
        // Kiểm tra xem token đã tồn tại với cùng user_id chưa
        $checkSameUserQuery = "SELECT id FROM user_devices WHERE user_id = $escapedUserId AND expo_token = $escapedToken LIMIT 1";
        $checkSameUserResult = $db->query($checkSameUserQuery);

        if ($db->fetchByAssoc($checkSameUserResult)) {
            return $response->withHeader('Content-Type', 'application/json')
                            ->write(json_encode(['status' => 'already_exists']));
        }

        // Kiểm tra xem user_id đã có token khác chưa (để update)
        $checkUserQuery = "SELECT id FROM user_devices WHERE user_id = $escapedUserId LIMIT 1";
        $checkUserResult = $db->query($checkUserQuery);
        $existingUserRecord = $db->fetchByAssoc($checkUserResult);

        if ($existingUserRecord) {
            // 2. Update token mới cho user hiện tại
            $now = date('Y-m-d H:i:s');
            $escapedDate = "'" . $db->quote($now) . "'";
            $escapedPlatform = "'" . $db->quote($platform) . "'";
            
            $updateSql = "UPDATE user_devices 
                         SET expo_token = $escapedToken, 
                             platform = $escapedPlatform, 
                             date_modified = $escapedDate 
                         WHERE user_id = $escapedUserId";
            
            $result = $db->query($updateSql);
            
            if (!$result) {
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json')
                                ->write(json_encode(['error' => 'Database update failed']));
            }

            return $response->withHeader('Content-Type', 'application/json')
                            ->write(json_encode(['status' => 'updated', 'id' => $existingUserRecord['id']]));
        }

        // 3. Thêm token mới (user_id chưa tồn tại)
        $id = create_guid();
        $now = date('Y-m-d H:i:s');
        
        // Escape values properly
        $escapedId = "'" . $db->quote($id) . "'";
        $escapedPlatform = "'" . $db->quote($platform) . "'";
        $escapedDate = "'" . $db->quote($now) . "'";

        $insertSql = "INSERT INTO user_devices (id, user_id, expo_token, platform, date_modified)
                      VALUES ($escapedId, $escapedUserId, $escapedToken, $escapedPlatform, $escapedDate)";
        
        $result = $db->query($insertSql);
        
        if (!$result) {
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json')
                            ->write(json_encode(['error' => 'Database insertion failed']));
        }

        return $response->withHeader('Content-Type', 'application/json')
                        ->write(json_encode(['status' => 'saved', 'id' => $id]));
    }

    /**
     * Kiểm tra và tạo bảng user_devices nếu chưa tồn tại
     */
    private function ensureTableExists($db)
    {
        $tableName = 'user_devices';
        
        // Kiểm tra xem bảng có tồn tại không
        $checkTable = "SHOW TABLES LIKE '$tableName'";
        $result = $db->query($checkTable);
        
        if (!$db->fetchByAssoc($result)) {
            // Tạo bảng nếu chưa tồn tại
            $createTableSql = "
                CREATE TABLE `user_devices` (
                    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
                    `user_id` VARCHAR(36) NOT NULL,
                    `expo_token` VARCHAR(255) NOT NULL,
                    `platform` VARCHAR(50) DEFAULT 'unknown',
                    `date_modified` DATETIME NOT NULL,
                    INDEX `idx_user_id` (`user_id`),
                    INDEX `idx_expo_token` (`expo_token`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8
            ";
            $db->query($createTableSql);
        }
    }
}
