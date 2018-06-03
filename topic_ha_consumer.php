<?php

/*
 * 说明：
 * 1.此文件需要获取所有安卓系统信息
 */

//用于自动加载类中的Lib目录定位
define('LIB_PATH', __DIR__ . '/Lib/');

//自动加载类
require_once __DIR__ . '/ComModule/AutoLoader/Loader.php';

use Lib\RabbitMQ\RabbitConsumerBase;

class TopicHaConsumer extends RabbitConsumerBase {

    /**
     * 初始化连接
     */
    public function init() {
        //设置初始化参数
        $arrInitParam = [
            'exchange_name' => 'e_xiaopangzi',
            'exchange_type' => 'topic',
            'is_need_ae' => true,
            'queue_list' => [
                [
                    'queue_name' => 'q_xiaopangzi_aa.bb',
                    'route_key' => ['aa.bb'],
                    'is_requeue'=>false
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
        //return true;
        usleep(500 * 1000);
        if ($strMessage % 2 == 0) {
            echo $strMessage . ':true' . PHP_EOL;
            return true;
        } else {
            echo $strMessage . ':false' . PHP_EOL;
            return false;
        }
    }

}

$objTopicHaConsumer = new TopicHaConsumer();
if ($objTopicHaConsumer->init()) {
    $objTopicHaConsumer->run();
}




