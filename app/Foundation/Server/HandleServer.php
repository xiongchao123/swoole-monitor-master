<?php
/**
 * Created by PhpStorm.
 * User: XiongChao
 * Date: 2017/12/7
 * Time: 16:10
 */
/*
 * 分化Server业务处理模块,避免Serve过于臃肿
 */
namespace App\Foundation\Server;

use App\Foundation\Cache\HandleRedis;
use App\Server\Scripts\Swoole;
use App\Server\Server;
use App\util\AesCrypt;
use App\util\GetUniqueString;


trait HandleServer
{
    use HandleRedis;

    private $client_keys = "TCP_CONNECT_CLIENTS";  //save clients to redis key
    private $push_client_keys = "TCP_PUSH_CONNECT_CLIENTS";  //save clients to redis key


    private function listenTcpStart()
    {
        $this->connectR();
        $this->redis->del($this->client_keys);
        $this->redis->del($this->push_client_keys);
        $this->redis->hset(Server::$master_pid_key, $this->ini["serve_name"], $this->master_pid);
        $this->closeR();
    }


    /**
     * @param $fd
     */
    private function listenTcpClose($fd)
    {
        try {
            $this->connectR();
            if ($this->redis->exists($this->client_keys)) {
                $clients = unserialize($this->getR($this->client_keys));
                if (isset($clients[$fd]))
                    unset($clients[$fd]);
                $this->setR($this->client_keys, serialize($clients));
            }
            if ($this->redis->exists($this->push_client_keys)) {
                $push_clients = unserialize($this->getR($this->push_client_keys));
                if (isset($push_clients[$fd]))
                    unset($push_clients[$fd]);
                $this->setR($this->push_client_keys, serialize($push_clients));
            }
            $this->closeR();
        } catch (\RedisException $e) {

        }
    }


    /**
     * @param $serv
     * @param $param
     */
    protected function handleTask($serv, $param)
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
        $uuid = substr($data, 12, 33);
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
                $this->connectR();
                $clients = $this->getR($this->client_keys);
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
                $this->setR($this->client_keys, serialize($clients));
                $this->closeR();
                $this->send($serv, $fd, $reply_header, $reply_body, $clients[$fd]['cipher_key']);
                break;
            //请求心跳
            case 3:
                $reply_header['reply_msg_type'] = 4;
                $reply_body['data'] = ["heartbeat" => time()];
                $this->send($serv, $fd, $reply_header, $reply_body);
                break;
            //推送请求  single
            case 5:
                //有消息需要推送时,可从redis中获取需要推送的相关客户端
                $this->connectR();
                $clients = $this->getR($this->client_keys);
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
                $this->setR($this->client_keys, serialize($clients));
                $reply_header['reply_msg_type'] = 6;
                $req_body = substr($data, Swoole::PAG_HEAD_LENGHT);
                $req_body = json_decode($req_body);
                //接口code
                if (is_object($req_body) && property_exists($req_body, "code")) {
                    $push_clients = $this->getR($this->push_client_keys);
                    if (!$push_clients)
                        $push_clients = [];
                    else
                        $push_clients = unserialize($push_clients);
                    if (!array_key_exists($fd, $push_clients)) {
                        $push_clients[$fd]['code'] = $req_body->code;
                        $push_clients[$fd]['reply_header'] = $reply_header;
                    }
                    $this->setR($this->push_client_keys, serialize($push_clients));
                    $reply_body['data'] = $req_body;
                } else {
                    $reply_body['errCode'] = -1;
                    $reply_body['msg'] = "错误格式的消息体";
                    $reply_body['data'] = $req_body;
                    $this->send($serv, $fd, $reply_header, $reply_body);
                    $serv->close($fd);
                }
                $this->closeR();
                //push test
                /*  swoole_timer_tick(5000, function ($timer_id) use ($fd, $reply_header, $reply_body) {
                     $this->send($serv,$fd, $value['reply_header'], $reply_body);
                  });*/
                break;
            //上报消息统计
            case 7:
                $reply_msg_type = 8;
                //测试,将监控上报消息推送给接收推送客户端 (实际情况可落地存储和分发告警人员等)
                $req_body = substr($data, Swoole::PAG_HEAD_LENGHT);
                $reply_body['data'] = $req_body;
                $this->connectR();
                $push_clients = $this->getR($this->push_client_keys);
                if ($push_clients)
                    $push_clients = unserialize($push_clients);
                if (is_array($push_clients) && count($push_clients) > 0) {
                    foreach ($push_clients as $fd => $value) {
                        $this->send($serv, $fd, $value['reply_header'], $reply_body);
                    }
                }
                $this->closeR();
                break;
        }
    }

    /**
     * send msg to client
     * @param $serv \swoole_server
     * @param $fd
     * @param $reply_header
     * @param $reply_body
     * @param $cipher_key
     */
    function send($serv, $fd, &$reply_header, $reply_body, $cipher_key = '')
    {
        $reply_body_str = json_encode($reply_body);
        if ($reply_header['replyCompress'] === 0 && strlen($reply_body_str) > Swoole::REPLY_MAX_BODY) {
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
        $reply_msg_lenght = Swoole::PAG_HEAD_LENGHT + strlen($reply_body_str);
        $reply_crc_bf = pack("C", $reply_header['reply_version']) . pack("C", $reply_header['reply_msg_type']) . pack("C", $reply_header['replyCipher']) . pack("C", $reply_header['replyCompress']) . pack("V", $reply_msg_lenght);
        $reply_crc = pack("V", sprintf("%u", crc32($reply_crc_bf)));
        $reply_crc_af = $reply_header['uuid'] . $reply_body_str;
        $serv->send($fd, $reply_crc_bf . $reply_crc . $reply_crc_af);
    }

}