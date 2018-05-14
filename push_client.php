<?php
header("Content-type: text/html; charset=utf-8");
$push_client_id = time();
$push_data = [
    'method' => 'push', 
    'data' => [
        'push_client_id' => $push_client_id,
        'message' => '推送服务端ID'.$push_client_id.'随机一条信息', 
        'token' => 'xxxxxyyyy'
    ]
];
$message = json_encode($push_data);

$host = '192.168.128.128';
$port = '8898';   // TCP的端口  $serv->addlistener('0.0.0.0', 8898, SWOOLE_SOCK_TCP);
$data = send_tcp_message($host, $port, $message);
$server_data = json_decode($data, true);
if($server_data['status'] == 1) {
    echo '服务端已经处理推送请求';
} else {
    echo '服务端处理失败,错误代码:'.$server_data['data']['error_code'];
}
function send_tcp_message($host, $port, $message)
{
    $message = $message."\r\n";
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    @socket_connect($socket, $host, $port);
 
    $num = 0;
    $length = strlen($message);
    do
    {
        $buffer = substr($message, $num);
        $ret = @socket_write($socket, $buffer);
        $num += $ret;
    } while ($num < $length);
 
    $ret = '';
    do
    {
        $buffer = @socket_read($socket, 1024, PHP_BINARY_READ);
        $ret .= $buffer;
    } while (strlen($buffer) == 1024);
 
    socket_close($socket);
 
    return $ret;
}