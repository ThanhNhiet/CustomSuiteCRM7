<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Api\V8\Controller\UserPasswordController;
use Api\V8\Controller\ListViewController;
use Api\V8\Controller\LanguageModuleController;
use Api\V8\Controller\PushTokenController;

$app->get('/username/{id}', UserPasswordController::class . ':getUserInfo');

$app->post('/change-password/{id}', UserPasswordController::class . ':changePassword');

// API để lấy các field có default = true từ listviewdefs
$app->get('/{module}/list-fields', 'ListViewController:getDefaultFields');

// API để lấy các field từ detailviewdefs
$app->get('/{module}/detail-fields', 'DetailViewController:getDetailFields');

// API để lấy các field từ editviewdefs
$app->get('/{module}/edit-fields', 'EditViewController:getEditFields');

// API ngôn ngữ hệ thống: /Api/V8/custom/system/language/lang={lang}
$app->get('/system/language/lang={lang}', LanguageModuleController::class . ':getSystemLanguage');

// API ngôn ngữ theo format RESTful: /Api/V8/custom/{module}/language/lang={lang}
$app->get('/{module}/language/lang={lang}', LanguageModuleController::class . ':getModuleLanguage');

// API để lưu token push notification
$app->post('/save-token', 'PushTokenController:saveToken');

$app->get('/hello', function () {
    return 'Hello World!';
});

// Test endpoint để debug
$app->get('/test', function () {
    return json_encode(['message' => 'Custom API is working!', 'timestamp' => date('Y-m-d H:i:s')]);
});

return $app;
