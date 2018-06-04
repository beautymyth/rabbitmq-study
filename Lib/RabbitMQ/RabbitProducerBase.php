<?php

namespace Lib\RabbitMQ;

use Lib\PhpAmqpLib\Message\AMQPMessage;
use Lib\PhpAmqpLib\Exception\AMQPConnectionException;

/**
 * Rabbit生产者基类
 */
class RabbitProducerBase extends RabbitBase {

    /**
     * 初始化参数
     */
    private $arrInitParam = [];

    /**
     * 此次发送消息的总数
     */
    private $intTotalMessage = 0;

    /**
     * 发送失败的消息
     */
    private $arrFailMessage = [];

    /**
     * 消息发送失败重试次数
     */
    private $intTryCount = 1;

    /**
     * 获取子类类型
     */
    protected function getType() {
        return 'producer';
    }

    /**
     * 构建客户端
     * @param array $arrInitParam 配置信息
     * <br><b>注意：如果在生产与消费端都配置交换器与队列，确保配置信息一致</b>
     * <br>is_recreate：是否每次连接都需要创建交换器与队列，如果服务器已存在交换器与队列就不需要，（true,false），默认为false
     * <br>exchange_name：交换器名，必要
     * <br>exchange_type：交换器类别，(direct,topic,fanout)，必要
     * <br>is_need_ae：是否开启备用交换器，(true,false)，默认false
     * <br>queue_list：需要绑定的队列，可填，[
     * <br>&nbsp;&nbsp;&nbsp;&nbsp;queue_name：队列名，必填
     * <br>&nbsp;&nbsp;&nbsp;&nbsp;route_key：路由键，可填，[key1,key2，..]
     * <br>&nbsp;&nbsp;&nbsp;&nbsp;is_need_dq：是否开启死信队列，(true,false)，默认true
     * <br>]
     * @param string $strErrorMsg (&)init的错误信息
     * @return boolean true：成功 false：失败
     */
    protected function build($arrInitParam, &$strErrorMsg = '') {
        try {
            $this->arrInitParam = $arrInitParam;
            $blnIsRecreate = isset($arrInitParam['is_recreate']) ? $arrInitParam['is_recreate'] : false;
            if ($blnIsRecreate) {
                //交换器
                parent::declareExchange($arrInitParam['exchange_name'], $arrInitParam['exchange_type'], isset($arrInitParam['is_need_ae']) ? $arrInitParam['is_need_ae'] : false);
                //队列
                if (isset($arrInitParam['queue_list']) && count($arrInitParam['queue_list']) > 0) {
                    foreach ($arrInitParam['queue_list'] as $arrQueue) {
                        if (!empty($arrQueue['queue_name'])) {
                            parent::declareQueue($arrQueue['queue_name'], isset($arrQueue['is_need_dq']) ? $arrQueue['is_need_dq'] : true);
                            //绑定
                            parent::bindExchangeQueue($arrInitParam['exchange_name'], $arrQueue['queue_name'], isset($arrQueue['route_key']) ? $arrQueue['route_key'] : []);
                        }
                    }
                }
            }
            //开启信道确认模式
            $this->getChannel()->confirm_select();
            //设置信道回调方法
            $this->getChannel()->set_ack_handler(function(AMQPMessage $objMessage) {
                $this->ackHandler($objMessage);
            });
            $this->getChannel()->set_nack_handler(function(AMQPMessage $objMessage) {
                $this->nackHandler($objMessage);
            });
            //返回true
            return true;
        } catch (\Exception $e) {
            //设置错误信息
            $strErrorMsg = $e->getMessage();
            //错误日志记录
            echo $strErrorMsg . PHP_EOL;
            //返回false
            return false;
        }
    }

    /**
     * 发送成功调用
     * @param AMQPMessage $objMessage
     */
    private function ackHandler(AMQPMessage $objMessage) {
        $this->intTotalMessage--;
    }

    /**
     * 发送失败调用
     * @param AMQPMessage $objMessage
     */
    private function nackHandler(AMQPMessage $objMessage) {
        $this->arrFailMessage[] = $objMessage->body;
    }

    /**
     * 消息发送
     * @param array $arrMessage 需要发送的消息，格式[['message'=>'消息实体','route_key'=>'路由键(可为空)']]
     * @param array $arrFailMessage (&)发送失败的消息
     * @param int $intFailCount (&)发送失败的消息个数
     * @param string $strErrorMsg (&)send的错误信息
     * @return boolean true：成功 false：失败
     */
    protected function send($arrMessage, &$arrFailMessage = [], &$intFailCount = 0, &$strErrorMsg = '') {
        $intFailTryCount = $this->intTryCount;
        while ($intFailTryCount >= 0) {
            try {
                $this->intTotalMessage = $intFailCount = count($arrMessage);
                $this->arrFailMessage = [];
                //批量发送
                foreach ($arrMessage as $arrMsg) {
                    $objMessage = new AMQPMessage($arrMsg['message'], ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
                    $this->getChannel()->batch_basic_publish($objMessage, $this->arrInitParam['exchange_name'], isset($arrMsg['route_key']) ? $arrMsg['route_key'] : '');
                }
                $this->getChannel()->publish_batch();
                //等待
                $this->getChannel()->wait_for_pending_acks();
                //返回true or false
                $intFailCount = $this->intTotalMessage;
                return $this->intTotalMessage == 0 ? true : false;
            } catch (\Exception $e) {
                if ($this->intTotalMessage == $intFailCount && $intFailTryCount > 0) {
                    //如果一条消息都没发送成功，尝试重做
                    $intFailTryCount--;
                    $this->reset();
                    if (!$this->build($this->arrInitParam, $strErrorMsg)) {
                        return false;
                    }
                } else {
                    //设置错误信息
                    $strErrorMsg = $e->getMessage();
                    //错误日志记录
                    echo $strErrorMsg . PHP_EOL;
                    //返回false
                    return false;
                }
            }
        }
    }

}
