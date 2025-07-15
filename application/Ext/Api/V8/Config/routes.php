<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Api\V8\Controller\UserPasswordController;
use Api\V8\Controller\ListViewController;
use Api\V8\Controller\LanguageModuleController;

$app->get('/user-password/{id}', UserPasswordController::class . ':getUserInfo');

$app->post('/change-password/{id}', UserPasswordController::class . ':changePassword');

// API để lấy các field có default = true từ listviewdefs
$app->get('/{module}/default-fields', ListViewController::class . ':getDefaultFields');

// API ngôn ngữ theo format RESTful: /Api/V8/custom/{module}/language/lang={lang}
$app->get('/{module}/language/lang={lang}', LanguageModuleController::class . ':getModuleLanguage');

$app->get('/hello', function () {
    return 'Hello World!';
});

// Test endpoint để debug
$app->get('/test', function () {
    return json_encode(['message' => 'Custom API is working!', 'timestamp' => date('Y-m-d H:i:s')]);
});

return $app;
