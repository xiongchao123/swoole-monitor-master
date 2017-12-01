<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/11/27
 * Time: 15:28
 */


if (! function_exists('config')) {
    /**
     * Get / set the specified configuration value.
     *
     * If an array is passed as the key, we will assume you want to set an array of values.
     *
     * @param  array|string  $key
     * @param  mixed  $default
     * @return mixed
     */
    function config($key = null, $default = null)
    {
        $array=$GLOBALS['config'];
        if (is_null($key)) {
            return $GLOBALS['config'];
        }
        foreach (explode('.',$key) as $segment){
            if (array_key_exists($segment,$array)) {
                $array = $array[$segment];
            } else {
                return $default;
            }
        }
        return $array;
    }
}