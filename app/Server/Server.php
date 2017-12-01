<?php

/**
 * Created by PhpStorm.
 * User: XiongChao
 * Date: 2017/8/25
 * Time: 9:46
 */
namespace App\Server;

use App\Log\Writer;
use App\util\AesCrypt;
use App\util\GetUniqueString;

class Server
{
    // TODO ....可将消息类型定义为常量 本例并未设置

    const PAG_HEAD_LENGHT = 41;  //包头长度
    const REPLY_MAX_BODY = 81920;
    const CLIENTS_KEY = "TCP_CONNECT_CLIENTS";  //save clients to redis key
    const PUSH_CLIENTS_KEY = "TCP_PUSH_CONNECT_CLIENTS";  //save clients to redis key
    private $serv;   //swoole server
    private $writer;  //log writer object
    private $redis_config;   //redis connect config


    /**
     * 初始化swoole
     */
    public function __construct()
    {
        $this->serv = new \swoole_server('0.0.0.0', 9503, SWOOLE_BASE, SWOOLE_SOCK_TCP);
        $this->writer = new Writer();
        $this->redis_config = config("database.redis.default");
        $this->serv->set(array(
            'worker_num' => 2,   //worker进程数,生产环境下可以根据需求设置
            'reactor_num' => 2,   //通过此参数来调节主进程内事件处理线程的数量，以充分利用多核。默认会启用CPU核数相同的数量。一般设置为CPU核数的1-4倍
            'daemonize' => false,
            'backlog' => 1000,  //Listen队列长度，
            'task_worker_num' => 2,     //设置此参数后，服务器会开启异步task功能。此时可以使用task方法投递异步任务。
            'max_request' => 10000,
            'dispatch_mode' => 2,  //数据包分发策略  默认为2 固定模式
            // 'open_eof_check' => true, //打开EOF检测
            // 'package_eof' => "\r\n", //设置EOF
            'open_length_check' => true,   //打开固定包头协议解析功能
            //'package_length_offset' => 0,  //规定了包头中第几个字节开始是长度字段
            //'package_body_offset' => 42,    //规定了包头的长度
            //'package_body_offset' => 0,    //length的值包含了整个包（包头+包体）
            //'package_length_type' => 'V',   //规定了长度字段的类型
            'package_length_func' => function ($data) {

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
                if (strlen($data) !== unpack("V", substr($data, 4, 4))[1]) {
                    $this->writer->error("包长度字段错误: " . unpack("V", $data)[1]);
                    return -1;
                }
                return strlen($data);
            },
            'package_max_length' => 81920,   //所能接收的包最大长度 根据实际情况自行配置
            'task_max_request' => 100,  //最大task进程请求数
            'heartbeat_idle_time' => 120,  //表示连接最大允许空闲的时间
            'heartbeat_check_interval' => 60,  //轮询检测时间
            'log_file' => ROOT_PATH . 'storage/logs/swoole.log'
        ));
        $this->serv->on('Start', array(
            $this,
            'onStart'
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
        $this->serv->on('WorkerStart', array(
            $this,
            'onWorkerStart'
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
        //start redis connect to clear clients
        //use phpredis or predis
        $redis = new \Redis();
        $redis->connect($this->redis_config['host'], $this->redis_config['port'], $this->redis_config['timeout'] ?? null);
        $redis->select($this->redis_config['database'] ?? 0);
        $redis->del(self::CLIENTS_KEY);
        $redis->del(self::PUSH_CLIENTS_KEY);
        $redis->close();
        // 设置进程名称
        //cli_set_process_title("root_server");
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
        try {
            $redis = new \Redis();
            $redis->connect($this->redis_config['host'], $this->redis_config['port'], $this->redis_config['timeout'] ?? null);
            $redis->select($this->redis_config['database'] ?? 0);
            if ($redis->exists(self::CLIENTS_KEY)) {
                $clients = unserialize($redis->get(self::CLIENTS_KEY));
                if (isset($clients[$fd]))
                    unset($clients[$fd]);
                $redis->set(self::CLIENTS_KEY, serialize($clients));
            }
            if ($redis->exists(self::PUSH_CLIENTS_KEY)) {
                $push_clients = unserialize($redis->get(self::PUSH_CLIENTS_KEY));
                if (isset($push_clients[$fd]))
                    unset($push_clients[$fd]);
                $redis->set(self::PUSH_CLIENTS_KEY, serialize($push_clients));
            }
            $redis->close();
        } catch (\RedisException $e) {

        }
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
        //响应消息头
        $reply_header['reply_version'] = 1;
        $reply_body = [
            "errCode" => 0,
            "msg" => "success",
            "data" => []
        ];
        //先获取请求数据.包括包头和包体
        $fd = $param['fd'];
        $data = $param['data'];
        $uuid = substr($data, 8, 33);
        $Header_C = unpack("C4", $data);
        //消息类别
        $msg_type = $Header_C[2];
        //响应包体是否需要加密标识 aes
        $reply_header['replyCipher'] = $Header_C[3];
        //响应包体是否压缩标识  =0(没用压缩)	=1(zlib压缩) 先压缩后加密/先解密后解压缩
        $reply_header['replyCompress'] = $Header_C[4];
        $reply_header['uuid'] = $uuid;

        switch ($msg_type) {
            //请求密钥
            case 1:
                $redis = new \Redis();
                $clients = $this->getValue($redis, self::CLIENTS_KEY);
                if (!$clients)
                    $clients = [];
                else
                    $clients = unserialize($clients);
                //对客户端进行记录
                if (!array_key_exists($fd, $clients)) {
                    $clients[$fd] = [
                        'uuid' => $uuid,
                        'cipher_key' => ''
                    ];
                }
                if ($clients[$fd]['cipher_key'] === '') {
                    $cipher_key = GetUniqueString::str_rand(32);
                    $clients[$fd]['cipher_key'] = $cipher_key;
                }
                $reply_header['reply_msg_type'] = 2;
                $reply_header['replyCipher'] = 0;
                $reply_body['data'] = ["cipher_key" => $clients[$fd]['cipher_key']];
                $this->setValue($redis, self::CLIENTS_KEY, serialize($clients));
                $this->send($fd, $reply_header, $reply_body, $clients[$fd]['cipher_key']);
                break;
            //请求心跳
            case 3:
                $reply_header['reply_msg_type'] = 4;
                $reply_body['data'] = ["heartbeat" => time()];
                $this->send($fd, $reply_header, $reply_body);
                break;
            //推送请求  single
            case 5:
                //有消息需要推送时,可从redis中获取需要推送的相关客户端
                $redis = new \Redis();
                $clients = $this->getValue($redis, self::CLIENTS_KEY);
                if (!$clients)
                    $clients = [];
                else
                    $clients = unserialize($clients);
                //对客户端进行记录
                if (!array_key_exists($fd, $clients)) {
                    $clients[$fd] = [
                        'uuid' => $uuid,
                        'cipher_key' => ''
                    ];
                }
                $this->setValue($redis, self::CLIENTS_KEY, serialize($clients));
                $reply_header['reply_msg_type'] = 6;
                $req_body = substr($data, self::PAG_HEAD_LENGHT);
                $req_body = json_decode($req_body);
                //接口code
                if (is_object($req_body) && property_exists($req_body, "code")) {
                    $push_clients = $this->getValue($redis, self::PUSH_CLIENTS_KEY);
                    if (!$push_clients)
                        $push_clients = [];
                    else
                        $push_clients = unserialize($push_clients);
                    if (!array_key_exists($fd, $push_clients)) {
                        $push_clients[$fd]['code'] = $req_body->code;
                        $push_clients[$fd]['reply_header'] = $reply_header;
                    }
                    $this->setValue($redis, self::PUSH_CLIENTS_KEY, serialize($push_clients));
                    $reply_body['data'] = $req_body;
                } else {
                    $reply_body['errCode'] = -1;
                    $reply_body['msg'] = "错误格式的消息体";
                    $reply_body['data'] = $req_body;
                    $this->send($fd, $reply_header, $reply_body);
                    $serv->close($fd);
                }
                //push test
                /*  swoole_timer_tick(5000, function ($timer_id) use ($fd, $reply_header, $reply_body) {
                      $this->send($fd, $reply_header, $reply_body);
                  });*/
                break;
            //上报消息统计
            case 7:
                $reply_msg_type = 8;
                //测试,将监控上报消息推送给接收推送客户端 (实际情况可落地存储和分发告警人员等)
                $req_body = substr($data, self::PAG_HEAD_LENGHT);
                $reply_body['data'] = $req_body;
                $redis = new \Redis();
                $push_clients = $this->getValue($redis, self::PUSH_CLIENTS_KEY);
                if ($push_clients)
                    $push_clients = unserialize($push_clients);
                foreach ($push_clients as $fd => $value) {
                    $this->send($fd, $value['reply_header'], $reply_body);
                }
                break;
        }
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
     * 此事件在worker进程/task进程启动时发生
     *
     * @param swoole_server $serv
     * @param int $worker_id
     */
    function onWorkerStart($serv, $worker_id)
    {
        //进程都为独立进程,数据不可共享
        //可设置tick异步定时任务处理业务上的逻辑
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
        /*  try {
              if ($serv->redis->ping() !== "+PONG") {
                  $this->writer->warning("存储客户端Redis连接断开");
                  $serv->redis->close();
                  $serv->redis = new Client($this->redis_config);;
              }
          } catch (PredisException $e) {
              $this->writer->error("存储客户端Redis连接异常" . $e->getMessage());
              $serv->redis->close();
              $serv->redis = new Client($this->redis_config);;
          }*/
    }


    /***
     * get value from redis
     * @param $redis
     * @param $key
     * @return bool
     */
    private function getValue(&$redis, $key)
    {
        //get tcp connect clients from redis
        try {
            $redis->connect($this->redis_config['host'], $this->redis_config['port'], $this->redis_config['timeout'] ?? null);
            $redis->select($this->redis_config['database'] ?? 0);
            if ($redis->exists($key)) {
                return $redis->get($key);
            }
        } catch (\RedisException $e) {

        }
        return false;
    }

    /**
     * set value to redis
     * @param $redis object pointer
     * @param $key string
     * @param $value
     */
    private function setValue(&$redis, $key, $value)
    {
        try {
            $redis->set($key, $value);
            $redis->close();
        } catch (\RedisException $e) {

        }
    }

    /**
     * send msg to client
     * @param $fd
     * @param $reply_header
     * @param $reply_body
     * @param $cipher_key
     */
    private function send($fd, &$reply_header, $reply_body, $cipher_key = '')
    {
        $reply_body_str = json_encode($reply_body);
        if ($reply_header['replyCompress'] === 0 && strlen($reply_body_str) > self::REPLY_MAX_BODY) {
            $reply_header['replyCompress'] = 1;
        }
        if ($reply_header['replyCompress'] === 1) {
            $reply_body_str = gzcompress($reply_body_str, 9);
        }
        if ($reply_header['replyCipher'] === 1 && $reply_header['reply_msg_type'] !== 4) {
            if ($cipher_key === '') {
                $reply_body['errCode'] = -1;
                $reply_body['msg'] = "请先请求密钥";
                if ($reply_header['replyCompress'] === 1) {
                    $reply_body_str = gzcompress(json_encode($reply_body), 9);
                } else {
                    $reply_body_str = json_encode($reply_body);
                }
            } else {
                $aes = new AesCrypt($cipher_key);
                $reply_body_str = $aes->crypt($reply_body_str);
            }
        }
        $reply_msg_lenght = self::PAG_HEAD_LENGHT + strlen($reply_body_str);
        $reply_msg = pack("C", $reply_header['reply_version']) . pack("C", $reply_header['reply_msg_type']) . pack("C", $reply_header['replyCipher']) . pack("C", $reply_header['replyCompress']) . pack("V", $reply_msg_lenght) . $reply_header['uuid'] . $reply_body_str;
        $this->serv->send($fd, $reply_msg);
    }
}
