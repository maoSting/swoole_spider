<?php

namespace app\spider;

use app\spider\Spider;

class Log {
    private $_spider;

    public function __construct(Spider $spider) {
        $this->_spider = $spider;
    }

    /**
     * 启动日志
     */
    public function startLog() {
        $msg       = PHP_EOL . "-------------------------------------------------" . PHP_EOL;
        $msg       .= PHP_EOL . $this->_spider->config['webSite']. ' 任务开始'. PHP_EOL;
        $msg       .= PHP_EOL . $this->_spider->config['webSite'] . PHP_EOL;
        $msg       .= PHP_EOL . "-------------------------------------------------" . PHP_EOL;
        echo $msg;
    }


    public function endLog(){
        $endTime = time();
        $msg       = PHP_EOL . "-------------------------------------------------" . PHP_EOL;
        $msg       .= PHP_EOL . $this->_spider->config['webSite']. ' 任务结束'. PHP_EOL;
        $msg       .= PHP_EOL . sprintf('请求总数:%d, 成功数量:%d, 失败数量:%d' , $this->_spider->requestNum, $this->_spider->successNum, $this->_spider->failedNum) . PHP_EOL;
        $msg       .= PHP_EOL . sprintf('花费时间:%d s', $endTime-$this->_spider->startTime) . PHP_EOL;
        $msg       .= PHP_EOL . "-------------------------------------------------" . PHP_EOL;
        echo $msg;
    }

    /**
     * 写入日志
     */
    public function writeLog() {

    }


}