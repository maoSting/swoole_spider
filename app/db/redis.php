<?php

namespace app\db;

use app\config\config;

class redis extends \Redis {
    public static $instance;
    public static $redis;
    public static $config;

//    private function __construct() {
//    }

    /**
     * 获取实例
     */
    public static function getInstance() {
        if (empty(self::$redis)) {
            self::$redis = new \Redis();
            $conf        = config::get('redis');
            self::$redis->connect($conf['host'], $conf['port']);

            return self::$redis;
        }

        return self::$redis;
    }

}