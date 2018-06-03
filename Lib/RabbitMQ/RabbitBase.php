<?php

namespace Lib\RabbitMQ;

use Lib\PhpAmqpLib\Connection\AMQPStreamConnection;
use Lib\PhpAmqpLib\Message\AMQPMessage;
use Lib\PhpAmqpLib\Channel\AMQPChannel;
use Lib\PhpAmqpLib\Wire;

/**
 * Rabbit基类
 */
class RabbitBase {

    /**
     * rabbit服务器
     */
    private $arrRabbitServer = [];

    /**
     * rabbit连接超时时间(秒)
     */
    private $intConnectTimeOut = 3;

    /**
     * rabbit读取超时时间(秒)
     */
    private $intReadWriteTimeOut = 3;

    /**
     * 连接对象
     */
    private $objConnection = null;

    /**
     * 信道对象
     */
    private $objChannel = null;

    /**
     * 获取连接对象
     */
    protected function getConnection() {
        if (is_null($this->objConnection)) {
            $this->createConnect();
        }
        return $this->objConnection;
    }

    /**
     * 获取信道对象
     * @param int $intChannelID 信道id
     */
    protected function getChannel($intChannelID = null) {
        if (is_null($this->objChannel) && !is_null($this->getConnection())) {
            $this->objChannel = $this->getConnection()->channel($intChannelID);
        }
        return $this->objChannel;
    }

    /**
     * 构造函数
     */
    public function __construct() {
        $this->arrRabbitServer = [
            ['host' => '10.100.3.106', 'port' => 5672, 'user' => 'admin', 'password' => 'admin'],
            ['host' => '10.100.2.234', 'port' => 5672, 'user' => 'admin', 'password' => 'admin'],
            ['host' => '10.100.2.235', 'port' => 5672, 'user' => 'admin', 'password' => 'admin']
        ];
    }

    /**
     * 析构函数
     */
    public function __destruct() {
        $this->reset();
    }

    /**
     * 重置连接
     * 1.置为null是不能直接关闭连接的 ,如果服务器异常会自动关闭连接
     * 2.当调用close时，如果服务器异常，也会有异常信息抛出，需要进行捕捉
     */
    protected function reset() {
        try {
            if ($this->objChannel instanceof AMQPChannel) {
                $this->objChannel->close();
            }
        } catch (\Exception $e) {
            
        } finally {
            $this->objChannel = null;
        }

        try {
            if ($this->objConnection instanceof AMQPStreamConnection) {
                $this->objConnection->close();
            }
        } catch (\Exception $e) {
            
        } finally {
            $this->objConnection = null;
        }
    }

    /**
     * 创建连接
     * <br>随机获取一个有效的服务器连接
     */
    private function createConnect() {
        //1.随机获取rabbit服务器
        $intTryCount = 1;
        $intTotalCount = count($this->arrRabbitServer);
        $arrRabbitServer = $this->arrRabbitServer;
        while ($intTryCount <= $intTotalCount) {
            $strRandomKey = rand(0, count($arrRabbitServer) - 1);
            $strRandomKey = array_keys($arrRabbitServer)[$strRandomKey];
            $arrServer = $arrRabbitServer[$strRandomKey];
            try {
                $this->objConnection = new AMQPStreamConnection($arrServer['host'], $arrServer['port'], $arrServer['user'], $arrServer['password'], '/', false, 'AMQPLAIN', null, 'en_US', $this->intConnectTimeOut, $this->intReadWriteTimeOut);
                echo json_encode($arrServer) . PHP_EOL;
                break;
            } catch (\Exception $e) {
                echo $e->getMessage() . PHP_EOL;
                unset($arrRabbitServer[$strRandomKey]);
            }
            $intTryCount++;
        }
        //2.检查是否能连接到服务器
        if (is_null($this->objConnection)) {
            throw new \Exception('RabbitMQ服务器连接失败');
        }
    }

