<?php

define('LIB_PATH', __DIR__ . '/Lib/');

require_once __DIR__ . '/ComModule/AutoLoader/Loader.php';

use Lib\PhpAmqpLib\Connection\AMQPStreamConnection;
use Lib\PhpAmqpLib\Message\AMQPMessage;

$connection = new AMQPStreamConnection('127.0.0.1', 5672, 'admin', 'admin');
$channel = $connection->channel();
$channel->queue_declare('hello', false, false, false, false);
while (1) {
    $msg = new AMQPMessage('Hello World!' . microtime());
    $channel->basic_publish($msg, '', 'hello');
    usleep(1000 * 10);
}
echo " [x] Sent 'Hello World!'\n";
$channel->close();
$connection->close();
