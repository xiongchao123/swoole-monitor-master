<?php
/**
 * Created by PhpStorm.
 * User: XiongChao
 * Date: 2017/12/18
 * Time: 16:38
 */
require_once __DIR__ . "/vendor/autoload.php";

$a = 9;
$b = 9;
echo sprintf("%b", $a|$b)."\n"; //二进制
echo sprintf("%d", $a|$b)."\n"; //十进制

$abc=(function($v){
    $value=explode("|",$v);
    return constant($value[0]) | constant($value[1]);
})("FILE_APPEND|ARRAY_FILTER_USE_KEY");

test($abc);
function test($define){
    var_dump($define);
}


var_dump($v);
if (!isset($v['mode'])) {
    self::$ini['$k']['mode'] = SWOOLE_PROCESS;
} else {
    if (defined($v['mode'])) {
        self::$ini['$k']['mode'] = constant($v['mode']);
    } else {
        self::fatal("unknown mode " . $v['mode']);
    }
}
/*
if (!isset($v['sock_type'])) {
    self::$ini['$k']['sock_type'] = SWOOLE_SOCK_TCP;
} else {
    if (stripos($v['sock_type'], "|") < 0) {
        if (defined($v['sock_type'])) {
            self::$ini['$k']['sock_type'] = $v['sock_type'];
        } else {
            self::fatal("unknown sock_type" . $v['sock_type']);
        }
    } else {
        self::$ini['$k']['sock_type'] = (function (array $arr) : int {
            $socket_type = SWOOLE_SOCK_TCP;
            foreach ($arr as $v) {
                $socket_type = $socket_type | constant($v);
            }
            return $socket_type;
        })(explode("|", $v['sock_type']));
    }
}*/

/*print_r((function (array  $arr): array {
    $result = [];
    foreach ($arr as $k => $v) {
        if (substr_count(implode("", $arr), $v) === 1) {
            array_push($result, $v);
        }
    }
    return $result;
})(['xiongchao', 'xiongchao', 'xiongchaoxiongchao']));

//or array_filter

print_r((function (array  $arr): array {
    return array_filter($arr,function ($k) use ($arr){
        return substr_count(implode(",", $arr), $arr[$k]) === 1;
    },ARRAY_FILTER_USE_KEY );

})(['weizhicheng', 'weizhicheng', 'zhichengwei']));*/