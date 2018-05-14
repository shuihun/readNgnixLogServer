<?php
/**
 * Created by PhpStorm.
 * User: chenjh
 * Date: 2018/1/17
 * Time: 10:28
 * php redis 订阅模板
 */


define("REDIS_SERVER_IP", "127.0.0.1");
define("REDIS_SERVER_PORT", 6379);


$redis = new Redis();
$redis->connect(REDIS_SERVER_IP, REDIS_SERVER_PORT);

try {
    //订阅信息，第一个参数表示要订阅的频道，是个数组，可以多个频道，第二个参数表示回调函数
    $result=$redis->subscribe(array('monitor_message'), 'callback');
} catch (Exception $e) {
    echo 'error';
    exit;
}

//redis订阅的回调函数，第一个参数表示实体，第二个参数表示订阅的频道，第三个参数表示收到的数据，然后可以根据收到的数据进行自己的逻辑处理
function callback($instance, $channelName, $message) {
    if($message){
        echo $message;
    }
}
?>