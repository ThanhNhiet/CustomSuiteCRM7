<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->get('/user-password/{id}', 'UserPasswordController:getUserInfo');

$app->post('/change-password/{id}', 'UserPasswordController:changePassword');

// API để lấy các field có default = true từ listviewdefs
$app->get('/{module}/default-fields', 'ListViewController:getDefaultFields');

$app->get('/hello', function () {
    return 'Hello World!';
});
