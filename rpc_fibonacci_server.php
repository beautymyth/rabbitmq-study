<?php

//用于自动加载类中的Lib目录定位
define('LIB_PATH', __DIR__ . '/Lib/');

//自动加载类
require_once __DIR__ . '/ComModule/AutoLoader/Loader.php';

//引入连接与消息类
use Lib\PhpAmqpLib\Connection\AMQPStreamConnection;
use Lib\PhpAmqpLib\Message\AMQPMessage;

//队列名
$strQueue = 'queue_rpc_fibonacci';

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
 * passive:是否只检测队列(交换器)是否存在，而不进行队列(交换器)声明参数的匹配检测
 * durable:是否持久化的，服务器重启队列不消失
 * exclusive:是否专有的，允许其他信道访问此队列
 * auto_delete：是否自动删除，当被消费者连接过，且最后所有消费者都断开连接时
 */
$objChannel->queue_declare($strQueue, false, true, false, false);

//逻辑运算
function fib($intN) {
    if ($intN == 0) {
        return 0;
    }
    if ($intN == 1) {
        return 1;
    }
    return fib($intN - 1) + fib($intN - 2);
}

//定义回调函数
function callback_func($objMessage) {
    $intN = $objMessage->body;
    echo " [x] fib(", $intN, ")" . PHP_EOL;
    if ($intN <= 10) {
        //调用函数进行逻辑运算
        $intRst = fib($intN);
        $objReturnMsg = new AMQPMessage($intRst, array('correlation_id' => $objMessage->get('correlation_id')));

        //将计算结果推入客户端预定义的队列
        $objMessage->delivery_info['channel']->basic_publish($objReturnMsg, '', $objMessage->get('reply_to'));
        //确认请求，从队列删除消息
        $objMessage->delivery_info['channel']->basic_ack($objMessage->delivery_info['delivery_tag']);
    } else {
        $objReturnMsg = new AMQPMessage($intN . '>10：不做处理', array('correlation_id' => $objMessage->get('correlation_id')));
        //将计算结果推入客户端预定义的队列
        $objMessage->delivery_info['channel']->basic_publish($objReturnMsg, '', $objMessage->get('reply_to'));
        //拒绝请求，将消息重新放入队列
        $objMessage->delivery_info['channel']->basic_nack($objMessage->delivery_info['delivery_tag'], false, false);
    }
}

//每次只获取一条消息
$objChannel->basic_qos(null, 1, null);
$objChannel->basic_consume($strQueue, '', false, false, false, false, 'callback_func');

echo " [x] Awaiting RPC requests" . PHP_EOL;
while (count($objChannel->callbacks)) {
    $objChannel->wait();
}

//关闭信道与断开连接
$objChannel->close();
$objConnection->close();
