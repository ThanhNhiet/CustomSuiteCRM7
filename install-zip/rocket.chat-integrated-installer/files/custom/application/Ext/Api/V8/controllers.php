<?php
require_once 'custom/application/Ext/Api/V8/Controller/UserGroupsController.php';

use Api\V8\Controller\UserGroupsController;
use Slim\Container;         

return [
    UserGroupsController::class => function (Container $container) {
        return new UserGroupsController();
    },
];
