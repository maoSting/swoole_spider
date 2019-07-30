<?php

namespace app\client;

use app\config\config;
use app\task\TaskAbstract;

/**
 * swoole客户端
 */
class client {
    public $client;

    public function __construct() {
        if (empty($this->client)) {
            $this->client = new \Swoole\Client(SWOOLE_SOCK_TCP);
            $this->client->connect(config::get('server.host'), config::get('server.port'));
        }
    }

    public function main(TaskAbstract $task) {
        $params = serialize($task);
        if ($task->getDebug()){
            echo sprintf('Swoole Client send ' . $params).PHP_EOL;
        }
        $this->client->send($params);
    }

}