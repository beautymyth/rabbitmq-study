<?php

/**
 * 1.将消息发送到rabbitmq_node1上的队列
 */
//用于自动加载类中的Lib目录定位
define('LIB_PATH', __DIR__ . '/Lib/');

//自动加载类
require_once __DIR__ . '/ComModule/AutoLoader/Loader.php';

//引入连接与消息类
use Lib\PhpAmqpLib\Connection\AMQPStreamConnection;
use Lib\PhpAmqpLib\Message\AMQPMessage;

//交换器名
$strExchange = 'exchange_direct_cluster_node1';

//队列名
$strQueue = 'queue_direct_cluster_node1';

/**
 * 连接rabbit服务器
 * host:主机名
 * port:端口号
 * user:通过[rabbitmqctl add_user]创建的
 * password:通过[rabbitmqctl add_user]创建的
 */
$objConnection = new AMQPStreamConnection('10.100.3.106', 5672, 'admin', 'admin');

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
$objChannel->queue_declare($strQueue, false, true, false, false);

/**
 * 获取一个交换器，如果不存在则新建
 * name:交换器名
 * type:交换器类型(fanout,direct,topic,headers)
 * passive:false
 * durable:是否持久化的，服务器重启队列不消失
 * auto_delete：是否自动删除，当被队列或交换器过，且最后所有的队列或交换器都解绑了
 */
$objChannel->exchange_declare($strExchange, 'direct', false, true, false);

//将交换器与队列进行绑定，之后消息可以根据交换器路由到不同的队列
$objChannel->queue_bind($strQueue, $strExchange);

while (1) {
    //创建消息
    $objMessage = new AMQPMessage('hello world!' . time());
    //将消息发送到指定的交换器
    $objChannel->basic_publish($objMessage, $strExchange);
    usleep(100 * 1000);
}

//关闭信道与断开连接
$objChannel->close();
$objConnection->close();
