<?php

/**
 * Created by PhpStorm.
 * User: XiongChao
 * Date: 2017/8/25
 * Time: 9:46
 */
namespace App\Server;

class Server
{


    /**
     * Swoole系统核心类，外部使用全局变量$php引用
     *
     * @package    SwooleSystem
     * @author     XiongChao
     * @property Swoole $php
     */

    static public $ini;  //server ini
    /**
     * Swoole类的实例集
     * @var array[Swoole,Swoole]
     */
    static public $php;

    /**
     * Swoole类的路径
     * @var string
     */
    static public $swoole_path = __DIR__ . "/Start.php";

    /**
     * 加载Swoole配置选项
     */
    public static function loadIni()
    {
        if (is_file(ROOT_PATH . "swoole.ini")) {
            $ini = parse_ini_file(ROOT_PATH . "swoole.ini", true);
            foreach ($ini as $k => $v) {
                if (isset($v['host']) && $v['port']) {
                    self::$ini[$k]['host'] = $v['host'];
                    self::$ini[$k]['port'] = (int)$v['port'];
                } else {
                    self::fatal("host&port must be set!");
                }
                self::$ini[$k]['mode'] = (int)$v['mode'] ?? SWOOLE_PROCESS;
                self::$ini[$k]['sock_type'] = (int)$v['sock_type'] ?? SWOOLE_SOCK_TCP;
                self::$ini[$k]['process_title'] = $v['process_title'] ?? null;
            };
        } else {
            echo "can't find file swoole.ini!" . PHP_EOL;
            exit();
        }
    }


    /**
     * @param $is_daemon bool
     * 开启Swoole Tcp 服务端
     */
    public static function start(bool $is_daemon)
    {
        if (empty(self::$ini)) {
            self::fatal("set swoole ini first!");
        }
        /* if (!is_object(self::$php)) {
             self::fatal("\$php is not instantiation!");
         }*/
        foreach (self::$ini as $k => $ini) {
            echo "$k:".PHP_EOL;
            if($is_daemon){
                $command="nohup php ".self::$swoole_path." -i '". json_encode($ini)."' 1>/dev/null 2>/dev/null &";
               echo shell_exec($command).PHP_EOL;
            }else{
                $command="php ".self::$swoole_path." -i '". json_encode($ini)."'";
                $pid = \Swoole\Async::exec($command, function ($result, $status) {
                    echo "resule:".PHP_EOL;
                    var_dump($result);
                    echo "status:".PHP_EOL;
                    var_dump($status);
                });
                var_dump($pid);
            }
            //     self::$php[$k]=new Swoole();
            //僵尸进程 如何处理？？？
            // echo $k.PHP_EOL;
            // self::$php->start($ini);
            /* $process = new \swoole_process(function () use ($k,$ini) {
                 self::$php[$k]->start($ini);
             }, false);
             $process->name($k."-".$ini['process_title']);
             $pid = $process->start();
             echo $k."-".$ini['process_title'] . ": " . $pid . PHP_EOL;
             \swoole_process::wait(false);*/
        }
    }

    public static function stop()
    {
        if (empty(self::$ini)) {
            self::fatal("set swoole ini first!");
        }
        \swoole_process::kill(25782);
    }

    /**
     * 打印错误信息
     * @param $message
     */
    private static function fatal($message)
    {
        echo $message . PHP_EOL;
        exit();
    }
}