    /**
     * 创建交换器
     * @param string $strExchangeName 交换器名称
     * @param string $strExchangeType 交换器类别，【'direct', 'fanout', 'topic'】
     * @param bool $blnNeedAe 是否需要备用交换器，默认为false
     * <br>true：当消息不能被路由时进入此交换器，自动创建备用交换器与队列并绑定
     * <br>备用交换器名：ae_exchange_{$strExchangeName} 交换器类别：direct 队列名：ae_queue_{$strExchangeName}
     */
    protected function declareExchange($strExchangeName, $strExchangeType, $blnNeedAe = false) {
        //1.参数校验
        if (empty($strExchangeName)) {
            throw new \Exception('交换器名不能为空');
        }
        if (!in_array($strExchangeType, ['direct', 'fanout', 'topic'])) {
            throw new \Exception('交换器类别错误');
        }
        //2.备用交换器
        $arrArgument = [];
        if ($blnNeedAe) {
            $strAeExchangeName = "ae_exchange_{$strExchangeName}";
            $strAeQueueName = "ae_queue_{$strExchangeName}";
            //生成参数
            $arrArgument = new Wire\AMQPTable([
                'alternate-exchange' => $strAeExchangeName
            ]);
            //1.创建备用交换器
            $this->declareExchange($strAeExchangeName, 'fanout');
            //2.创建备用队列
            $this->declareQueue($strAeQueueName);
            //3.绑定备用交换器与队列
            $this->bindExchangeQueue($strAeExchangeName, $strAeQueueName);
        }
        //3.创建交换器
        $this->getChannel()->exchange_declare($strExchangeName, $strExchangeType, false, true, false, false, false, $arrArgument);
    }

    /**
     * 创建队列
     * @param string $strQueueName 队列名
     * @param type $blnNeedDq 是否需要死信队列，默认为false
     * <br>true：当消息被拒绝,过期,队列达到最大长度 ，会进入死信队列；自动创建死信交换器与队列并绑定
     * <br>死信交换器名：dq_exchange_{$strExchangeType}_{$strExchangeName} 交换器类别：direct 队列名：ae_queue_{$strExchangeType}_{$strExchangeName}
     */
    protected function declareQueue($strQueueName, $blnNeedDq = false) {
        //1.参数校验
        if (empty($strQueueName)) {
            throw new \Exception('队列名不能为空');
        }
        //2.死信队列
        $arrArgument = [];
        if ($blnNeedDq) {
            $strDqExchangeName = "dq_exchange_{$strQueueName}";
            $strDqQueueName = "dq_queue_{$strQueueName}";
            $strDqRouteKey = "dq_rk";
            //生成参数
            $arrArgument = new Wire\AMQPTable([
                'x-dead-letter-exchange' => $strDqExchangeName,
                'x-dead-letter-routing-key' => $strDqRouteKey
            ]);
            //1.创建死信交换器
            $this->declareExchange($strDqExchangeName, 'direct');
            //2.创建死信队列
            $this->declareQueue($strDqQueueName);
            //3.绑定死信交换器与队列
            $this->bindExchangeQueue($strDqExchangeName, $strDqQueueName, [$strDqRouteKey]);
        }
        //3.创建队列
        $this->getChannel()->queue_declare($strQueueName, false, true, false, false, false, $arrArgument);
    }

    /**
     * 交换器与队列绑定
     * @param string $strExchangeName 交换器名
     * @param string $strQueueName 队列名
     * @param array $arrRoutingKey 路由键
     */
    protected function bindExchangeQueue($strExchangeName, $strQueueName, $arrRoutingKey = []) {
        //1.参数校验
        if (empty($strExchangeName) || empty($strQueueName)) {
            throw new \Exception('交换器名与队列名不能为空');
        }
        //2.绑定
        if (count($arrRoutingKey) == 0) {
            $this->getChannel()->queue_bind($strQueueName, $strExchangeName);
        } else {
            foreach ($arrRoutingKey as $strRoutingKey) {
                $this->getChannel()->queue_bind($strQueueName, $strExchangeName, $strRoutingKey);
            }
        }
    }

}
