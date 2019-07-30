<?php

namespace app\task;

use app\config\config;
use app\db\redis;
use Curl\Curl;
use sethink\swooleOrm\Db;
use sethink\swooleOrm\MysqlPool;
use voku\helper\HtmlDomParser;
use voku\helper\SimpleHtmlDomNodeBlank;

/**
 * 安居客楼盘数据 爬取
 * Class Anjuke
 * Author: DQ
 * @package app\task
 */
class Anjuke extends TaskAbstract {

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
        $match = preg_match('/^https:\/\/sz\.xzl\.anjuke\.com\/loupan\/\d{1,10}/i', $url);

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
        $match = preg_match('/^https:\/\/sz\.xzl\.anjuke\.com\/loupan\/p\d{1,5}/i', $url);

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
        $nextDom = $dom->findOne('a.aNxt');
        if (!$nextDom instanceof SimpleHtmlDomNodeBlank) {
            $aHref = $nextDom->getAttribute('href');
            if ($aHref) {
                $nextUrl[] = sprintf("https://sz.xzl.anjuke.com%s", $aHref);
            }
        }


        // 内容页
        $contentUrl = $dom->find('dt.item-title a');
        if (!$contentUrl instanceof SimpleHtmlDomNodeBlank) {
            foreach ($contentUrl as $val) {
                $itemUrl = $val->getAttribute('href');
                if (empty($itemUrl)) {
                    continue;
                }
                $nextUrl[] = sprintf('https://sz.xzl.anjuke.com%s', $itemUrl);
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

        // 标题
        $titleDom = $dom->findOne('h1');
        if ($titleDom->plaintext) {
            $data['title'] = $titleDom->plaintext;
        }

        // 内容
        $contentDom = $dom->findOne('span.adderss');
        if ($contentDom->plaintext) {
            $tmpAddr = explode('-', $contentDom->plaintext);
            if (isset($tmpAddr[2])) {
                $data['address'] = $tmpAddr[2];
            }
            if (isset($tmpAddr[0])) {
                if(in_array($tmpAddr[0] ,['宝安','龙华','南山','龙岗','福田','罗湖','布吉','光明','坪山','盐田','大鹏新区'])){
                    $data['disc'] = $tmpAddr[0];
                }else{
                    return [];
                }
            }
            if(isset($data['disc'])){
                $data['province'] = '深圳市';
                $data['city'] = '深圳市';
                $data['type'] = 1;
                $data['status'] = 0;
            }
        }

        if(empty($data['disc']) || empty($data['address']) || empty($data['title'])){
            return [];
        }

        // 楼层
        $floorDom = $dom->find('ul.basic-parms-mod li', -1);
        $levelDom = $floorDom->findOne('div');
        if($levelDom->plaintext){
            $data['floor_total'] = intval($levelDom->plaintext);
        }

        // 上传人
        $data['upload_id'] = 0;


        $data['is_del'] = 0;

        // 简介
        $info = $dom->findOne('div.j-ext-infos');
        if ($info->plaintext) {
            $data['intro'] = $info->plaintext;
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
            $exist     = $mysqlTool->name('office_g_building')->where(['title'   => $data['title'],
                                                                       'address' => $data['address']
            ])->find();
            if (empty($exist)) {
                Db::init($mysqli)->name('office_g_building')->insert($data);
            } else {
                echo sprintf('title:%s, address:%s has exist!', $data['title'], $data['address']) . PHP_EOL;
            }
        }
    }



    /**
     * 设置代理，一般自己实现
     *
     *
     * @return null|string
     * Author: DQ
     */
    public function getProxy(Curl &$curl) {
        $key   = 'sp_proxy_pool';
        $redis = redis::getInstance();

        $proxy = null;
        for ($i = 0; $i < 5; $i++) {
            $nodeStr = $redis->rPop($key);
            if (empty($nodeStr)) {
                continue;
            }
            $node = json_decode($nodeStr, true);
            if ($node['time'] <= time()) {
                continue;
            }
            $proxy = $node['node'];
            break;
        }

        if ($proxy) {
            $curl->setOpt(CURLOPT_PROXY, $proxy);
            $curl->setOpt(CURLOPT_PROXYUSERPWD, sprintf('%s:%s', config::get('proxy.user'), config::get('proxy.pwd')));
        }

        return '';
    }


//    public function setCookie(Curl &$curl) {
//        $curl->setCookieString('sessid=3ACC58B5-2FFB-4921-AB7E-3D062FDA6A76; aQQ_ajkguid=DB3AE800-F1E2-F937-F09B-01A5E916894E; lps=http%3A%2F%2Fwww.anjuke.com%2F%3Fpi%3DPZ-baidu-pc-all-biaoti%7Chttp%3A%2F%2Fbzclk.baidu.com%2Fadrc.php%3Ft%3D06KL00c00f7WWws0vrGb00PpAsaJhH4I00000Zg_P-C00000Ir5Wgc.THvs_oeHEtY0UWdVUv4_py4-g1wxuAT0T1dBnjc3nHFBrH0snj7BP1wh0ZRqwWbzPWczfbmdwWfzn1D4rDnvPjParDcsPHnsrH0zwbR0mHdL5iuVmv-b5HnzP1D4nWn3n1nhTZFEuA-b5HDv0ARqpZwYTZnlQzqLILT8my4JIyV-QhPEUitOTAbqR7CVmh7GuZRVTAnVmyk_QyFGmyqYpfKWThnqPHndrjc%26tpl%3Dtpl_11534_19968_16032%26l%3D1513548917; ctid=13; twe=2; 58tj_uuid=9b2b7001-2c3f-4bd5-aa48-2b80b9eb20b7; new_session=0; init_refer=http%253A%252F%252Fbzclk.baidu.com%252Fadrc.php%253Ft%253D06KL00c00f7WWws0vrGb00PpAsaJhH4I00000Zg_P-C00000Ir5Wgc.THvs_oeHEtY0UWdVUv4_py4-g1wxuAT0T1dBnjc3nHFBrH0snj7BP1wh0ZRqwWbzPWczfbmdwWfzn1D4rDnvPjParDcsPHnsrH0zwbR0mHdL5iuVmv-b5HnzP1D4nWn3n1nhTZFEuA-b5HDv0ARqpZwYTZnlQzqLILT8my4JIyV-QhPEUitOTAbqR7CVmh7GuZRVTAnVmyk_QyFGmyqYpfKWThnqPHndrjc%2526tpl%253Dtpl_11534_19968_16032%2526l%253D1513548917; new_uv=1; _ga=GA1.2.1250752408.1564383667; _gid=GA1.2.2138218350.1564383667; __xsptplus8=8.3.1564384496.1564384496.1%232%7Csp0.baidu.com%7C%7C%7C%25E5%25AE%2589%25E5%25B1%2585%25E5%25AE%25A2%7C%23%23BlV14Nsbr-zmElcb1TSA9BFdkI6sYxjK%23; als=0; wmda_uuid=c792b7b2ce17aec13fe70092d1d3b872; wmda_new_uuid=1; wmda_session_id_6289197098934=1564383687195-d77a9c40-e9e3-e7b2; wmda_visited_projects=%3B6289197098934');
//    }

}