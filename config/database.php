<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/11/29
 * Time: 15:14
 */

return [
    'redis' => [
        'default'=>[
            'host' => "127.0.0.1",
            'password' => null,
            'port' => 6379,
            'database' => 0,
            'read_write_timeout' => 2 ,
           // 'persistent' => true ,  //predis 持久连接
        ]
    ]
];