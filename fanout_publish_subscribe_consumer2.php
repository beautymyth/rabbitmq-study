<?php

//用于自动加载类中的Lib目录定位
define('LIB_PATH', __DIR__ . '/Lib/');

//自动加载类
require_once __DIR__ . '/ComModule/AutoLoader/Loader.php';

//引入连接与消息类
use Lib\PhpAmqpLib\Connection\AMQPStreamConnection;
use Lib\PhpAmqpLib\Message\AMQPMessage;

//交换器名
$strExchange = 'exchange_fanout_publish_subscribe';

//队列名-自动生成
$strQueue = '';

//消费者标识
$strConsumerTag = 'consumer_fanout_publish_subscribe';

/**
 * 连接rabbit服务器
 * host:主机名
 * port:端口号
 * user:通过[rabbitmqctl add_user]创建的
 * password:通过[rabbitmqctl add_user]创建的
 */
$objConnection = new AMQPStreamConnection('127.0.0.1', 5672, 'admin', 'admin');

//获取或创建一个信道
$objChannel = $objConnection->channel();

/**
 * 获取一个队列，如果不存在则新建
 * name:队列名
 * passive:false
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
$arrReturn = $objChannel->queue_declare('', false, false, true, true);
$strQueue = $arrReturn[0];
/**
 * 获取一个交换器，如果不存在则新建
 * name:交换器名
 * type:交换器类型(fanout,direct,topic,headers)
 * passive:false
 * durable:是否持久化的，服务器重启队列不消失
 * auto_delete：是否自动删除，当被队列或交换器过，且最后所有的队列或交换器都解绑了
 */
$objChannel->exchange_declare($strExchange, 'fanout', false, true, false);

//将交换器与队列进行绑定，之后消息可以根据交换器路由到不同的队列
$objChannel->queue_bind($strQueue, $strExchange);

//定义回调函数
function callback_func($objMessage) {
    echo " [x] Received ", $objMessage->body, "\n";
}

//php中止时执行的函数
function shutdown($objChannel, $objConnection) {
    //关闭信道与断开连接
    $objChannel->close();
    $objConnection->close();
}

//注册一个会在php中止时执行的函数
register_shutdown_function('shutdown', $objChannel, $objConnection);

/**
 * queue:从哪个队列获取信息
 * consumer_tag:消费者标识
 * no_local:不接受此消费者发布的消息
 * no_ack:服务器是否等待用户确认消息
 * exclusive:当消费者访问此队列时，是否允许其他消费者访问
 * nowait:
 * callback:回调函数
 */
$objChannel->basic_consume($strQueue, $strConsumerTag, false, true, false, false, 'callback_func');


//阻塞等待服务器推送消息
while (count($objChannel->callbacks)) {
    $objChannel->wait();
}



