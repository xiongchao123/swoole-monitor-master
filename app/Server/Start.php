<?php

/*
 * 启动Swoole服务脚本
 */

require_once dirname(dirname(__DIR__))."/bootstrap/app.php";

//get swoole_ini
$opt_ini = getopt('i:');
$ini=json_decode($opt_ini["i"],true);
if(is_array($ini) && count($ini)>1){
    (new \App\Server\Swoole())->start($ini);
}