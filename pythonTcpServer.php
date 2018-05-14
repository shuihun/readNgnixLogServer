<?php
/**
 * Created by PhpStorm.
 * User: chenjh
 * Date: 2018/1/15
 * Time: 18:57
 * python 监控tcp服务
 * http://blog.molibei.com/archives/105
 */

cli_set_process_title("php pythonTcpServer.php: TcpMaster");  // 设置程序的进程名

$config['is_debug'] = 1;

if ($config['is_debug'] == 1) {
    define("IS_DEBUG", 1);  //  1 测试环境配置
}else{
    define("IS_DEBUG", 0);  //  0-线上环境配置
}

if (1 === IS_DEBUG) {
    define("TCP_SERVER_IP", "0.0.0.0");
    define("TCP_SERVER_PORT", 9501);
    define("REDIS_SERVER_IP", "127.0.0.1");
    define("REDIS_SERVER_PORT", 6379);
    define("REDIS_SERVER_PASSWORD", '123456');
}else{ //
    define("TCP_SERVER_IP", "0.0.0.0");
    define("TCP_SERVER_PORT", 9501);
    define("REDIS_SERVER_IP", "192.168.0.110");
    define("REDIS_SERVER_PORT", 6392);
    define("REDIS_SERVER_PASSWORD", 'mZ7TailMgwrj');
}

define("PATH", dirname(__FILE__));
define("SERV_TICK", 10000);  # 定时给客户端发送检测 10秒
define("PYTHON_CLIENT_NAME_LIST", 'client_list');

$serv = new swoole_server(TCP_SERVER_IP, TCP_SERVER_PORT);

//设置异步任务的工作进程数量-配置
$serv->set(array(
        #设置就是每60秒侦测一次心跳，一个TCP连接如果在300秒内未向服务器端发送数据，将会被切断
        'heartbeat_check_interval' => 60,
        'heartbeat_idle_time' => 300,
        'task_worker_num' => 4,
        'daemonize' => 1,
        'log_file' => PATH."/Logs/python_server_swoole.file",
        'pid_file' => PATH."/Logs/server.pid"
    )
);

$serv->on('WorkerStart', function ($serv){
    if ($serv->worker_id == 0){
        $serv->tick(SERV_TICK, function() use($serv) {
            $start_fd = 0;
            while(true)
            {
                $conn_list = $serv->getClientList($start_fd, 10);
                if ($conn_list===false or count($conn_list) === 0)
                {
                    echo "finish\n";
                    break;
                }
                $start_fd = end($conn_list);
                foreach($conn_list as $fd)
                {
                    $serv->send($fd, get_code_msg_to_client(2999));
                }
            }
        });
    }
});

try {
    $redis = new Redis();
    $redis->connect(REDIS_SERVER_IP, REDIS_SERVER_PORT);
    $redis->auth(REDIS_SERVER_PASSWORD); 
} catch (Exception $e) {
    throw new Exception("redis connect error", 1);
}


//监听连接进入事件
$serv->on('connect', function ($serv, $fd) use ($redis){
    $key = 'user_'.$fd;
    $redis->hset(PYTHON_CLIENT_NAME_LIST, $key, $fd);
});

//监听数据接收事件
$serv->on('receive', function ($serv, $fd, $from_id, $rev_data) use($redis) {
    $data = json_decode($rev_data, true);
    if(empty($data['code']) && !isset($data['code']) && !is_numeric($data['code'])){
        return;
    }
    $code = $data['code']; //操作码
    switch($code) {//根据收到的消息做出不同的响应
        case 2001: { //关闭当前客户端对应的读服务
            
        }
        case 2002: { //更新当前客户端对应的读服务
            
        }
        case 2003: { //重启当前客户端对应的读服务
            
        }
        case 2004: { //关闭当前客户端和对应的读服务
            if(empty($data['op_object']) && !isset($data['op_object']) && !is_numeric($data['op_object'])){
                break;
            }
            $op_object = $data['op_object'];
            $serv->send($op_object,get_code_msg_to_client($code));
            break;
        }
        case 2887: { // --更新所有客户端
            foreach($serv->connections as $fd){
                $serv->send($fd, get_code_msg_to_client(2002));
            }
            break;
        }
        case 2888:{ // -- 重启全部客户端
            foreach($serv->connections as $fd){
                $serv->send($fd, get_code_msg_to_client(2003));
            }
            break;
        }
        case 2999: {
            $key = 'user_'.$fd;
            $redis->hset(PYTHON_CLIENT_NAME_LIST, $key,json_encode($data['data']));
            break;
        }
        default: {
            $serv->send($fd,"this is error code\n");
        }
    }
});

//处理异步任务
$serv->on('task', function ($serv, $task_id, $from_id, $data) {
    handleFun($data);
    //返回任务执行的结果
    $serv->finish("$task_id -> $data ==> ok");
});

//处理异步任务的结果   这边传递的 $data 是  $serv->finish() 的参数
$serv->on('finish', function ($serv, $task_id, $data) {
    write("异步任务处理完成：id = $task_id  , data = $data"  );
});

//监听连接关闭事件
$serv->on('close', function ($serv, $fd) use($redis){
    $key = 'user_'.$fd;
    $redis->hDel(PYTHON_CLIENT_NAME_LIST,$key);
    echo "Client: Close.\n";
});

/*
任务处理函数
*/
function handleFun($data){
    sleep(2);   //休眠2s模拟耗时
    $arr = json_decode($data,true);
}

/**
 *
 * @param string $data
 * @return mixed
 */
function get_json_to_arr($data = ''){
    return json_decode($data,true);
}

/**
 *
 * @param array $data
 * @return string
 */
function get_arr_to_json($data = array()){
    return json_encode($data);
}

/**
 * 通过管理客户端下发的指令转发到客户端的指令
 * @param  string $code [description]
 * @return [type]       [description]
 */
function get_code_msg_to_client($code=2001)
{
    $code_list = array(2001=>1001, 2002=>1001, 2003=>1003, 2004=>1004, 2999=>1999);
    $client_data = array();
    $client_data['code'] = $code_list[$code];
    $client_data['msg'] = '';
    $client_data['data'] = '';
    return json_encode($client_data);
}

//清空用户redis状态数据
$redis->del(PYTHON_CLIENT_NAME_LIST);
//启动服务器
$serv->start();