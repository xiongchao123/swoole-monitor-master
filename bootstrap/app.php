<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/11/27
 * Time: 15:46
 */

date_default_timezone_set('Asia/Shanghai');
define('ROOT_PATH', __DIR__ . '/../');
require_once ROOT_PATH.'vendor/autoload.php';
//加载全局异常处理
new \App\Exceptions\HandleExceptions();
require_once ROOT_PATH .'app/util/file.php';
require_once ROOT_PATH .'app/util/helpers.php';


$GLOBALS['config']=[];

//加载config目录下所有的配置文件
foreach (get_files(ROOT_PATH .'config') as $path){
    $pathinfo = pathinfo($path);
    if($pathinfo['extension'] !== 'php'){
        exit("Unknown File : ".$path);
    }
    $GLOBALS['config'][$pathinfo['filename']]=require_once $path;
}


