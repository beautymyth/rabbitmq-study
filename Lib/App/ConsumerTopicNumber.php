<?php

namespace Lib\App;

use Lib\RabbitMQ\RabbitConsumerBase;

class ConsumerTopicNumber extends RabbitConsumerBase {

    /**
     * 初始化连接
     */
    public function init($intWorkId) {
        $this->intWorkId = $intWorkId;
        //设置初始化参数
        $arrInitParam = [
            'exchange_name' => 'e_xiaopangzi',
            'exchange_type' => 'topic',
            'is_need_ae' => true,
            'queue_list' => [
                [
                    'queue_name' => 'q_xiaopangzi_aa.bb',
                    'route_key' => ['aa.bb'],
                    'is_requeue' => false
                ]
            ]
        ];
        //调用父类构建方法
        return parent::build($arrInitParam);
    }

    /**
     * 从服务器接收消息，进行业务处理
     * 必须要返回true or false
     * @return boolean true：处理成功 false：处理失败
     */
    protected function receiveMessage($strMessage) {
        $strFileName = getenv('InterviewRootPath') . "/logs/rabbit_consume.log";
        //return true;
        //usleep(10 * 1000);
        sleep(60);
        if ($strMessage % 2 == 0) {
            //echo $strMessage . ':true' . PHP_EOL;
            file_put_contents("{$strFileName}", $this->intWorkId . '[x] Received ' . $strMessage . '_' . microtime() . "\r\n", FILE_APPEND | LOCK_EX);
            return true;
        } else {
            //echo $strMessage . ':false' . PHP_EOL;
            file_put_contents("{$strFileName}", $this->intWorkId . '[x] Received ' . $strMessage . '_' . microtime() . "\r\n", FILE_APPEND | LOCK_EX);
            return false;
        }
    }

}
