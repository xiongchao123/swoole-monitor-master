<?php

/**
 * Created by PhpStorm.
 * User: XiongChao
 * Date: 2017/8/25
 * Time: 9:46
 */
namespace App\Server;

use App\Log\Writer;
use Predis\Client;
use Predis\PredisException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;

class Server
{


    /**
     * Swoole系统核心类
     *
     * @package    SwooleSystem
     * @author     XiongChao
     */

    /**
     * @var array
     */
    static public $ini;  //server ini

    /**
     * @var
     */
    static private $swoole_start_path = __DIR__ . "/Start.php";

    /**
     * Writer类的实例 记录日志
     * @var Writer
     */
    static public $writer;

    /**
     * OutputFormatterStyle 实例
     * @var \Symfony\Component\Console\Formatter\OutputFormatter
     */
    static public $output;

    /**
     * 存储服务进程主PID的redis key
     * @var string
     */
    static public $master_pid_key = "TCP_MASTER_PID";  //set pid redis keyname

    /**
     * @var Client
     */
    static private $redis = null;

    /**
     * 加载Swoole配置选项
     */
    public static function loadIni()
    {
        self::$output = new OutputFormatter(true);
        if (is_file(ROOT_PATH . "swoole.ini")) {
            self::$writer = new Writer();
            $ini = parse_ini_file(ROOT_PATH . "swoole.ini", true);
            foreach ($ini as $k => $v) {
                if (isset($v['host']) && $v['port'] && $v['script_path']) {
                    self::$ini[$k]['host'] = $v['host'];
                    self::$ini[$k]['port'] = (int)$v['port'];
                    if (!is_file(__DIR__ . "/Scripts/" . $v['script_path'])) {
                        self::$writer->error(__DIR__ . "/Scripts/" . $v['script_path'] . " not found!");
                        self::fatal("<error>" . __DIR__ . "/Scripts/" . $v['script_path'] . " not found!</error>");
                    }
                    self::$ini[$k]['script_path'] = $v['script_path'];
                } else {
                    self::$writer->error("host&port&script_path must be set!");
                    self::fatal("<error>host&port&script_path must be set!</error>");
                }
                self::$ini[$k]['process_title'] = $v['process_title'] ?? $v['script_path'];
            };
        } else {
            self::$writer->error("Could not open input file: swoole.ini!");
            self::fatal("<error>can't find file swoole.ini!</error>");
        }
    }


    /**
     * @param $is_daemon bool
     * @param $serve string
     * 开启Swoole Tcp 服务端
     */
    public static function start(bool $is_daemon, $serve = null)
    {
        if (empty(self::$ini)) {
            self::fatal("<error>set swoole ini first!</error>");
        }
        if (empty($serve)) {
            self::clearPidAll();
            foreach (self::$ini as $k => $ini) {
                $ini['serve_name'] = $k;
                self::startServe($is_daemon, $ini);
                usleep(500000);
                $pid_info = self::getPid($k);
                if (is_numeric($pid_info)) {
                    echo self::$output->format("<info>[$k] 服务启动成功,进程ID: $pid_info</info>") . PHP_EOL;
                } else {
                    echo self::$output->format("<error>[$k] 服务启动失败,错误信息: $pid_info</error>") . PHP_EOL;
                }
            }
        } else {
            self::clearPid($serve);
            self::$ini[$serve]['serve_name'] = $serve;
            self::startServe($is_daemon, self::$ini[$serve]);
            usleep(500000);
            $pid_info = self::getPid($serve);
            if (is_numeric($pid_info)) {
                echo self::$output->format("<info>[$serve] 服务启动成功,进程ID: $pid_info</info>") . PHP_EOL;
            } else {
                echo self::$output->format("<error>[$serve] 服务启动失败,错误信息: $pid_info</error>") . PHP_EOL;
            }
        }

        if (!$is_daemon) {
            \swoole_process::signal(SIGINT, function ($signo) use ($serve) {
                if (empty($serve)) {
                    foreach (array_keys(self::$ini) as $serve_name) {
                        $pid_info = self::getPid($serve_name);
                        if (is_numeric($pid_info)) {
                            if (shell_exec("ps -ef |grep $pid_info|grep -v \"grep\"| awk '{print $2}'")) {
                                shell_exec("kill -TERM $pid_info");
                            }
                        }
                    }
                    self::clearPidAll();
                } else {
                    $pid_info = self::getPid($serve);
                    if (is_numeric($pid_info)) {
                        if (shell_exec("ps -ef |grep $pid_info|grep -v \"grep\"| awk '{print $2}'")) {
                            shell_exec("kill -TERM $pid_info");
                        }
                    }
                    self::clearPid($serve);
                }
                exit(0);
            });
        }
    }

