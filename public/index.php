<?php

/**
 * VPN销售系统 - 入口文件
 * 通过宝塔面板设置网站根目录为 public/
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Auth;

Auth::init();

$router = require APP_PATH . '/routes.php';

$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

$router->dispatch($method, $uri);
