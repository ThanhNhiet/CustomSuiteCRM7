<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Api\V8\Controller\UserGroupsController;

$app->get('/user/{user_id}/roles', RoleUserController::class . ':getUserRoles');
$app->get('/user/{user_id}/roles-task', UserGroupsController::class . ':getUserTaskRoles');

return $app;
