<?php
/**
 * Created by PhpStorm.
 * Author: DQ
 * Date: 2019/7/9
 * Time: 14:39
 */

namespace app\config;

class config {
    // 配置文件名
    const CONFIG_FILE = 'config.ini';

    private static $_config = null;

    /**
     *
     * @param $method
     * @param $arguments
     *
     * @return mixed
     * Author: DQ
     */
    public static function __callStatic($method, $arguments){
        if(self::$_config == null){
            $configFile = APP_PATH.DS.self::CONFIG_FILE;
            if(!is_file($configFile)){
                exit(sprintf('config file not exist:%s', $configFile));
            }
            self::$_config = parse_ini_file($configFile, true);
        }
        return self::$method(...$arguments);
    }

    /**
     * 获取配置
     * @param        $name
     * @param string $default
     *
     * @return array|string
     * Author: DQ
     */
    private static function get($name, $default = ''){
        if (strpos($name, '.') === false){
            return isset(self::$_config[$name]) ? self::$_config[$name] : (is_array($default)? $default : []) ;
        }
        list($key, $subKey) = explode('.', $name);
        return isset(self::$_config[$key][$subKey]) ? self::$_config[$key][$subKey] : $default;
    }

}