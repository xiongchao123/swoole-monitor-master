<?php

/**
 * Created by PhpStorm.
 * User: XiongChao
 * Date: 2017/8/25
 * Time: 9:46
 */
namespace App\Server\Scripts;

use App\Foundation\Server\HandleServer;
use App\Log\Writer;
use Predis\PredisException;

class Swoole
{
    use HandleServer;
    // TODO ....可将消息类型定义为常量 本例并未设置
    const PAG_HEAD_LENGHT = 45;  //包头长度
    const REPLY_MAX_BODY = 81920;
    public $serv;   //swoole server
    private $writer;  //log writer object
    private $ini;
    private $master_pid;

    /**
     * 初始化swoole
     */
    public function __construct()
    {
        $this->writer = new Writer();
    }

    public function start($ini)
    {
        $this->ini=$ini;
        $this->serv = new \swoole_server($ini['host'], $ini['port'], SWOOLE_PROCESS, SWOOLE_SOCK_TCP);
        //配置选项
        $this->serv->set([
            'worker_num' => 1,   //worker进程数,生产环境下可以根据需求设置
            'reactor_num' => 1,   //通过此参数来调节主进程内事件处理线程的数量，以充分利用多核。默认会启用CPU核数相同的数量。一般设置为CPU核数的1-4倍
            'daemonize' => false,
            'backlog' => 1000,  //Listen队列长度，
            'task_worker_num' => 1,     //设置此参数后，服务器会开启异步task功能。此时可以使用task方法投递异步任务。
            'max_request' => 10000,
            'dispatch_mode' => 2,  //数据包分发策略  默认为2 固定模式
            // 'open_eof_check' => true, //打开EOF检测
            // 'package_eof' => "\r\n", //设置EOF
            'open_length_check' => true,   //打开固定包头协议解析功能
            //'package_length_offset' => 0,  //规定了包头中第几个字节开始是长度字段
            //'package_body_offset' => 42,    //规定了包头的长度
            //'package_body_offset' => 0,    //length的值包含了整个包（包头+包体）
            //'package_length_type' => 'V',   //规定了长度字段的类型
            'package_length_func' =>function ($data) {
                if (strlen($data) < self::PAG_HEAD_LENGHT) {
                    return 0;
                }
                $Header_C = unpack("C4", substr($data, 0, 4));
                if ($Header_C[1] !== 1) {
                    $this->writer->error("版本号错误: " . $Header_C[1]);
                    return -1;
                }
                /*
                 * 1-请求密钥消息，2-返回密钥消息，3-心跳请求消息，4-心跳应答消息，5-推送请求消息，6-推送应答消息,7-监控上报消息,8-监控上报应答消息
                 */
                if (!in_array($Header_C[2], [1, 3, 5, 7])) {
                    $this->writer->error("消息类别错误: " . $Header_C[2]);
                    return -1;
                }
                if ($Header_C[3] !== 1 && $Header_C[3] !== 0) {
                    $this->writer->error("包体加密标识错误: " . $Header_C[3]);
                    return -1;
                }
                if ($Header_C[4] !== 1 && $Header_C[4] !== 0) {
                    $this->writer->error("包体压缩标识错误: " . $Header_C[4]);
                    return -1;
                }
                $Header_V = unpack("V2", substr($data, 4, 8));
                $package_length = $Header_V[1];
                $reqcrc = $Header_V[2];
                //判断crc32校验和
                $headcrc = sprintf("%u", crc32(substr($data, 0, 8)));
                if ((string)$reqcrc !== $headcrc) {
                    $this->writer->error("crc32校验和错误: " . "reqcrc=$reqcrc,headcrc=$headcrc");
                    return -1;
                }
                if (strlen($data) < $package_length) {
                    return 0;
                }
                return $package_length;
            },
            'package_max_length' => 81920,   //所能接收的包最大长度 根据实际情况自行配置
            'task_max_request' => 100,  //最大task进程请求数
            'heartbeat_idle_time' => 120,  //表示连接最大允许空闲的时间
            'heartbeat_check_interval' => 60,  //轮询检测时间
            'log_file' => ROOT_PATH . 'storage/logs/swoole.log'
        ]);
        $this->serv->on('Start', array(
            $this,
            'onStart'
        ));
        $this->serv->on('ManagerStart', array(
            $this,
            'onManagerStart'
        ));
        $this->serv->on('WorkerStart', array(
            $this,
            'onWorkerStart'
        ));
        $this->serv->on('Connect', array(
            $this,
            'onConnect'
        ));
        $this->serv->on('Receive', array(
            $this,
            'onReceive'
        ));
        $this->serv->on('Close', array(
            $this,
            'onClose'
        ));
        //1.7？ 版本后不支持
        /* $this->serv->on('Timer', array(
             $this,
             'onTimer'
         ));*/
        // bind callback
        $this->serv->on('Task', array(
            $this,
            'onTask'
        ));
        $this->serv->on('Finish', array(
            $this,
            'onFinish'
        ));
        $this->serv->start();
    }


