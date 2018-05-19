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
 * 获取一个交换器，如果不存在则新建
 * name:交换器名
 * type:交换器类型(fanout,direct,topic,headers)
 * passive:是否需要检查已存在同名的交换器
 * durable:是否持久化的，服务器重启队列不消失
 * auto_delete：是否自动删除，当被队列或交换器过，且最后所有的队列或交换器都解绑了
 */
$objChannel->exchange_declare($strExchange, 'fanout', false, true, false);

//创建消息
//delivery_mode：设置消息持久化
$strMessage = json_encode([
    'msg' => 'hello world',
    'time' => time()
        ]);
$objMessage = new AMQPMessage($strMessage, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
//将消息发送到指定的交换器
//while (1) 
{
    $objChannel->basic_publish($objMessage, $strExchange);
    //usleep(1000 * 10);
}

//关闭信道与断开连接
$objChannel->close();
$objConnection->close();
