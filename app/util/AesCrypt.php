<?php

/**
 * Created by PhpStorm.
 * User: Xiong
 * Date: 2017/11/1
 * Time: 17:23
 */
namespace App\util;

Class AesCrypt
{
    private $key;      //miyue
    private $cipher;   //aes jiami fangshi

    /**
     * AES constructor.
     * @param string $key
     */
    public function __construct($key = 'LifeBigBoomqwerd')
    {
        // TODO...
        $this->key=$key;
        //AES-128。key长度：16, 24, 32 bytes 对应 AES-128, AES-192, AES-256
        $length = mb_strlen($this->key, '8bit');
        if($length === 16){
            $this->cipher='AES-128-CBC';
        }else if($length === 24){
            $this->cipher='AES-192-CBC';
        }else if($length === 32){
            $this->cipher='AES-256-CBC';
        }else{
            return false;
        }
    }

    /**
     * @param $value
     * @return bool|string
     */
    public function crypt($value)
    {
        $seeds = '0123456789abcdefghijklmnopqrstuvwxyz';
        $length = 16;

        $iv = substr(str_shuffle(str_repeat($seeds, $length)), 0, $length);

        $value = \openssl_encrypt(serialize($value), $this->cipher, $this->key, 0, $iv);

        if ($value === false) {
            return false;
        }

        $iv = base64_encode($iv);

        $mac = hash_hmac('sha256', $iv . $value, $this->key);

        $json = json_encode(compact('iv', 'value', 'mac'));

        if (!is_string($json)) {
            return false;
        }

        return base64_encode($json);
    }

    /**
     * @param $payload
     * @return bool|mixed
     */
    public function decrypt($payload)
    {
        $payload = json_decode(base64_decode($payload), true);

        if (!$payload || !is_array($payload) || !isset($payload['iv']) || !isset($payload['value']) || !isset($payload['mac'])) {
            return false;
        }

        $seeds = '0123456789abcdefghijklmnopqrstuvwxyz';
        $length = 16;

        $bytes = substr(str_shuffle(str_repeat($seeds, $length)), 0, $length);
        $hash = hash_hmac('sha256', $payload['iv'] . $payload['value'], $this->key);
        $calcMac = hash_hmac('sha256', $hash, $bytes, true);

        if (!hash_equals(hash_hmac('sha256', $payload['mac'], $bytes, true), $calcMac)) {
            return false;
        }

        $iv = base64_decode($payload['iv']);

        $decrypted = \openssl_decrypt($payload['value'], $this->cipher, $this->key, 0, $iv);

        if ($decrypted === false) {
            return false;
        }

        return unserialize($decrypted);
    }
}

