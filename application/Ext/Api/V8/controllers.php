<?php
require_once 'custom/application/Ext/Api/V8/Controller/UserPasswordController.php';
require_once 'custom/application/Ext/Api/V8/Controller/ListViewController.php';
require_once 'custom/application/Ext/Api/V8/Controller/LanguageModuleController.php';
require_once 'custom/application/Ext/Api/V8/Controller/PushTokenController.php';

use Api\V8\Controller\UserPasswordController;
use Api\V8\Controller\ListViewController;
use Api\V8\Controller\LanguageModuleController;
use Api\V8\Controller\PushTokenController;
use Slim\Container;

return [
    'UserPasswordController' => function (Container $container) {
        return new UserPasswordController();
    },
    'ListViewController' => function (Container $container) {
        return new ListViewController();
    },
    'LanguageModuleController' => function (Container $container) {
        return new LanguageModuleController();
    },
    'PushTokenController' => function (Container $container) {
        return new PushTokenController();
    },
];
