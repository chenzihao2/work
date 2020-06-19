<?php
/**
 * 订阅金额结算(暂停)
 * User: YangChao
 * Date: 2018/11/21
 */

require(__DIR__ . "/cron.php");

use QK\WSF\Settings\AppSetting;
use QK\HaoLiao\DAL\DALOrder;
use QK\HaoLiao\Model\ExpertRateModel;
use QK\HaoLiao\DAL\DALUserExpertSubscribeSettle;

$_appSetting = AppSetting::newInstance(AppRoot);

// 获取昨天所有订阅订单列表
$startTime = strtotime(date('Y-m-d', strtotime("-1 day")));
$endTime = strtotime(date("Y-m-d"));
$dalOrder = new DALOrder($_appSetting);
$subscribeOrderList = $dalOrder->getOrderListByTime($startTime, $endTime, 1);

if(!empty($subscribeOrderList)){
    $expertRateModel = new ExpertRateModel();
    $dalUserExpertSubscribeSettle = new DALUserExpertSubscribeSettle($_appSetting);
    foreach($subscribeOrderList as $key => $val){
        $orderNum = $val['order_num'];
        $userId = $val['user_id'];
        // 专家ID
        $expertId = $val['expert_id'];
        $expertRateInfo = $expertRateModel->getExpertRate($expertId);
        $expertRate = $expertRateInfo['rate'];
        // 用户实付金额
        $payAmount = $val['pay_amount'];

        // 计算分成金额
        $separateAmountTotal = bcmul($payAmount,$expertRate);

        // 结算金额
        $settleAmountTotal = $payAmount - $separateAmountTotal;

        // 计算用户前29天每天订阅分成
        // 计算用户前29天每天结算金额数
        for($i = 0; $i < 29; $i++){
            $settleData = [];
            $settleData['order_num'] = $orderNum;
            $settleData['user_id'] = $userId;
            $settleData['user_id'] = $userId;
            $settleData['expert_id'] = $expertId;
            $settleData['pay_amount'] = $payAmount;
            // 计算每日分成金额
            $settleData['separate_amount'] = $separateAmount = bcdiv($separateAmountTotal, 30);
            // 计算每日结算金额
            $settleData['settle_amount'] = $settleAmount =  bcdiv($settleAmountTotal, 30);
            $settleData['create_time'] = time();
            $settleData['settle_status'] = 0;
            $settleData['settle_time'] = date("Y-m-d", strtotime("+ $i days"));
            // 入库
            $dalUserExpertSubscribeSettle->setSettle($settleData);
            // 29天分成总额
            $separateAmount29 += $separateAmount;
            // 29天结算总额
            $settleAmount29 += $settleAmount;
        }

        // 计算用户第30天结算金额（结算总额-29*每天金额）
        $settleData = [];
        $settleData['order_num'] = $orderNum;
        $settleData['user_id'] = $userId;
        $settleData['expert_id'] = $expertId;
        $settleData['pay_amount'] = $payAmount;
        // 计算第30日分成金额
        $settleData['separate_amount'] = $separateAmountTotal - $separateAmount29;
        // 计算第30日结算金额
        $settleData['settle_amount'] = $settleAmountTotal - $settleAmount29;
        $settleData['create_time'] = time();
        $settleData['settle_status'] = 0;
        $settleData['settle_time'] = date("Y-m-d", strtotime("+ 29 days"));
        // 入库
        $dalUserExpertSubscribeSettle->setSettle($settleData);
    }
}
