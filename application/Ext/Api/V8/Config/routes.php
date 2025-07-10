<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->get('/user-password/{id}', 'UserPasswordController:getUserInfo');

$app->post('/change-password/{id}', 'UserPasswordController:changePassword');

$app->get('/hello', function () {
    return 'Hello World!';
});
