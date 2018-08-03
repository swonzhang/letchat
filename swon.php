<?php

require"./vendor/autoload.php";

use Illuminate\Database\Capsule\Manager as Capsule;//如果你不喜欢这个名称，as DB;就好 

$database = [ 
     'driver'    => 'mysql',
     'host'      => '127.0.0.1',
     'database'  => 'chat',
     'username'  => 'root',
     'password'  => '',
     'charset'   => 'utf8',
     'collation' => 'utf8_general_ci',
     'prefix'    => ''
 ];
 // 初始化数据库
$capsule = new Capsule;
// 创建链接
$capsule->addConnection($database);
// 设置全局静态可访问
$capsule->setAsGlobal(); 
// 启动Eloquent
$capsule->bootEloquent();


function _exception_handler(Throwable $e)
{
    if ($e instanceof Error)
    {
        echo "catch Error: " . $e->getCode() . '   ' . $e->getMessage() . '<br>';
    }
    else
    {
        echo "catch Exception: " . $e->getCode() . '   ' . $e->getMessage() . '<br>';
    }
}
 
set_exception_handler('_exception_handler');    // 注册异常处理方法来捕获异常


//自定义的错误处理方法
function _error_handler($errno, $errstr ,$errfile, $errline)
{
    echo "错误编号errno: $errno<br>";
    echo "错误信息errstr: $errstr<br>";
    echo "出错文件errfile: $errfile<br>";
    echo "出错行号errline: $errline<br>";
}
 
set_error_handler('_error_handler', E_ALL | E_STRICT);  // 注册错误处理方法来处理所有错误


$server = new swoole_websocket_server("0.0.0.0", 9501);

$server->set([
    'log_level' => SWOOLE_LOG_INFO,
    'trace_flags' => SWOOLE_TRACE_ALL,
    'worker_num' => 2
]);

$server->on('WorkerStart', function ($serv, $worker_id){
    global $argv;
    if($worker_id >= $serv->setting['worker_num']) {
        swoole_set_process_name("php {$argv[0]} task worker");
    } else {
        swoole_set_process_name("php {$argv[0]} event worker");
    }
   


});
$server->on('open', function (swoole_websocket_server $server, $request) {
        echo "server: handshake success with fd{$request->fd}\n";
        $collect = [];
                 $redis = new redis();
        $redis->connect('127.0.0.1', 6379);

                $login = new \App\Http\Controller\Login();
        $hostname = $login->getName();
        var_dump($hostname);
        $redis->set($request->fd,$hostname);

        foreach ($server->connections as $fds) {

            if($server->connection_info($fds)['websocket_status'] == 0){continue;}
            if($request->fd != $fds){

                $name = $redis->get($fds);
                // 告诉所有人有人进来了
                $msg = ['type' => 1,'fd' => $request->fd,'name'=>$hostname];   // type ： 1进入聊天室，2退出聊天室，3发送消息

                $server->push($fds, json_encode($msg));

                $collect[] = ['fd' => $fds,'name' => $name];
            }
        }
        $collect[] = ['fd' => $request->fd,'name' => $hostname];

        $msg = ['type' => 1,'users' => $collect,'fd' => $request->fd,'name' => $hostname];   // type ： 1进入聊天室，2退出聊天室，3发送消息
        $server->push($request->fd, json_encode($msg));

    });
$server->on('message', function (swoole_websocket_server $server, $frame) {
        echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";
         $redis = new redis();
        $redis->connect('127.0.0.1', 6379);
        foreach ($server->connections as $fd) {
            if($fd != $frame->fd && $server->connection_info($fd)['websocket_status'] == 3){
                $name = $redis->get($frame->fd);
                $msg = ['type' => 3,'fd' => $frame->fd,'msg'=>$frame->data,'name' => $name];   //type ： 1进入聊天室，2退出聊天室，3发送消息
                $server->push($fd, json_encode($msg));
            }
        }
        // $server->push($frame->fd, "this is server");
    });
$server->on('close', function ($ser, $fd) {
        echo "client {$fd} closed\n";
        if($ser->connection_info($fd)['websocket_status'] == 0){return;}
        $redis = new redis();
        $redis->connect('127.0.0.1', 6379);
        $name = $redis->get($fd);
        foreach ($ser->connections as $fds) {
            if($fd != $fds && $ser->connection_info($fds)['websocket_status'] == 3){
                
                $msg = ['type' => 2,'fd' => $fd,'name'=>$name];   // type ： 1进入聊天室，2退出聊天室，3发送消息
                $ser->push($fds, json_encode($msg));
            }
        }
        $redis->del($fd);
    });
$server->on('request', function (swoole_http_request $request, swoole_http_response $response) {
        global $server;//调用外部的server
        // $server->connections 遍历所有websocket连接用户的fd，给所有用户推送
        $response->header('Access-Control-Allow-Origin', '*');
        $response->status(200);

        try{
            $server = $request->server;
            if($server['path_info'] == '/login'){
                $login = new \App\Http\Controller\Login();
                $login->login($request->post, $response);
            }

        
            // $response->end('heheh');

        }catch(\Throwable $e){

            
            $json = json_encode(['code'=>$e->getCode(),'content' => '','msg' => $e->getMessage()]);
            $response->end($json);
 
            
        }

    });


$server->start();