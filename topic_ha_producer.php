<?php

//用于自动加载类中的Lib目录定位
define('LIB_PATH', __DIR__ . '/Lib/');

//自动加载类
require_once __DIR__ . '/ComModule/AutoLoader/Loader.php';

use Lib\RabbitMQ\RabbitProducerBase;

class TopicHaProducer extends RabbitProducerBase {

    /**
     * 初始化连接
     * todo:rabbit初始化参数，可写在配置文件里，根据业务进行获取
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
                    'route_key' => ['aa.bb']
                ], [
                    'queue_name' => 'q_xiaopangzi_aa.cc',
                    'route_key' => ['aa.cc']
                ], [
                    'queue_name' => 'q_xiaopangzi_aa.dd',
                    'route_key' => ['aa.dd'],
                    'is_need_dq' => false
                ]
            ]
        ];
        //调用父类构建方法
        return parent::build($arrInitParam);
    }

    /**
     * 发送消息
     */
    public function sendMessage($arrMessage) {
        //消息加工
        //调用父类发送方法
        $arrFailMessage = [];
        $intFailCount = 0;
        $blnFlag = parent::send($arrMessage, $arrFailMessage, $intFailCount);
        if (!$blnFlag) {
            //失败处理
            echo json_encode($arrFailMessage) . PHP_EOL;
            echo $intFailCount . PHP_EOL;
        }
    }

}

var_dump(microtime());
$objTopicHaProducer = new TopicHaProducer();
if ($objTopicHaProducer->init()) {
    //sleep(20);
    $arrMessage = [];
    $arrTmp = ['aa.bb', 'aa.cc', 'aa.dd'];
    for ($i = 1; $i <= 1; $i++) {
        //$arrMessage[] = ['message' => 'hello world!' . time(), 'route_key' => $arrTmp[rand(0, 2)]];
        $arrMessage[] = ['message' => rand(1, 1), 'route_key' => 'aa.bb'];
    }
    $objTopicHaProducer->sendMessage($arrMessage);
}
var_dump(microtime());

