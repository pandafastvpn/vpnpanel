<?php

/**
 * 自动加载器和引导文件
 * 
 * 注意: 路径常量必须最先定义, 因为 config.php 中会用到,
 * 而 bootstrap.php 自身的 autoload 逻辑也需要用到。
 */

// 1. 先定义路径常量 (不依赖 config.php)
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}
if (!defined('APP_PATH')) {
    define('APP_PATH', ROOT_PATH . '/app');
}
if (!defined('VIEW_PATH')) {
    define('VIEW_PATH', ROOT_PATH . '/app/views');
}
if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', ROOT_PATH . '/public');
}
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', ROOT_PATH . '/storage');
}
if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', ROOT_PATH . '/config');
}

// 2. 加载配置文件 (config.php 或 config.sample.php)
// config.php 中会定义数据库、NETORA-Radius、站点等配置常量
if (file_exists(CONFIG_PATH . '/config.php')) {
    require CONFIG_PATH . '/config.php';
} else {
    require CONFIG_PATH . '/config.sample.php';
}

// 3. 错误报告设置
if (defined('SITE_DEBUG') && SITE_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// 4. 注册自动加载器
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = APP_PATH . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// 5. 加载 Composer 自动加载 (如果存在)
if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require ROOT_PATH . '/vendor/autoload.php';
}
