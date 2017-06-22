<?php
/**
 * Created by PhpStorm.
 * User: SongKejing
 * QQ: 597481334
 * Date: 2017-04-10
 * Time: 16:30
 */

namespace app\common\behavior;


use app\console\BrowserSync;
use think\App;

/**
 * 自动拼接浏览器自动刷新js
 * Class BrowserSyncJsMerge
 * @package app\common\behavior
 */
class BrowserSyncJsMerge
{

    /**
     * 执行
     * @param $content
     */
    public function run(&$content){
        if(App::$debug){
            //拼接socket监听js
            $content .= BrowserSync::instance()->getJsContent();
        }
    }
}