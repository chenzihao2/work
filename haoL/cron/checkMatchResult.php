<?php

require(__DIR__ . "/cron.php");

use QK\HaoLiao\Model\ResourceModel;
use QK\HaoLiao\Model\MatchModel;
use QK\HaoLiao\Model\RedisKeyManageModel;
use QK\HaoLiao\Model\ExpertExtraModel;
use QK\HaoLiao\Model\ExpertModel;
use QK\HaoLiao\Model\OrderModel;
use QK\HaoLiao\Model\StatModel;
use QK\HaoLiao\Model\SoccerModel;
use QK\HaoLiao\Model\BasketballModel;
use QK\HaoLiao\Model\ExpertMoneyChangeModel;
use QK\HaoLiao\Common\CommonHandler;
use QK\WSF\Settings\AppSetting;

$st = date('Y-m-d 00:00:00', strtotime('-1 day'));
$et = date('Y-m-d H:i:s');
$matchModel = new MatchModel();
$resourceModel = new ResourceModel();
$common = new CommonHandler();
$appSetting = AppSetting::newInstance(APP_ROOT);
$arr=[1,2];// 1足球，2篮球
foreach($arr as $v){

    //$matches = $matchModel->getMatchWithResource($st, $et, 1);
    $matches = $matchModel->getMatchWithResource($st, $et, $v);

    foreach($matches as $match) {
        if ($match['bet_status'] == 0) {
            $resourceId = $match['resource_id'];

            $resourceInfo = $resourceModel->getResourceInfo($resourceId);

            if($match['type']==1){
                $res = $matchModel->checkMatchResult($match['schedule_id'], $match['h'], $match['lottery_type'], $match['type']);
            }
            if($match['type']==2){
                $res = $matchModel->checkBasketMatchResult($match['schedule_id'], $match['h'], $match['lottery_type'], $match['type'],$match['d']);
            }


            //1,主胜，2,平，3,客胜，4,主半胜，5,客半胜

            $result_map = array(
                1 => 'w',
                2 => 'd',
                3 => 'l',
                4 => 'w',
                5 => 'l'
            );

            $recommend_list = explode(',', $match['recommend']);
            $main_recommend = $recommend_list[0];
            $extra_recommend = count($recommend_list) == 2 ? $recommend_list[1] : '';
            $betStatus = 0;

            if ($result_map[$res] == $main_recommend) {
                $betStatus = 1;
                if ($res == 4 || $res == 5) {
                    $betStatus = 5;    //主推半红
                }
            } else if ($result_map[$res] == $extra_recommend) {
                $betStatus = 4;
                if ($res == 4 || $res == 5) {
                    $betStatus = 6;    //副推半红
                }
            } else {
                if ($res == 2 && $main_recommend != $result_map[$res] && $extra_recommend != $result_map[$res]) {
                    $betStatus = 2;
                } else {
                    $betStatus = 3;
                    if ($res == 4 || $res == 5) {
                        $betStatus = 7;    //半黑
                    }
                }
            }

            if ($match['lottery_type'] == 1 && $betStatus == 2) {
                $betStatus = 3;
            }

            $upCondition = ['id' => $match['id']];
            $matchModel->updateResoureMatch(['bet_status' => $betStatus, 'lottery_result' => $res], $upCondition);

            //所有比赛都判定了，需要取消置顶，不中退款
            $resourceSchedules = $resourceModel->getResourceSchedules(['resource_id' => $resourceId, 'bet_status' => 0]);
            if (empty($resourceSchedules)) {
                //如果它的所有比赛都判定了，取消置顶
                $resourceUpdateInfo = array('wx_placement' => 0, 'bd_placement' => 0, 'is_over_bet' => 1); //is_over_bet为1表示料已判完
                $resourceModel->updateResource($resourceId, $resourceUpdateInfo, false);


                //========只有选一场比赛的时候才会有不中退款的情况=================
                //判定红黑之后判定是否退款，做退款处理
                //只有一场比赛会有退款操作
                if (($betStatus == 3 || $betStatus == 2 || $betStatus == 7) && $resourceInfo['resource_type'] == 2) {
                    //黑单/红单的金额以及退款的相关处理
                    refundHandler($resourceId);
                }
            }

            //红黑数据 写入统计表
            $statModel = new StatModel();
            $statTime = date("Y-m-d", $resourceInfo['stat_time']);
            $statModel->betRecordStat($resourceInfo['expert_id'], $statTime, $match['type'], $betStatus);

            //================当前方案对应专家的统计方案START========================
            //连红数据处理,如果为红（方案全红才算红），连红+1，如为走和黑，则归0
            //最大连红数取连红里面的最大值
            //$expertExtraModel = new ExpertExtraModel();
            //$expertExtraInfo = $expertExtraModel->getExpertExtraInfo($resourceInfo['expert_id']);

            //$resourceScheduleList = $resourceModel->getResourceSchedules(['resource_id' => $resourceId]);
            //$all_bet_status = $resourceModel->getBetStatus($resourceScheduleList);
            //if($all_bet_status == 1) {

            //    $updateInfo['red_num'] = $expertExtraInfo['red_num'] + 1;

            //    if($expertExtraInfo['max_red_num'] < $updateInfo['red_num']){
            //        $updateInfo['max_red_num'] = $updateInfo['red_num'];
            //    }
            //} else {
            //    $updateInfo['red_num'] = 0;
            //}


            //当前专家的盈利率计算
            //$odds = ($betStatus == 4 || $betStatus == 6) ? $match[$extra_recommend] : $match[$main_recommend];
            //$soccerModel = new SoccerModel();
            //$basketballModel = new BasketballModel();
            //if ($match['lottery_type'] == 2) {
            //    if($v==1){
            //        $lotteryInfo = $soccerModel->findLotteryById($schedule['lottery_id']);
            //    }else{
            //        $lotteryInfo = $basketballModel->findLotteryById($schedule['lottery_id']);
            //    }
            //    dump($lotteryInfo);
            //    $odds = ($betStatus == 4 || $betStatus == 6) ? $lotteryInfo[$extra_recommend] : $lotteryInfo[$main_recommend];
            //}




            //盈利率
            //$profitInfo = $expertExtraModel->countProfitRate($expertExtraInfo, $odds, $betStatus);
            //if ($profitInfo) {
            //    $updateInfo['profit_rate'] = $profitInfo['profitRate'];
            //    //$updateInfo['profit_all'] = $profitInfo['profitAll'];
            //    $updateInfo['profit_resource_num'] = $profitInfo['profitResourceNum'];
            //}


            //$expertExtraModel->updateExtra($resourceInfo['expert_id'], $updateInfo);

            ////近十场战绩删除/重置
            $redisManageModel = new RedisKeyManageModel('betRecord');
            $redisManageModel->delExpertStat($resourceInfo['expert_id']);

            ////新版本命中率（近N场次的命中率）(根据专家的platform和料的platform计算结果)
            //$expertModel = new ExpertModel();
            //$platform = 0;
            //if ($resourceInfo['wx_display'] && $resourceInfo['bd_display']) {
            //    $platform = 0;
            //} else if ($resourceInfo['bd_display']) {
            //    $platform = 1;
            //} else if ($resourceInfo['wx_display']) {
            //    $platform = 2;
            //}
            //$expertModel->calBetRecord($resourceInfo['expert_id'], $platform);
            //===============专家的统计方案END===============================
                            //更新专家信息
            $prefix_url = $appSetting->getConstantSetting("UpdateExpertUrl");
            $url = $prefix_url . $resourceInfo['expert_id'];
            $common->httpGet($url, []);
        }
    }

}

