<?php

/*
 * 启动Swoole服务脚本
 */

namespace App\Server;

require_once dirname(dirname(__DIR__)) . "/bootstrap/app.php";

//get swoole_ini
$opt_ini = getopt('i:');
$ini = json_decode($opt_ini["i"], true);
if (is_array($ini) && count($ini) > 2) {
    $script_path=explode(".",$ini['script_path'])[0];
    // As of PHP 5.3.
    call_user_func_array(
        array(new \ReflectionClass(__NAMESPACE__ . '\Scripts\\' . $script_path), 'newInstance'),
        array()
    )->start($ini);
}