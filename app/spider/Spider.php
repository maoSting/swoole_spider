<?php

namespace app\spider;

use app\db\redis;
use app\task\TaskAbstract;
use Curl\Curl;

class Spider {
    const   VERSION   = '0.0.1';
    const   REDISLIST = 'queueList';
    const   REDISKEY  = 'queueListKey';

    // 配置类
    private $_instanceTask = null;

    public $config         = [];
    public $log;
    public $redis;
    public $mysql;
    //队列数组,swoole看情况需要一个文件队列或者Redis
    public $queueList      = [];
    public $queueListKey   = [];
    //队列长度
    public $queueListCount = 0;

    //爬虫开始时间
    public $startTime = 0;

    // 请求总数量
    public $requestNum = 0;
    // 成功的数量
    public $successNum = 0;
    // 错误的数量
    public $failedNum = 0;

    public function __construct(TaskAbstract $taskConfig) {
        $config                      = $taskConfig->getConfig();
        $this->config['webSite']     = !empty($config['webSite']) ? $config['webSite'] : '';
        $this->config['webName']     = !empty($config['webName']) ? $config['webName'] : 'Default';
        $this->config['workerNum']   = !empty($config['workerNum']) ? $config['workerNum'] : 1;
        $this->config['memory']      = !empty($config['memory']) ? true : false;        //记忆功能、false不启用、true启用
        $this->config['indexUrl']    = !empty($config['indexUrl']) ? $config['indexUrl'] : '';
        $this->config['listUrl']     = !empty($config['listUrl']) ? $config['listUrl'] : [];
        $this->config['contentUrl']  = !empty($config['contentUrl']) ? $config['contentUrl'] : [];
        $this->config['fields']      = !empty($config['fields']) ? $config['fields'] : [];

        $this->_instanceTask = $taskConfig;
        $this->curl          = new Curl();
        $this->curl->setTimeout(3);

        //实例化日志
        $this->log = new Log($this);

        if (!extension_loaded('redis')) {
            exit("该工具需要redis支持");
        }

        //实例化redis
        $this->redis = redis::getInstance();
    }

    /**
     * 任务执行
     */
    public function start() {
        //初始化爬取时间
        $this->startTime = time();

        //入口url入列
        if ($this->config['indexUrl']) {
            $this->addScanUrl($this->config['indexUrl'], true);
        }
        $this->log->startLog();
        $this->run();
        $this->log->endLog();
    }

    private function run() {
        do {
            $this->requestNum++;
            $this->getHttp();
            if($this->_instanceTask->getTest()){
                if($this->requestNum > 100){
                    break;
                }
            }
        } while ($this->queueCount() > 0);

        if(!$this->_instanceTask->getMemory()){
            $this->redis->del(self::REDISKEY);
            $this->redis->del(self::REDISLIST);
        }
    }

    /**
     * 获取url信息
     */
    private function getHttp() {
        if($this->_instanceTask->getSleep()){
            sleep(rand(3,5));
        }
        $collect_url = $this->queueRightPop();
        if ($this->_instanceTask->getDebug()){
            echo sprintf("开始抓取:%s", json_encode($collect_url)) . PHP_EOL;
        }
        if (isset($collect_url['url'])) {
            if ($this->_instanceTask->getDebug()){
                echo sprintf('是否开启代理：%s', $this->_instanceTask->getSupportProxy() ? '是' : '否') . PHP_EOL;
            }
            if ($this->_instanceTask->getSupportProxy()) {
                $this->_instanceTask->getProxy($this->curl);
            }

            // 设置请求头
            $this->curl->setUserAgent(UserAgent::randomPcUserAgent());
            $headers = [
                'Connection'      => 'keep-alive',
                'Cache-Control'   => 'max-age=0',
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
                'Accept-Encoding' => 'gzip, deflate',
                'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8,zh-TW;q=0.7,ja;q=0.6',
                'Referer' => 'https://sz.xzl.anjuke.com/loupan/'
            ];
            $this->curl->setHeaders($headers);
            if($this->_instanceTask->getCookie()){
                $this->curl->setCookieString($this->_instanceTask->getCookie());
            }

            if ($this->_instanceTask->getDebug()) {
                echo sprintf("使用代理：%s", $this->curl->getOpt(CURLOPT_PROXY)) . PHP_EOL;
                echo sprintf("使用代理密码：%s", $this->curl->getOpt(CURLOPT_PROXYUSERPWD)) . PHP_EOL;
            }
            $this->curl->get($collect_url['url']);

            if ($this->_instanceTask->getDebug()){
                echo sprintf("http response status code:%d, error:%s", $this->curl->httpStatusCode, $this->curl->error ? 'yes' : 'no') . PHP_EOL;
            }

//            if ($this->curl->httpStatusCode == 302) {
//                $response = $this->curl->getResponseHeaders();
//                echo sprintf('this is 302, %s', $response['location']).PHP_EOL;
//                $this->addScanUrl($collect_url['url']);
//            }

            if ($this->curl->httpStatusCode == 200) {
                $html = gzdecode($this->curl->getResponse());
                $this->analysisContent($html, $collect_url['url']);
                $this->successNum++;
            } else {
                echo sprintf("抓取失败:%s", $collect_url['url']) . PHP_EOL;
                $this->failedNum++;
            }
        }
    }


