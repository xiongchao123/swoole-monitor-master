# master

# [Swoole-Private-Protocol](https://github.com/xiongchao123/Swoole-Private-Protocol)
## 功能
* 私有协议接口,用于推送消息、接收上报消息如主机健康信息上报等

## 安装

安装PHP`swoole`拓展：`pecl install swoole`

或到[swoole官网](http://www.swoole.com/)获取安装帮助

Swoole版本请选用1.8.1以上版本。

安装第三方包及自动加载机制 composer install or composer update

## 实现

* 服务端基于swoole+redis实现
* 客户端传输正确的协议头,即可与master端建立通信
* 传入不同的消息类型,master端对其进行处理
    
## 使用方法
* 配置文件位于/config/目录下,app.php配置一些全局变量,database.php配置mysql或者redis连接配置。可通过config("")方法获取变量值。如config("database.redis")获取redis连接配置。
   
## 运行

开启服务：

``` bash
开启swoole_server端
进入master目录
php app/App.php -i e 
模拟接收推送数据
php App/Client/PushClientTest.php
模拟上报数据(本例将上报数据推送给接收推送的所有客户端)
php App/Client/ReportClientTest.php
```

## 服务端接口协议
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
|uuid ( 请求者ID ) | 33  | 客户端请求ID|
