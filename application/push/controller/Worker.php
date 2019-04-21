<?php

namespace app\push\controller;

use think\Db;
use think\Cache;
use think\worker\Server;
use Workerman\Lib\Timer;
use Workerman\Connection\AsyncTcpConnection;
// 心跳间隔55秒
define('HEARTBEAT_TIME', 55);

class Worker extends Server
{

    /**
     * 收到信息
     * @param $connection
     * @param $data
     */
    public function onMessage($connection, $data)
    {
        /*$task_connection = new AsyncTcpConnection('Text://127.0.0.1:12345');
        $task_connection->send(json_encode($data));
        // 异步获得结果
        $task_connection->onMessage = function($task_connection, $task_result)use($connection)
        {
            // 结果
            //var_dump($task_result);
            // 获得结果后记得关闭异步连接
            $task_connection->close();
            // 通知对应的websocket客户端任务完成
            $connection->send($task_result);
        };
        // 执行异步连接
        $task_connection->connect();
        return;*/
        // 客户端传递的是json数据
        if(!is_array($data))
        {
            $message_data = json_decode($data, true);
        }else{
            return ;
        }
        $connection->lastMessageTime = time();
        if(!isset($message_data['type']))
            return;
        // 根据类型执行不同的业务
        switch($message_data['type'])
        {
            // 客户端回应服务端的心跳
            case 'pong':

                return ;        
            
            // 客户端登录 message格式: {type:login, name:xx, room_id:1} ，添加到客户端，广播给所有客户端xx进入聊天室
            case 'login':
                // 判断当前客户端是否已经验证,即是否设置了uid
                if(!isset($connection->uid))
			    {
			       // 没验证的话把第一个包当做uid（这里为了方便演示，没做真正的验证）
			       $connection->uid = $message_data['client_name'];
			       /* 保存uid到connection的映射，这样可以方便的通过uid查找connection，
			        * 实现针对特定uid推送数据
			        */
			    	$worker = $this->worker;//dump($worker);die;
                    $old_message = Cache::get('message');
			    	foreach($worker->connections as $conn)
				    {
				        if($conn->uid != $connection->uid){
                            $conn->send("{'type':'reply','name':'".$connection->uid."','data':'我上线了哟'}");
                        }else{
                            $conn->send("{'type':'reply','name':'".$connection->uid."','old_message':".$old_message."}");
                        }
				    }
			    }
                break;

            case 'say':
                if($message_data['msg']){
                    $old_message = json_decode(Cache::get('message'),true);
                    if(is_array($old_message)){
                        $old_message[] = ['name'=>$connection->uid,'message'=>$message_data['msg'],'time'=>time()];
                        $message = json_encode($old_message);
                    }else
                        $message = json_encode([['name'=>$connection->uid,'message'=>$message_data['msg'],'time'=>time()]]);
                    Cache::set('message',$message);
                }
                $worker = $this->worker;
                foreach($worker->connections as $conn)
                {
                    $conn->send("{'type':'reply','name':'".$connection->uid."','data':'".$message_data['msg']."'}");
                }
               	return;
        }
    }

    /**
     * 当连接建立时触发的回调函数
     * @param $connection
     */
    public function onConnect($connection)
    {

    }
    /**
	 * 向所有验证的用户发送消息
     */
    /*public function sendAllMessage(){
    	global $worker;
	   	foreach($worker->uidConnections as $connection)
	   	{
	    	$connection->send($message);
	   	}
    }*/
    /**
     * 当连接断开时触发的回调函数
     * @param $connection
     */
    public function onClose($connection)
    {
        /*$worker = $this->worker;
        foreach($worker->connections as $conn)
        {
            if($conn->uid != $connection->uid){
                $conn->send("{'type':'reply','name':'".$connection->uid."','data':'我下线了哟'}");
            }
        }*/
    }

    /**
     * 当客户端的连接上发生错误时触发
     * @param $connection
     * @param $code
     * @param $msg
     */
    public function onError($connection, $code, $msg)
    {
        echo "error $code $msg\n";
    }

    /**
     * 每个进程启动
     * @param $worker
     */
    public function onWorkerStart($worker)
    {
        Timer::add(10, function()use($worker){
            $time_now = time();
            foreach($worker->connections as $connection) {
                // 有可能该connection还没收到过消息，则lastMessageTime设置为当前时间
                if (empty($connection->lastMessageTime)) {
                    $connection->lastMessageTime = $time_now;
                    continue;
                }
                // 上次通讯时间间隔大于心跳间隔，则认为客户端已经下线，关闭连接
                if ($time_now - $connection->lastMessageTime > HEARTBEAT_TIME) {
                    $connection->close();
                }else{
                    $connection->send("{'type':'ping','name':'".$connection->uid."','data':''}");
                }
            }
        });
    }
}