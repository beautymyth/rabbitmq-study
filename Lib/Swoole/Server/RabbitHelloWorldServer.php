<?php

define('LIB_PATH', getenv('InterviewRootPath') . '/Lib/');

include getenv('InterviewRootPath') . 'ComModule/AutoLoader/Loader.php';

use Lib\RabbitMQ\RabbitConsumerBase;

/**
 * 消息发送服务端
 */
class SwooleHelloWorldServer {

    /**
     * 服务端实例
     */
    private $objServer = null;

    /**
     * 构造函数
     */
    public function __construct() {
        //实例化对象
        //swoole_get_local_ip()获取本机ip
        $this->objServer = new swoole_server('127.0.0.1', '9602');
        //设置运行参数
        $this->objServer->set(array(
            'daemonize' => 1, //以守护进程执行
            'max_request' => 10000, //worker进程在处理完n次请求后结束运行
            'worker_num' => 5,
            "task_ipc_mode " => 3, //使用消息队列通信，并设置为争抢模式,
            'heartbeat_check_interval' => 5, //每隔多少秒检测一次，单位秒，Swoole会轮询所有TCP连接，将超过心跳时间的连接关闭掉
            'heartbeat_idle_time' => 10, //TCP连接的最大闲置时间，单位s , 如果某fd最后一次发包距离现在的时间超过则关闭
            'open_eof_split' => true,
            'package_eof' => "\r\n",
            "log_file" => getenv('InterviewRootPath') . "\logs\swoole.log"
        ));
        //设置事件回调
        $this->objServer->on('Connect', array($this, 'onConnect'));
        $this->objServer->on('Receive', array($this, 'onReceive'));
        $this->objServer->on('Finish', array($this, 'onFinish'));
        $this->objServer->on('Task', array($this, 'onTask'));
        $this->objServer->on('WorkerStart', array($this, 'onWorkerStart'));
        //启动服务
        $this->objServer->start();
    }

    /**
     * 有新的连接进入时
     */
    public function onConnect($server, $fd, $from_id) {
        
    }

    /**
     * 有新的连接进入时
     */
    public function onWorkerStart($server, $worker_id) {
        $objConsumerTopicNumber = new \Lib\App\ConsumerTopicNumber();
        if ($objConsumerTopicNumber->init($worker_id)) {
            $objConsumerTopicNumber->run();
        }
    }

    /**
     * 接收到数据时
     */
    public function onReceive($server, $fd, $reactor_id, $strData) {
        //$this->objServer->task($strData);
    }

    /**
     * task任务完成时
     */
    public function onFinish($serv, $task_id, $strData) {
        
    }

    /**
     * 处理投递的任务
     */
    public function onTask($serv, $task_id, $src_worker_id, $strData) {
        
    }

}

//运行服务
$objSwooleHelloWorldServer = new SwooleHelloWorldServer();
?>
