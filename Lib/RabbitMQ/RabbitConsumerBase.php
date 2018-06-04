<?php

namespace Lib\RabbitMQ;

use Lib\PhpAmqpLib\Message\AMQPMessage;

/**
 * Rabbit消费者基类
 */
class RabbitConsumerBase extends RabbitBase {

    /**
     * 初始化参数
     */
    private $arrInitParam = [];

    /**
     * 获取子类类型
     */
    protected function getType() {
        return 'consumer';
    }

    /**
     * 构建客户端
     * @param array $arrInitParam 配置信息
     * <br><b>注意：如果在生产与消费端都配置交换器与队列，确保配置信息一致</b>
     * <br>exchange_name：交换器名，必要
     * <br>exchange_type：交换器类别，(direct,topic,fanout)，必要
     * <br>is_need_ae：是否开启备用交换器，(true,false)，默认false
     * <br>queue_list：需要绑定的队列，必填，最多只能一个，[
     * <br>&nbsp;&nbsp;&nbsp;&nbsp;queue_name：队列名，必填
     * <br>&nbsp;&nbsp;&nbsp;&nbsp;route_key：路由键，可填，[key1,key2，..]
     * <br>&nbsp;&nbsp;&nbsp;&nbsp;is_need_dq：是否开启死信队列，(true,false)，默认true
     * <br>&nbsp;&nbsp;&nbsp;&nbsp;is_requeue：消息消费失败是否重进队列(需要确保消息之后能消费掉，否则会一直占用资源)，(true,false)，默认false
     * <br>]
     * @param string $strErrorMsg (&)init的错误信息
     * @return boolean true：成功 false：失败
     */
    protected function build($arrInitParam) {
        try {
            $this->arrInitParam = $arrInitParam;
            //交换器
            parent::declareExchange($arrInitParam['exchange_name'], $arrInitParam['exchange_type'], isset($arrInitParam['is_need_ae']) ? $arrInitParam['is_need_ae'] : false);
            //队列校验
            if (count($arrInitParam['queue_list']) != 1) {
                throw new \Exception('队列配置，必须有且只有一个');
            }
            $arrQueue = $arrInitParam['queue_list'][0];
            //队列
            parent::declareQueue($arrQueue['queue_name'], isset($arrQueue['is_need_dq']) ? $arrQueue['is_need_dq'] : true);
            //绑定
            parent::bindExchangeQueue($arrInitParam['exchange_name'], $arrQueue['queue_name'], isset($arrQueue['route_key']) ? $arrQueue['route_key'] : []);
            //每次只接受一条信息
            $this->getChannel()->basic_qos(null, 1, null);
            $this->getChannel()->basic_consume($arrQueue['queue_name'], '', false, false, false, false, function(AMQPMessage $objMessage) {
                $this->dealMessage($objMessage);
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
     * 从服务器接收消息，进行业务处理
     * 必须要返回true or false
     * @return boolean true：处理成功 false：处理失败
     */
    protected function receiveMessage($strMessage) {
        return false;
    }

    /**
     * 信息处理
     */
    private function dealMessage(AMQPMessage $objMessage) {
        //消息消费失败是否重进队列
        $blnIsRequeue = isset($this->arrInitParam['queue_list'][0]['is_requeue']) ? $this->arrInitParam['queue_list'][0]['is_requeue'] : false;
        //业务确认是否成功
        $blnAck = $this->receiveMessage($objMessage->body);
        if ($blnAck) {
            $objMessage->delivery_info['channel']->basic_ack($objMessage->delivery_info['delivery_tag']);
        } else {
            //$objMessage->delivery_info['channel']->basic_nack($objMessage->delivery_info['delivery_tag'], false, $blnReQueue);
            $objMessage->delivery_info['channel']->basic_reject($objMessage->delivery_info['delivery_tag'], $blnIsRequeue);
        }
    }

    /**
     * 开始运行
     */
    public function run() {
        while (1) {
            try {
                while (1) {
                    $this->getChannel()->wait();
                }
            } catch (\Exception $e) {
                //日志记录
                //重建
                $this->reset();
                if (!$this->build($this->arrInitParam)) {
                    //日志记录
                    break;
                }
            }
        }
    }

}