    /**
     * Server启动在主进程的主线程回调此函数
     *
     * @param unknown $serv
     */
    public function onStart($serv)
    {
        $this->master_pid=$serv->master_pid;
        // 设置进程名称
        $this->set_process_name();
        //start redis connect to clear clients & set the master process pid
        //use phpredis or predis
        try{
            $this->listenTcpStart();
        }catch (PredisException $e){
           // $output=new OutputFormatter(true);
           // echo $output->format("<error>".$e->getMessage()."</error>");
            $this->writer->error("<error>".$e->getMessage()."</error>");
            \Swoole\Async::exec("kill -TERM $this->master_pid", function ($result, $status) {

            });
        }
    }

    /**
     * 当管理进程启动时调用它
     * @param $serv
     */
    public function onManagerStart($serv){
        $this->set_process_name();
    }

    /**
     * 此事件在worker进程/task进程启动时发生
     *
     * @param swoole_server $serv
     * @param int $worker_id
     */
    function onWorkerStart($serv, $worker_id)
    {
        $this->set_process_name();
        //进程都为独立进程,数据不可共享
        //可设置tick异步定时任务处理业务上的逻辑
    }

    /**
     * 有新的连接进入时，在worker进程中回调
     *
     * @param swoole_server $serv
     * @param int $fd
     * @param int $from_id
     */
    public function onConnect($serv, $fd, $from_id)
    {
        //可根据具体需求对客户端进行相关处理
        //暂对数据进行日志记录
        $clientinfo = $serv->connection_info($fd);
        try {
            $this->writer->info([
                'remote_ip' => $clientinfo['remote_ip'], //客户端连接的IP
                'remote_port' => $clientinfo['remote_port'], //客户端连接的端口
                'connect_time' => date('Y-m-d H:i:s', $clientinfo['connect_time']), //连接到Server的时间，单位秒
                //  'message' => substr($data, 42)    //消息内容
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->writer->error($e->getMessage());
        }
    }

    /**
     * 接收到数据时回调此函数，发生在worker进程中
     *
     * @param swoole_server $serv
     * @param int $fd
     * @param int $from_id
     * @param var $data
     */
    public function onReceive($serv, $fd, $from_id, $data)
    {
        $param = array(
            'fd' => $fd,
            'data' => $data
        );
        $serv->task($param);
    }

    /**
     * TCP客户端连接关闭后，在worker进程中回调此函数
     *
     * @param swoole_server $serv
     * @param int $fd
     * @param int $from_id
     */
    public function onClose($serv, $fd, $from_id)
    {
        //clear redis jilu
        $this->listenTcpClose($fd);
    }

    /**
     * 在task_worker进程内被调用。
     * worker进程可以使用swoole_server_task函数向task_worker进程投递新的任务。
     * 当前的Task进程在调用onTask回调函数时会将进程状态切换为忙碌，这时将不再接收新的Task，
     * 当onTask函数返回时会将进程状态切换为空闲然后继续接收新的Task
     *
     * @param swoole_server $serv
     * @param int $task_id
     * @param int $from_id
     * @param
     *            json string $param
     * @return string
     */
    public function onTask($serv, $task_id, $from_id, $param)
    {
        $this->handleTask($serv,$param);
        return "Task {$task_id}'s result";
    }

    /**
     * 当worker进程投递的任务在task_worker中完成时，
     * task进程会通过swoole_server->finish()方法将任务处理的结果发送给worker进程
     *
     * @param swoole_server $serv
     * @param int $task_id
     * @param string $data
     */
    public function onFinish($serv, $task_id, $data)
    {
        //   echo "Task {$task_id} finish\n";
        echo "Result: {$data}\n";
    }


    /**
     * 定时任务
     *
     * @param swoole_server $serv
     * @param int $interval
     */

    public function onTimer($serv, $interval)
    {
        // TODO 根据实际情况进行操作
    }

    /**
     * 定时任务
     *
     * @param swoole_server $serv
     */
    private function tickerEvent($serv)
    {
        // TODO 根据实际情况进行操作
    }

    function set_process_name(){
        // 设置进程名称
        if (!empty($this->ini['process_title'])) {
            if (!cli_set_process_title($this->ini['process_title'])) {
                $this->writer->warning("Unable to set process title for PID $this->master_pid...");
            }
        }
    }
}
