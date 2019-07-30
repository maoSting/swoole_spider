<?php
/**
 * Created by PhpStorm.
 * Author: DQ
 * Date: 2019/7/9
 * Time: 14:38
 */

namespace app\server;

use app\config\config;
use app\config\MysqlConfig;
use app\spider\Spider;
use app\task\TaskAbstract;
use sethink\swooleOrm\MysqlPool;
use Swoole\Server\Task;

class swoole {
    protected $serv;

    protected $conf;

    protected $mysqlPool;

    public function __construct() {
        $this->conf = config::get('swoole');
        //        $this->check_params();
    }

    public function main() {
        $this->serv = new \Swoole\Server(config::get('server.host'), config::get('server.port'));
        $this->serv->set($this->conf);
        $this->serv->on("Start", [$this, 'on_start']);           //swoole启动主进程主线程回调
        $this->serv->on("Shutdown", [$this, 'on_shutdown']);     //服务关闭回调
        $this->serv->on("Connect", [$this, 'on_connect']);       //新连接进入回调
        $this->serv->on("Receive", [$this, 'on_receive']);       //接收数据回调
        $this->serv->on("Close", [$this, 'on_close']);           //客户端关闭回调
        $this->serv->on("Task", [$this, 'on_task']);             //task进程回调
        $this->serv->on("Finish", [$this, 'on_finish']);         //进程投递的任务在task_worker中完成时回调 exit("服务已经在运行!");
        $this->serv->start();
    }

    /**
     * 服务启动
     */
    public function on_start($serv) {
        $dirname = dirname($this->conf['master_pid']);
        if (!is_dir($dirname)) {
            mkdir($dirname, 0777, true);
        }
        $dirnameManager = dirname($this->conf['manager_pid']);
        if (!is_dir($dirnameManager)) {
            mkdir($dirnameManager, 0777, true);
        }

        file_put_contents($this->conf['master_pid'], $serv->master_pid);
        file_put_contents($this->conf['manager_pid'], $serv->manager_pid);
        $msg = PHP_EOL . "-------------------------------------------------" . PHP_EOL;
        $msg .= PHP_EOL . "Swoole 爬虫服务启动成功!" . PHP_EOL;
        $msg .= PHP_EOL . "-------------------------------------------------" . PHP_EOL;
        echo $msg;
    }

    /**
     * 服务关闭
     */
    public function on_shutdown() {
        echo "Swoole关闭成功!" . PHP_EOL;
    }

    /**
     * 客户端连接
     */
    public function on_connect($server, $fd, $from_id) {
        echo sprintf("Swoole客户端连接成功fd:%s, from_id:%s", $fd, $from_id) . PHP_EOL;
    }

    public function parseConfig($string = ''): TaskAbstract {
        return unserialize($string);
    }

    /**
     * 接收数据
     */
    public function on_receive($serv, $fd, $from_id, $data) {
//        echo sprintf("接收客户端fd:%s, from_id:%s", $fd, $from_id).PHP_EOL;

//        echo sprintf("接收数据:%s", $data).PHP_EOL;

        $taskConfig = $this->parseConfig($data);
        $judge      = $taskConfig instanceof TaskAbstract;
        if (empty($judge)) {
            echo "参数错误，配置类必须继承 app\\task\\TaskAbstract" . PHP_EOL;

            return;
        }
        if ($taskConfig->getWorkNum()) {
            for ($i = 0; $i < $taskConfig->getWorkNum(); $i++) {
                $serv->task($data);
            }
        }
    }

    /**
     * 客户端关闭
     */
    public function on_close($serv, $fd, $reactorId) {
        echo sprintf("客户端 %s 关闭成功", $fd) . PHP_EOL;
    }

    public function on_task($serv, Task $task) {
//        echo sprintf("task任务接收_task_id:%s, _data:%s", $task->worker_id, $task->data).PHP_EOL;
        $taskConfig      = $this->parseConfig($task->data);
        $spider          = new Spider($taskConfig);
        $this->mysqlPool = new MysqlPool(config::get('mysql'));
        $spider->mysql   = $this->mysqlPool;
        $spider->start();
    }

//    public function on_task($serv, $task_id, $src_worker_id, $data){
//        echo sprintf("task任务接收_task_id:%s, _data:%s", $task_id, $data).PHP_EOL;
//        $taskConfig = $this->parseConfig($data);
//        $spider = new Spider($taskConfig);
//        $this->mysqlPool = new MysqlPool(config::get('mysql'));
//        var_dump($this->mysqlPool);
//        $spider->mysql = $this->mysqlPool;
//        $spider->start();
//    }

    public function on_finish($serv, $task_id, $data) {
        echo sprintf("task任务接收完成_task_id:%s, _data:%s", $task_id, $data) . PHP_EOL;
    }

    /**
     * 运行检测
     */
    private function check_run() {
        echo sprintf("php 运行模式:%s", PHP_SAPI) . PHP_EOL;
    }

    /**
     * 参数解析
     */
    private function check_params() {
        $params = 'reload';
        if ($params) {
            $master_pid = file_get_contents($this->conf['master_pid']);
            switch ($params) {
                case "reload":
                    exec("kill -USR1 $master_pid");
                    echo "Swoole Reload 完成!" . PHP_EOL;
                    exit;
                    break;
                case "shutdown":
                    exec("kill -15 $master_pid");
                    echo "Swoole Shutdown 完成!" . PHP_EOL;
                    exit;
                    break;
            }
        }
    }

}