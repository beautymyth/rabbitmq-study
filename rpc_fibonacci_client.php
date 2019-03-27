<?php

//用于自动加载类中的Lib目录定位
define('LIB_PATH', __DIR__ . '/Lib/');

//自动加载类
require_once __DIR__ . '/ComModule/AutoLoader/Loader.php';

//引入连接与消息类
use Lib\PhpAmqpLib\Connection\AMQPStreamConnection;
use Lib\PhpAmqpLib\Message\AMQPMessage;

//客户端类
class FibonacciRpcClient {

    private $connection;
    private $channel;
    private $callback_queue;
    private $response;
    private $corr_id;
    private $strQueue = 'queue_rpc_fibonacci';

    public function __construct() {
        /**
         * 连接rabbit服务器
         * host:主机名
         * port:端口号
         * user:通过[rabbitmqctl add_user]创建的
         * password:通过[rabbitmqctl add_user]创建的
         */
        $this->connection = new AMQPStreamConnection('127.0.0.1', 5672, 'admin', 'admin');

        //获取或创建一个信道
        $this->channel = $this->connection->channel();

        /**
         * 获取一个队列，如果不存在则新建
         * name:队列名
         * passive:是否只检测队列(交换器)是否存在，而不进行队列(交换器)声明参数的匹配检测
         * durable:是否持久化的，服务器重启队列不消失
         * exclusive:是否专有的，允许其他信道访问此队列
         * auto_delete：是否自动删除，当被消费者连接过，且最后所有消费者都断开连接时
         */
        /**
         * 1.自动生成
         * 2.专有队列
         * 3.持久化
         * 4.自动删除
         */
        $arrReturn = $this->channel->queue_declare("", false, false, true, true);
        $this->callback_queue = $arrReturn[0];

        /**
         * queue:从哪个队列获取信息
         * consumer_tag:消费者标识
         * no_local:不接受此消费者发布的消息
         * no_ack:服务器是否等待用户确认消息
         * exclusive:当消费者访问此队列时，是否允许其他消费者访问
         * nowait:
         * callback:回调函数
         */
        $this->channel->basic_consume($this->callback_queue, '', false, false, false, false, array($this, 'onResponse'));
    }

    /**
     * 服务器有返回
     */
    public function onResponse($objRep) {
        //是否响应的自己的请求
        if ($objRep->get('correlation_id') == $this->corr_id) {
            $this->response = $objRep->body;
            //确认请求，从队列删除消息
            $objRep->delivery_info['channel']->basic_ack($objRep->delivery_info['delivery_tag']);
        } else {
            //将消息返回到队列
            $objRep->delivery_info['channel']->basic_nack($objMessage->delivery_info['delivery_tag'], false, true);
        }
    }

    public function call($intN) {
        $this->response = null;
        $this->corr_id = uniqid();
        $objMsg = new AMQPMessage($intN, array('correlation_id' => $this->corr_id, 'reply_to' => $this->callback_queue));
        $this->channel->basic_publish($objMsg, '', $this->strQueue);

        //等待服务器返回
        while (!$this->response) {
            $this->channel->wait();
        }
        return $this->response;
    }

}

$fibonacci_rpc = new FibonacciRpcClient();
while (1) {
    $response = $fibonacci_rpc->call(rand(1, 20));
    echo " [.] Got ", $response, "\n";
    //usleep(100 * 1000);
}
