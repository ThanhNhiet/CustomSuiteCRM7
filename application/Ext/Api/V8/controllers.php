<?php
// Nạp các Controller tùy chỉnh của bạn
require_once 'custom/application/Ext/Api/V8/Controller/UserPasswordController.php';
require_once 'custom/application/Ext/Api/V8/Controller/ListViewController.php';
require_once 'custom/application/Ext/Api/V8/Controller/LanguageModuleController.php';
require_once 'custom/application/Ext/Api/V8/Controller/PushTokenController.php';
require_once 'custom/application/Ext/Api/V8/Controller/DetailViewController.php';
require_once 'custom/application/Ext/Api/V8/Controller/EditViewController.php';
require_once 'custom/application/Ext/Api/V8/Controller/SearchController.php';
require_once 'custom/application/Ext/Api/V8/Controller/RoleUserController.php';
require_once 'custom/application/Ext/Api/V8/Controller/SecurityGroupController.php';
require_once 'custom/application/Ext/Api/V8/Controller/UserGroupsController.php';

use Api\V8\Controller\UserPasswordController;
use Api\V8\Controller\ListViewController;
use Api\V8\Controller\LanguageModuleController;
use Api\V8\Controller\PushTokenController;
use Api\V8\Controller\DetailViewController;
use Api\V8\Controller\EditViewController;
use Api\V8\Controller\SearchController;
use Api\V8\Controller\RoleUserController;
use Api\V8\Controller\SecurityGroupController;
use Api\V8\Controller\UserGroupsController;
use Slim\Container;         

return [
    UserPasswordController::class => function (Container $container) {
        return new UserPasswordController();
    },
    ListViewController::class => function (Container $container) {
        return new ListViewController();
    },
    LanguageModuleController::class => function (Container $container) {
        return new LanguageModuleController();
    },
    PushTokenController::class => function (Container $container) {
        return new PushTokenController();
    },
    DetailViewController::class => function (Container $container) {
        return new DetailViewController();
    },
    EditViewController::class => function (Container $container) {
        return new EditViewController();
    },
    SearchController::class => function (Container $container) {
        return new SearchController();
    },
    RoleUserController::class => function (Container $container) {
        return new RoleUserController();
    },
    SecurityGroupController::class => function (Container $container) {
        return new SecurityGroupController();
    },
    UserGroupsController::class => function (Container $container) {
        return new UserGroupsController();
    },
];
