<?php
namespace Api\V8\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UserPasswordController
{
    public function getUserInfo(Request $request, Response $response, array $args)
    {
        global $db;

        $id = $args['id'] ?? null;
        if (!$id) {
            return $response->withStatus(400)->withJson(['error' => 'Missing ID']);
        }

        $escapedId = $db->quote($id);
        $query = "SELECT user_name, user_hash FROM users WHERE id = '{$escapedId}' AND deleted = false";
        $result = $db->query($query);

        $userInfo = [];
        if ($row = $db->fetchByAssoc($result)) {
            $userInfo = $row;
        }

        $response->getBody()->write(json_encode($userInfo));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function changePassword(Request $request, Response $response, array $args)
    {
        global $db;

        $id = $args['id'] ?? null;
        if (!$id) {
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json')
                            ->write(json_encode(['error' => 'Missing user id']));
        }

        $body = json_decode($request->getBody()->getContents(), true);
        $oldPassword = $body['old_password'] ?? '';
        $newPassword = $body['new_password'] ?? '';

        if (!$oldPassword || !$newPassword) {
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json')
                            ->write(json_encode(['error' => 'Missing password fields']));
        }

        // Lấy user hiện tại
        $escapedId = $db->quote($id);
        $query = "SELECT user_hash FROM users WHERE id = '{$escapedId}' AND deleted = 0";
        $result = $db->query($query);
        $user = $db->fetchByAssoc($result);

        if (!$user) {
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json')
                            ->write(json_encode(['error' => 'User not found']));
        }

        $storedHash = $user['user_hash'];

        //SuiteCRM 8: cần hash tầng 1 trước khi verify
        $oldHashLevel1 = strtolower(md5($oldPassword));
        if (!password_verify($oldHashLevel1, $storedHash)) {
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json')
                            ->write(json_encode(['error' => 'Old password is incorrect']));
        }

        //Hash mật khẩu mới chuẩn SuiteCRM 8
        $newLevel1 = strtolower(md5($newPassword));
        $newFinalHash = password_hash($newLevel1, PASSWORD_DEFAULT);
        $quotedNewHash = $db->quote($newFinalHash);

        $updateQuery = "UPDATE users SET user_hash = '{$quotedNewHash}' WHERE id = '{$escapedId}'";
        $db->query($updateQuery);

        return $response->withHeader('Content-Type', 'application/json')
                        ->write(json_encode(['message' => 'Password updated successfully']));
    }
}
