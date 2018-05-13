<?php

define('LIB_PATH', __DIR__ . '/Lib/');
//define('AMQP_DEBUG', true);

require_once __DIR__ . '/ComModule/AutoLoader/Loader.php';


use Lib\PhpAmqpLib\Connection\AMQPStreamConnection;
//实际应用使用单例？
$connection = new AMQPStreamConnection('127.0.0.1', 5672, 'admin', 'admin');

$channel = $connection->channel('2');
$channel->queue_declare('hello', false, false, false, false);

$callback = function($msg) {
    echo " [x] Received ", $msg->body, "\n";
};
$channel->basic_consume('hello', '', false, false, false, false, $callback);
echo ' [*] Waiting for messages. To exit press CTRL+C', "\n";
//$channel->wait();
//while (count($channel->callbacks)) {
while (1) 
{
    echo " [x] while 1 ", "\n";
    $channel->wait();
}
$channel->close();
$connection->close();
