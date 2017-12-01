<?php
/**
 * Created by PhpStorm.
 * User: Xc
 * Date: 2017/8/25
 * Time: 10:42
 */

class Client
{
    private $client;

    public function __construct() {
        //异步客户端
        $this->client = new \swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
        $this->client->set(array(
           /* 'open_eof_check' => true,
            'package_eof' => "\r\n\r\n",*/
          //  'open_length_check'     => 1,
          //  'package_length_type'   => 'V',
          //  'package_length_offset' => 0,       //第N个字节是包长度的值
          //  'package_body_offset'   => 0,       //第几个字节开始计算长度
            'package_max_length'    => 8192,  //协议最大长度
            'socket_buffer_size'     => 1024*1024*10, //10M缓存区
        ));
  //      $this->client = new swoole_client(SWOOLE_SOCK_TCP);
        $this->client->on('Connect', array($this, 'onConnect'));
        $this->client->on('Receive', array($this, 'onReceive'));
        $this->client->on('Close', array($this, 'onClose'));
        $this->client->on('Error', array($this, 'onError'));
        $this->client->on('BufferFull', array($this, 'onBufferFull'));
        $this->client->on('BufferEmpty', array($this, 'onBufferEmpty'));
    }
    public function connect() {
        $fp = $this->client->connect("127.0.0.1", 9503 , 1);
        if( !$fp ) {
            echo "Error: {$fp->errMsg}[{$fp->errCode}]\n";
            return;
        }

    }

    public function onConnect( $cli) {
	echo "Start\n";
        $message=json_encode([
            'code'=>'demo',
            'status'=>'1'
        ]);
        $length=41+strlen($message);
       // $length=41;
        $uuid=md5(uniqid(microtime(true),true)) . "t";
        $this->client->send(pack("C",1));      //版本号 固定为1
        $this->client->send(pack("C",7));      //消息类别
        $this->client->send(pack("C",0));      //包体是否加密
        $this->client->send(pack("C",0));      //包体是否压缩

        $this->client->send(pack("V",$length));  //整个消息的长度(包头+包体)
        $this->client->send($uuid);          //请求者ID

        $this->client->send($message);

        //根据服务端设置60S请求一次心跳数据
        swoole_timer_tick(15000, function() use($uuid,$length,$message){
            //发送心跳数据
            $this->client->send(pack("C",1));      //版本号 固定为1
            $this->client->send(pack("C",7));      //消息类别
            $this->client->send(pack("C",0));      //包体是否加密
            $this->client->send(pack("C",0));      //包体是否压缩
            $this->client->send(pack("V",$length));  //整个消息的长度(包头+包体)
            $this->client->send($uuid);  //整个消息的长度(包头+包体)
            $this->client->send($message);
        });
    }

    public function onReceive( $cli, $data ) {
       /* $reply_header=unpack("C4",$data);
        var_dump($reply_header);
        $lenght=unpack("V",substr($data,4,4))[1];
        echo "lenght: ".$lenght.PHP_EOL;
        $uuid=substr($data,8,33);
        echo $uuid.PHP_EOL;
        $body=substr($data,41,$lenght-41);
        echo strlen($body)."   ".$body.PHP_EOL;*/
    }

    public function onClose( $cli) {
        echo "WebSocketClient close connection\n";

    }
    public function onError() {
    }

    public function onBufferFull($cli){

    }

    public function onBufferEmpty($cli){

    }

    public function send($data) {
        $this->client->send( $data );
    }

    public function isConnected() {
        return $this->client->isConnected();
    }

}
$cli = new Client();
$cli->connect();