    /**
     * 解析内容加入队列
     * @param string $html
     * @param string $currentUrl
     * Author: DQ
     */
    private function analysisContent($html = '', $currentUrl = '') {
        if (is_string($html) && $html) {
            //解析url加入队列
            if($this->_instanceTask->isListUrl($currentUrl) || $this->_instanceTask->getStartUrl() == $currentUrl){
                $urls = $this->_instanceTask->getUrl($html, $currentUrl);
                $urls = array_unique($urls);
                if ($urls) {
                    if ($this->_instanceTask->getDebug()) {
                        echo sprintf('获取url:%s', json_encode($urls)).PHP_EOL;
                    }
                    foreach ($urls as $url) {
                        $this->addScanUrl($url);
                    }
                }
            }

            // 解析内容
            if ($this->_instanceTask->isContentUrl($currentUrl)) {
                //字段筛选
                $data = $this->_instanceTask->getContent($html, $currentUrl);
                if ($this->_instanceTask->getDebug()) {
                    echo sprintf('获取字段:%s', json_encode($data)).PHP_EOL;
                }
                if ($data) {
                    $this->_instanceTask->saveContent($this->mysql, $data, $currentUrl);
                }
            }
        }
    }

    /**
     * 投递url
     *
     * @param $url     投递url
     * @param $isIndex 是否为入口url
     */
    public function addScanUrl($url, $isIndex = false) {
        $link['url'] = $url;
        if ($isIndex) {
            $link['url_type'] = 'index_url';
        } else {
            if ($this->_instanceTask->isListUrl($url)) {
                $link['url_type'] = 'list_url';
            } else if ($this->_instanceTask->isContentUrl($url)) {
                $link['url_type'] = 'content_url';
            } else {
                return false;
            }
        }
        $this->queueLeftPush($link);
    }

    /**
     * 链接头部加入队列
     */
    private function queueLeftPush($arr = []) {
        $result = false;
        $key    = md5($arr['url']);
        if ($this->config['memory']) {
            if (!$this->redis->sIsMember(self::REDISKEY, $key)) {
                $this->redis->sAdd(self::REDISKEY, $key);
                $result = $this->redis->lPush(self::REDISLIST, json_encode($arr));
            }
        } else {
            if (!array_key_exists($key, $this->queueListKey)) {
                $this->queueListCount++;
                $this->queueListKey[ $key ] = time();
                $result                     = array_unshift($this->queueList, $arr);
            }
        }

        return $result;
    }


    /**
     * 链接头部弹出队列
     */
    private function queueRightPop() {
        if ($this->config['memory']) {
            $tmp = $this->redis->rPop(self::REDISLIST);
            return json_decode($tmp, true);
        } else {
            return array_shift($this->queueList);
        }
    }

    public function queueCount() {
        if ($this->config['memory']) {
            return $this->redis->lLen(self::REDISLIST);
        } else {
            return count($this->queueList);
        }
    }

}