<?php

namespace app\task;

use app\db\redis;
use Curl\Curl;
use EasySwoole\Mysqli\Mysqli;
use sethink\swooleOrm\MysqlPool;

abstract class TaskAbstract {

    protected $_taskName = '';

    protected $_startUrl = null;

    protected $_workerNum = 1;

    protected $_debug = false;

    protected $_test = false;

    protected $_proxySupport = false;

    protected $_memory = true;

    protected $_sleep = 0;

    protected $_cookie = '';

    abstract public function isListUrl($url);

    abstract public function isContentUrl($url);

    // 从内容中提取url
    abstract public function getUrl($html, $url);

    // 从内容获取字段
    abstract public function getContent($html, $url);

    // 保存字段
    abstract public function saveContent(MysqlPool $mysqli, $data, $url);

    public function setStartUrl($url) {
        $this->_startUrl = $url;
    }

    // 获取
    public function getStartUrl() {
        return $this->_startUrl;
    }

    public function getWorkNum() {
        return $this->_workerNum;
    }

    public function setWorkNum($num = 1) {
        $this->_workerNum = $num;
    }

    public function setDebug($turnoff = false) {
        $this->_debug = $turnoff;
    }

    public function getDebug() {
        return $this->_debug;
    }

    public function setMemory($bool = true){
        $this->_memory = $bool;
    }

    public function getMemory(){
        return $this->_memory;
    }

    /**
     * 设置代理支持
     *
     * @param bool $enabled
     *                     true 支持，需要实现getProxy
     *
     * Author: DQ
     */
    public function setSupportProxy($enabled = false) {
        $this->_proxySupport = $enabled;
    }

    public function getSupportProxy() {
        return $this->_proxySupport;
    }

    /**
     * 获取代理
     * @return string
     *               note:port
     * Author: DQ
     */
    public function getProxy(Curl &$curl) {
        return '';
    }

    /**
     * cookie 设置
     * @param $cookie
     * Author: DQ
     */
    public function setCookie($cookie){
        $this->_cookie = $cookie;
    }

    public function getCookie(){
        return $this->_cookie;
    }

    /**
     * 获取任务名称
     * @return string
     * Author: DQ
     */
    public function getTaskName(){
        if(empty($this->_taskName)){
            $this->_taskName = date('md-Hi');
        }
        return $this->_taskName;
    }



    /**
     * 设置任务名称
     * @param string $taskName
     * Author: DQ
     */
    public function setTaskName($taskName = ''){
        $this->_taskName = $taskName;
    }

    /**
     * 设置 测试模式
     * @param bool $bool
     * Author: DQ
     */
    public function setTest($bool = true){
        $this->_test = $bool;
    }

    /**
     * 获取测试模式
     * @return bool
     * Author: DQ
     */
    public function getTest(){
        return $this->_test;
    }

    /**
     * 设置间隔时间
     * @param int $sleep
     * Author: DQ
     */
    public function setSleep($sleep = 0){
        $this->_sleep = intval($sleep);
    }

    /**
     * 获取 间隔时间
     * @return int
     * Author: DQ
     */
    public function getSleep(){
        return $this->_sleep;
    }

    public function getConfig() {
        $urls = parse_url($this->getStartUrl());
        return [
            "webSite"    => isset($urls['host']) ? $urls['host'] : $urls['path'],
            "workerNum"  => isset($workerNum) ? $workerNum : 1,   //启动任务数量,需要client投递
            "memory"     => $this->getMemory(),                                //是否记忆上次抓取节点
            "proxy"      => $this->getSupportProxy(),
            "taskName"    => $this->getTaskName(),
            "indexUrl"   => $this->getStartUrl(),
        ];
    }
}