    /**
     * 停止服务进程
     * @param string|null $serve
     */
    public static function stop(string $serve = null)
    {
        if (empty(self::$ini)) {
            self::fatal("set swoole ini first!");
        }
        if (empty($serve)) {
            //停止所有服务进程
            foreach (array_keys(self::$ini) as $serve) {
                $pid_info = self::getPid($serve);
                if (is_numeric($pid_info)) {
                    if (!shell_exec("ps -ef |grep $pid_info|grep -v \"grep\"| awk '{print $2}'")) {
                        echo self::$output->format("<error>[$serve]服务 未正常运行!</error>") . PHP_EOL;
                    }else{
                        shell_exec("kill -TERM $pid_info");
                        echo self::$output->format("<info>[$serve]服务进程ID:$pid_info,已停止</info>") . PHP_EOL;
                    }
                } else {
                    echo self::$output->format("<error>[$serve]服务 未正常运行!</error>") . PHP_EOL;
                }
            }
            self::clearPidAll();
        } else {
            //停止单组服务进程
            $pid_info = self::getPid($serve);
            if (is_numeric($pid_info)) {
                if (!shell_exec("ps -ef |grep $pid_info|grep -v \"grep\"| awk '{print $2}'")) {
                    echo self::$output->format("<error>[$serve]服务 未正常运行!</error>") . PHP_EOL;
                }else{
                    shell_exec("kill -TERM $pid_info");
                    echo self::$output->format("<info>[$serve]服务进程ID:$pid_info,已停止</info>") . PHP_EOL;
                }
            } else {
                echo self::$output->format("<error>[$serve]服务 未正常运行!</error>") . PHP_EOL;
            }
            self::clearPid($serve);
        }
    }

    /**
     * 重启服务进程
     * @param bool $is_daemon
     * @param null $serve
     */
    public static function restart(bool $is_daemon, $serve = null)
    {
        self::stop($serve);
        self::start($is_daemon, $serve);
    }

    public static function status($serve = null)
    {
        if (empty(self::$ini)) {
            self::fatal("set swoole ini first!");
        }
        if (empty($serve)) {
            foreach (array_keys(self::$ini) as $serve) {
                $pid_info = self::getPid($serve);
                if (is_numeric($pid_info)) {
                    //检测进程是否正常运行
                    $command = "pstree -p $pid_info";
                    $output = shell_exec($command);
                    if ($output) {
                        echo self::$output->format("<info>[$serve] 服务运行正常,进程信息:" . PHP_EOL . "$output</info>") . PHP_EOL;
                    } else {
                        echo self::$output->format("<error>[$serve] 服务未正常运行</error>") . PHP_EOL;
                    }
                } else {
                    echo self::$output->format("<error>[$serve] 服务查看状态失败: $pid_info</error>") . PHP_EOL;
                }
            }
        } else {
            $pid_info = self::getPid($serve);
            if (is_numeric($pid_info)) {
                //检测进程是否正常运行
                $command = "pstree -p $pid_info";
                $output = shell_exec($command);
                if ($output) {
                    echo self::$output->format("<info>[$serve] 服务运行正常,进程信息:" . PHP_EOL . "$output</info>") . PHP_EOL;
                } else {
                    echo self::$output->format("<error>[$serve] 服务未正常运行</error>") . PHP_EOL;
                }
            } else {
                echo self::$output->format("<error>[$serve] 服务查看状态失败: $pid_info</error>") . PHP_EOL;
            }
        }
    }

    /**
     * start the swoole serve
     * @param bool $is_daemon
     * @param array $ini
     */
    private static function startServe(bool $is_daemon, array $ini)
    {
        if ($is_daemon) {
            $command = "nohup php " . self::$swoole_start_path . " -i '" . json_encode($ini) . "' 1>/dev/null 2>/dev/null &";
            shell_exec($command);
        } else {
            $command = "php " . self::$swoole_start_path . " -i '" . json_encode($ini) . "'";
            $pid = \Swoole\Async::exec($command, function ($result, $status) {
                echo "返回结果:" . PHP_EOL;
                var_dump($result);
                echo "返回信号:" . PHP_EOL;
                var_dump($status);
            });
            //  echo "进程PID: " . $pid . PHP_EOL;
        }
    }

    /**
     * @param string $serve
     * @return int|string
     */
    private static function getPid(string $serve)
    {
        //检测进程是否启动成功
        try {
            if (is_null(self::$redis)) {
                self::$redis = new Client(config("database.redis.default"));
            }
            if (self::$redis->exists(self::$master_pid_key)) {
                if (self::$redis->hexists(self::$master_pid_key, $serve)) {
                    $pid = self::$redis->hget(self::$master_pid_key, $serve);
                    return $pid;
                } else {
                    return "进程未正常启动";
                }
            }else{
                return "进程未正常启动";
            }
        } catch (PredisException $e) {
            return $e->getMessage();
        }
    }

    /**
     * 清楚所有服务存储的PID
     */
    private static function clearPidAll(){
        try {
            if (is_null(self::$redis)) {
                self::$redis = new Client(config("database.redis.default"));
            }
            self::$redis->del(self::$master_pid_key);
        } catch (PredisException $e) {

        }
    }

    /**
     * @param string $serve
     */
    private static function clearPid(string $serve){
        try {
            if (is_null(self::$redis)) {
                self::$redis = new Client(config("database.redis.default"));
            }
            if (self::$redis->exists(self::$master_pid_key)) {
                if (self::$redis->hexists(self::$master_pid_key, $serve)) {
                    self::$redis->hdel(self::$master_pid_key, $serve);
                }
            }
        } catch (PredisException $e) {

        }
    }

    /**
     * 打印错误信息
     * @param $message
     */
    private static function fatal($message)
    {
        if (!self::$output instanceof OutputFormatterInterface) {
            self::$output = new OutputFormatter(true);
        }
        echo self::$output->format($message) . PHP_EOL;
        exit();
    }
}
