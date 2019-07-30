<?php
/**
 * Created by PhpStorm.
 * Author: DQ
 * Date: 2019/7/9
 * Time: 15:16
 */
include_once "vendor/autoload.php";
define('APP_PATH', dirname(__FILE__));
define('DS', DIRECTORY_SEPARATOR);


$task = new \app\task\Anjuke();
//$task->setStartUrl('https://sh.xzl.anjuke.com/loupan/p2/');
$task->setStartUrl('https://sz.xzl.anjuke.com/loupan/');
$task->setDebug(true);
$task->setMemory(false);
$task->setSleep(3);
$task->setSupportProxy(true);


//$task = new \app\task\WuhanEr();
//$task->setStartUrl('https://wh.58.com/ershoufang/?PGTID=0d100000-0009-e35f-6add-e9a575fc68ed&ClickID=6');
//$task->setDebug(true);
//$task->setMemory(true);
//$task->setSleep(3);
//$task->setSupportProxy(false);


//$task = new \app\task\ChengduEr();
//$task->setStartUrl('https://cd.58.com/ershoufang/?PGTID=0d100000-0006-6a5a-eafc-e9843b24a878&ClickID=1');
//$task->setDebug(true);
//$task->setMemory(true);
//$task->setSleep(3);
//$task->setSupportProxy(false);


// 客户端
$client = new \app\client\client();
$client->main($task);