<?php
/**
 * Created by PhpStorm.
 * User: Xc
 * Date: 2017/12/7
 * Time: 16:17
 */


return [
    "serve" => [
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
        'package_length_func' =>null,
        'package_max_length' => 81920,   //所能接收的包最大长度 根据实际情况自行配置
        'task_max_request' => 100,  //最大task进程请求数
        'heartbeat_idle_time' => 120,  //表示连接最大允许空闲的时间
        'heartbeat_check_interval' => 60,  //轮询检测时间
        'log_file' => ROOT_PATH . 'storage/logs/swoole.log'
    ]
];