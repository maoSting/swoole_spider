<?php
/**
 * 主服务
 *
 */
include_once "vendor/autoload.php";
define('APP_PATH', dirname(__FILE__));
define('DS', DIRECTORY_SEPARATOR);

$server = new \app\server\swoole();
$server->main();