function refundHandler($resourceId) {
    $resourceModel = new ResourceModel();
    $resourceModel->setRefundResource($resourceId);

    //判定退款之后修改专家金额
    if ($resourceInfo['resource_type'] == 2) {
        //获取料金额价格，从冻结金额中减去。
        $orderModel = new OrderModel();
        $resourceAmount = $orderModel->getResourceAmount($resourceId, $expertId);
        if($resourceAmount){
            if($betStatus==3 || $betStatus == 2){ //走单，黑单
                //获取料金额价格，从冻结金额中减去。
                $expertExtraIncOrDec['freezing'] = "-" . $resourceAmount;
            }
            if($betStatus==1){    //红单
                //将金额从冻结中转入余额和收入
                $expertExtraIncOrDec['freezing'] = "-" . $resourceAmount;
                $expertExtraIncOrDec['income'] = "+" . $resourceAmount;
                $expertExtraIncOrDec['balance'] = "+" . $resourceAmount;
                //增加金额变更记录
                $expertMoneyChangeModel = new ExpertMoneyChangeModel();
                $expertMoneyChangeModel->setMoneyChange(0, $expertId, 1, 2, $resourceAmount);
            }
        }
        $expertExtraModel = new ExpertExtraModel();
        $expertExtraModel->setExpertExtraIncOrDec($expertId, $expertExtraIncOrDec);
    }
}
