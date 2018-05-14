<?php
/**
 * Created by PhpStorm.
 * User: chenjh
 * Date: 2018/1/17
 * Time: 10:27
 * 常用redis 发布订阅模式进行监控
 * 发布服务端
 */

$config = parse_ini_file("../conf.ini");
if ($config['is_debug'] == 1) {
    define("IS_DEBUG", 1);  //  1 测试环境配置
}else{
    define("IS_DEBUG", 0);  //  0-线上环境配置
}

if (1 === IS_DEBUG) {
    define("REDIS_SERVER_IP", "127.0.0.1");
    define("REDIS_SERVER_PORT", 6379);
    define("REDIS_SERVER_PASSWORD", '123456');
}else{
    define("REDIS_SERVER_IP", "192.168.0.110");
    define("REDIS_SERVER_PORT", 6392);
    define("REDIS_SERVER_PASSWORD", 'mZ7TailMgwrj');
}

define("REDIS_MONITER_LIST_NAME", "monitor_message");
define("REDIS_PUBLISH_NAME", "monitor_message");

try {
    $redis = new Redis();
    $redis->connect(REDIS_SERVER_IP, REDIS_SERVER_PORT);
    $redis->auth(REDIS_SERVER_PASSWORD); 
} catch (Exception $e) {
    throw new Exception("redis connect error", 1);
}

while(1){
    $content = $redis->lPop(REDIS_MONITER_LIST_NAME);
    if($content){
    	//发布，前面一个参数是发布的频道，后面一个参数是发送的内容
        $ret=$redis->publish(REDIS_PUBLISH_NAME,$content);
    }else{
        sleep(1);
    }
}

?>