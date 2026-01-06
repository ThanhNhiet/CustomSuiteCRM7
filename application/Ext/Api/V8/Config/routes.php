<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Api\V8\Controller\UserPasswordController;
use Api\V8\Controller\ListViewController;
use Api\V8\Controller\DetailViewController;
use Api\V8\Controller\EditViewController;
use Api\V8\Controller\LanguageModuleController;
use Api\V8\Controller\PushTokenController;
use Api\V8\Controller\SearchController;
use Api\V8\Controller\RoleUserController;
use Api\V8\Controller\SecurityGroupController;
use Api\V8\Controller\UserGroupsController;
use Api\V8\Controller\DataTypeController;
use Api\V8\Controller\FileController;
use Api\V8\Controller\SetupController;

$app->get('/username/{id}', UserPasswordController::class . ':getUserInfo');

$app->post('/change-password/{id}', UserPasswordController::class . ':changePassword');

// API để lấy các field có default = true từ listviewdefs
$app->get('/{module}/list-fields', ListViewController::class . ':getDefaultFields');

// API để lấy các field từ detailviewdefs
$app->get('/{module}/detail-fields', DetailViewController::class . ':getDetailFields');

// API để lấy các field từ editviewdefs
$app->get('/{module}/edit-fields', EditViewController::class . ':getEditFields');

// API tìm kiếm theo từ khóa
$app->get('/{module}', SearchController::class . ':searchModule');

// API ngôn ngữ hệ thống: /Api/V8/custom/system/language/lang={lang}
$app->get('/system/language/lang={lang}', LanguageModuleController::class . ':getSystemLanguage');

// API ngôn ngữ theo format RESTful: /Api/V8/custom/{module}/language/lang={lang}
$app->get('/{module}/language/lang={lang}', LanguageModuleController::class . ':getModuleLanguage');

// API để lưu token push notification
$app->post('/expo-token', PushTokenController::class . ':saveToken');
// API để lấy token push notification
$app->get('/expo-token/{id}', PushTokenController::class . ':getExpoToken');

// API để lấy danh sách roles của user
$app->get('/user/{user_id}/roles', RoleUserController::class . ':getUserRoles');

// API để lấy danh sách thành viên của nhóm bảo mật
$app->get('/security-groups/{group_id}/members', SecurityGroupController::class . ':getMembers');

// API để lấy danh sách nhóm của người dùng
$app->get('/users/{user_id}/groups', UserGroupsController::class . ':getGroups');

// API để lấy danh sách roles của nhóm
$app->get('/security-groups/{group_id}/roles', UserGroupsController::class . ':getRoles');

// API để lấy danh sách actions của role
$app->get('/roles/{role_id}/actions', UserGroupsController::class . ':getRoleActions');

// API để lấy enum
$app->get('/enum/{module}', DataTypeController::class . ':getModuleEnumOptions');
// API để lấy relate type
$app->get('/relate/{module}', DataTypeController::class . ':getModuleRelateType');

$app->get('/file/{module}/{id}', FileController::class . ':getFile');
$app->post('/file/{module}/{id}', FileController::class . ':uploadFile');

// API để lưu client secret
$app->post('/setup/save-secret/{user_id}', SetupController::class . ':saveSecret');

// API để lưu danh sách modules và quyền truy cập
$app->post('/setup/save-modules-list/{user_id}', SetupController::class . ':saveModulesList');
$app->get('/setup/get-modules-list', SetupController::class . ':getModulesList');

return $app;
