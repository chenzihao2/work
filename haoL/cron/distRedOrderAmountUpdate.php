<?php
/**
 * 分销商红单金额增加脚本
 * User: WangHui
 * Date: 2018/12/11
 * Time: 11:24 AM
 */
require(__DIR__ . "/cron.php");


use QK\HaoLiao\Model\OrderModel;
use QK\HaoLiao\Model\RedisModel;
use QK\HaoLiao\Model\DistExtraModel;
use QK\HaoLiao\Model\DistMoneyChangeModel;

$resourceRedisModel = new RedisModel('resource');
$orderModel = new OrderModel();

$resourceRedList = RESOURCE_RED_LIST;
//获取红单料id
while ($resourceId = $resourceRedisModel->redisRpop($resourceRedList)) {
    //获取所有红单订单
    $orderList = $orderModel->getResourceOrder($resourceId);
    foreach ($orderList as $order){
        $userId = $order['user_id'];
        $distId = $order['dist_id'];
        $payAmount = $order['pay_amount'];
        $payAmountDist = $orderModel->distAmount($distId,$payAmount);
        //增加分销商金额
        $distIncOrDec['income'] = "+" . $payAmountDist;
        $distIncOrDec['balance'] = "+" . $payAmountDist;
        $distExtraModel = new DistExtraModel();
        $distExtraModel->setDistExtraIncOrDec($distId, $distIncOrDec);
        //增加金额变更记录
        $distMoneyChangeModel = new DistMoneyChangeModel();
        $distMoneyChangeModel->setMoneyChange($userId, $distId, 1, 1, $payAmount);
    }
}