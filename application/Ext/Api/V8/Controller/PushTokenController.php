<?php
namespace Api\V8\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PushTokenController
{
    public function saveToken(Request $request, Response $response, array $args)
    {
        global $db;

        $body = $request->getParsedBody();
        $userId = $body['user_id'] ?? null;
        $expoToken = $body['expo_token'] ?? null;
        $platform = $body['platform'] ?? 'unknown';

        if (!$userId || !$expoToken) {
            return $response->withStatus(400)->withJson(['error' => 'Missing user_id or expo_token']);
        }

        // 1. Kiểm tra token đã tồn tại chưa (chưa bị xóa)
        $safeToken = $db->quote($expoToken);
        $checkQuery = "SELECT id FROM user_devices WHERE expo_token = $safeToken AND deleted = 0 LIMIT 1";
        $checkResult = $db->query($checkQuery);

        if ($db->fetchByAssoc($checkResult)) {
            return $response->withJson(['status' => 'already_exists']);
        }

        // 2. Soft delete token cũ trùng (nếu có bản ghi deleted = 1)
        $db->query("UPDATE user_devices SET deleted = 1 WHERE expo_token = $safeToken");

        // 3. Thêm token mới
        $id = create_guid();
        $now = date('Y-m-d H:i:s');
        $safeUserId = $db->quote($userId);
        $safePlatform = $db->quote($platform);

        $insertSql = "INSERT INTO user_devices (id, user_id, expo_token, platform, date_modified, deleted)
                      VALUES ('$id', $safeUserId, $safeToken, $safePlatform, '$now', 0)";
        $db->query($insertSql);

        return $response->withJson(['status' => 'saved', 'id' => $id]);
    }
}
