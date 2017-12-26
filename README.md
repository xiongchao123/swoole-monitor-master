# master

# [swoole-monitor-master](https://github.com/xiongchao123/swoole-monitor-master)
## 功能
* 私有协议接口,用于推送消息、接收上报消息如主机健康信息上报等

## 安装

> PHP版本需求： PHP5.4/PHP5.5/PHP5.6/PHP7.0/PHP7.1，不支持PHP5.3
* git下载
> git clone https://github.com/xiongchao123/swoole-monitor-master.git
* composer 安装
> composer require xiongchao/swoole-monitor

安装PHP`swoole`拓展：`pecl install swoole`

或到[swoole官网](http://www.swoole.com/)获取安装帮助

Swoole版本请选用1.8.1以上版本。

## 实现

* 服务端基于swoole+redis实现
* 客户端传输正确的协议头,即可与master端建立通信
* 传入不同的消息类型,master端对其进行处理
    
## 使用方法
* 配置文件swoole.ini用于配置swoole协议参数
```
    ;swoole server config set
    [server]
    host = 0.0.0.0   ;监听的网络地址
    port = 9503      ;监听的端口
    process_title=monitor_master  ;自定义设置进程名称,可为空 windows&macos下不支持
    ;swoole执行脚本文件名 位于app/Serve/scripts 目录下
    script_path=Swoole.php
    
    [server2]
    host = 0.0.0.0
    port = 9504
    process_title=monitor_master2
    script_path=Swoole.php

```
* 全局的配置文件如数据库、redis等连接信息位于/config/目录下,可通过config("")方法获取变量值。如config("database.redis")获取redis连接配置。
* 通过脚本artisan.php进行进程管理。start|stop|restart|reload|status等操作。
* php artisan.php --help 或者-h 可查看命令帮助,php artisan.php --help
* php artisan.php list 可查看命令列表
  
## 运行

#### 开启服务：

* 启动所有服务
``` bash
tcp:serve为自定义的命令名称,--daemon为可选参数(在 start、restart和reload的时候可加),默认为false,设置为true的时候进程以守护进程模式启动
php artisan.php tcp:serve start --daemon=true
测试结果如下:
[server] 服务启动成功,进程ID: 21836
[server2] 服务启动成功,进程ID: 21845

php artisan.php tcp:serve stop
测试结果如下:
[server]服务进程ID:21836,已停止
[server2]服务进程ID:21845,已停止

...

```
* 启动单个服务

``` bash
tcp:single为自定义的命令名称,--option为并填参数,可为start|stop|restart|reload|status,--serve为并填参数,为配置文件swoole.ini中的section值,--daemon为可选参数(在 start、restart和reload的时候可加),默认为false,设置为true的时候进程以守护进程模式启动
php artisan.php tcp:single --option=start --serve=server --daemon=true
测试结果如下:
[server] 服务启动成功,进程ID: 21836

php artisan.php tcp:single --option=stop --serve=server 
测试结果如下:
[server]服务进程ID:21836,已停止

...

```
#### 测试服务

* 查看服务运行状态
``` bash
php artisan.php tcp:serve status

php artisan.php tcp:single --option=status --serve=server 

```
* 测试服务通讯是否正常

``` bash

模拟接收推送数据
php App/Client/PushClientTest.php
模拟上报数据(本例将上报数据推送给接收推送的所有客户端)
php App/Client/ReportClientTest.php

```

## 测试服务端接口协议
####  通信协议为长连接，字节流，包含消息头和消息体两部分

|名称|长度|说明|
|:----    |:----- |-----   |
|消息头(header) |  41   | 详见消息头定义表    |
|消息体(content) | 变长   | 对于请求参数，是接口的入参，json格式，例如：{"code":"demo","startDate":"20170825"}，其中code为接口名，必传。对于响应消息，是返回的数据集合，json格式：例如{"data":[],"errCode":0,"msg":"demo"} ，其中errCode错误码0（成功） -1（失败），data为返回的记录数组。|

### 消息头定义表

|名称|长度|说明|
|:----   |:----- |-----   |
|version ( 版本号 ) | 1  | 固定传1(无符号字符，以下相同)|
|msg_type ( 消息类别 )| 1  | 1-请求密钥消息，2-返回密钥消息，3-心跳请求消息，4-心跳应答消息，5-推送请求消息，6-推送应答消息,7-上报请求消息  |
|replyCipher（服务端响应包体是否需要加密） | 1  | 0-不需要加密  1-需要加密|
|compress（包体压缩标识） | 1  | 0-未压缩 1-压缩；先压缩后加密/先解密后解压缩|
|msg_length ( 消息长度 ) | 4  | 整个消息（包含消息头和消息体）的实际长度 (无符号小端字节序)|
|headcrc ( 消息长度 ) | 4  | 以上所有字段的crc32校验和 (无符号小端字节序)|
|uuid ( 请求者ID ) | 33  | 客户端请求ID|
