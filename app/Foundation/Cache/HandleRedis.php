<?php
/**
 * Created by PhpStorm.
 * User: XiongChao
 * Date: 2017/12/7
 * Time: 16:53
 */
namespace App\Foundation\Cache;

use Predis\Client;

trait HandleRedis
{

    /**
     * @var Client
     */
    private $redis;
    private $redis_config;   //redis connect config


    /**
     * connect redis
     */
    private function connectR()
    {

        $this->redis_config = config("database.redis.default");
        $this->redis = new Client($this->redis_config);
        /* $this->redis=new \Redis();
         $this->redis->connect($this->redis_config['host'], $this->redis_config['port'], $this->redis_config['timeout'] ?? null);
         $this->redis->select($this->redis_config['database'] ?? 0);*/
    }


    /***
     * get value from redis
     * @param $key
     * @return bool
     */
    private function getR($key)
    {
        //get tcp connect clients from redis
        try {
            if ($this->redis->exists($key)) {
                return $this->redis->get($key);
            }
        } catch (\RedisException $e) {

        }
        return false;
    }

    /**
     * set value to redis
     * @param $key string
     * @param $value
     */
    private function setR($key, $value)
    {
        try {
            $this->redis->set($key, $value);
        } catch (\RedisException $e) {

        }
    }

    /**
     * close redis connect
     */
    private function closeR()
    {
        $this->redis->disconnect();
    }

    /**
     * @return bool
     */
    private function isConnectedR()
    {
        return $this->redis->isConnected();
    }
}