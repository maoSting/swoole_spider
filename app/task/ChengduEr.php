<?php

namespace app\task;

use app\db\redis;
use Curl\Curl;
use sethink\swooleOrm\Db;
use sethink\swooleOrm\MysqlPool;
use voku\helper\HtmlDomParser;
use voku\helper\SimpleHtmlDomNodeBlank;

/**
 * 成都 58 中介人号码
 * Class WuhanEr
 * Author: DQ
 * @package app\task
 */
class ChengduEr extends TaskAbstract {

    /**
     * 是不是内容页
     *
     * @param $url
     *            判断url类型
     *
     * @return bool
     *             true 是内容也
     *             false 不是内容页
     * Author: DQ
     */
    public function isContentUrl($url) {
        $match = preg_match('/^https:\/\/cd\.58\.com\/ershoufang\/[\da-z]{1,20}\.shtml/i', $url);

        return empty($match) ? false : true;
    }

    /**
     *
     * @param $url
     *
     * @return bool
     * Author: DQ
     */
    public function isListUrl($url) {
        $match = preg_match('/^https:\/\/cd\.58\.com\/ershoufang\/pn\d{1,5}/i', $url);

        return empty($match) ? false : true;
    }

    /**
     * 获取 url
     *
     * @param $html
     * @param $url
     *
     * @return array
     * Author: DQ
     */
    public function getUrl($html, $url) {
        $nextUrl = [];
        $dom     = HtmlDomParser::str_get_html($html);

        // 下一页
        $nextDom = $dom->findOne('a.next');
        if (!$nextDom instanceof SimpleHtmlDomNodeBlank) {
            $aHref = $nextDom->getAttribute('href');
            if ($aHref) {
                $nextUrl[] = sprintf("https://cd.58.com%s", $aHref);
            }
        }


        // 内容页
        $contentUrl = $dom->find('h2.title a');
        if (!$contentUrl instanceof SimpleHtmlDomNodeBlank) {
            foreach ($contentUrl as $val) {
                $itemUrl = $val->getAttribute('href');
                if (empty($itemUrl)) {
                    continue;
                }
                $nextUrl[] = $itemUrl;
            }
        }
        return $nextUrl;
    }

    /**
     * 获取内容
     *
     * @param $html
     * @param $url
     *
     * @return array
     *              data
     *              url
     * Author: DQ
     */
    public function getContent($html, $url) {
        $data = [];
        $dom  = HtmlDomParser::str_get_html($html);

        // 经纪人手机号码
        $titleDom = $dom->findOne('p.phone-num');
        if ($titleDom->plaintext) {
            $data['phone'] = $titleDom->plaintext;
        }
        return $data;
    }


    /**
     * 保存文件
     *
     * @param $data
     * @param $url
     * Author: DQ
     */
    public function saveContent(MysqlPool $mysqli, $data, $url) {
        if($data){
            $mysqlTool = Db::init($mysqli);
            $exist     = $mysqlTool->name('dq_chengdu_er')->where(['phone'   => $data['phone']])->find();
            if (empty($exist)) {
                Db::init($mysqli)->name('dq_chengdu_er')->insert($data);
            } else {
                echo sprintf('phone:%s has exist!', $data['phone']) . PHP_EOL;
            }
        }
    }

}