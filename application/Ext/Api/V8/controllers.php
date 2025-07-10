<?php
require_once 'custom/application/Ext/Api/V8/Controller/UserPasswordController.php';

use Api\V8\Controller\UserPasswordController;
use Slim\Container;

return [
    'UserPasswordController' => function (Container $container) {
        return new UserPasswordController();
    }
];
