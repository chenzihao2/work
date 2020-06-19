<?php
/**
 * 完赛状态修改
 * User: WangHui
 * Date: 2018/11/21
 * Time: 下午4:23
 */

require(__DIR__ . "/cron.php");

use QK\HaoLiao\Model\ResourceModel;
use QK\HaoLiao\Model\RedisKeyManageModel;
use QK\HaoLiao\Model\ExpertModel;

//获取未完赛的料
$resourceModel = new ResourceModel();
$expertModel = new ExpertModel();

$start = 0;
$resourceId = $resourceModel->getNotOverResource($start);

$time = time() - 3 * 3600;
$soccer_status = [1, 2, 3, 5, 7, 8, 9];
$basket_status = [1, 2, 3, 4, 5, 6, 7, 8, 10];

while($resourceId){
    //获取赛事信息
    $scheduleList = $resourceModel->getResourceScheduleList($resourceId);
    dump($resourceId);
    dump($scheduleList);

    $over = 1;
	if($scheduleList){
		foreach ($scheduleList as $schedule){
			//足球
			if($schedule['match_type']==1 && !in_array($schedule['result'],[4,6,10,12])){
                if (in_array($schedule['result'], $soccer_status)) {
                    $over = 3;
                } else {
				    $over = false;
                }
				 break;
			}else if($schedule['match_type']==2 && !in_array($schedule['result'],[9,11,13])){
			//篮球
                if (in_array($schedule['result'], $basket_status)) {
                    $over = 3;
                } else {
                    $over = false;
                }
				break;
			}else if(!$schedule['match_type']){
				if($schedule['schedule_time'] >= $time){
				   $over = false;
				   break;
				}
			}


		}
	}else{
		$over = 2;
	}
	
    if($over){
        //完赛
        $update['is_schedule_over'] = $over;
        if ($over == 3) { 
            //$update['is_schedule_over'] = 3;
            $resourceModel->updateResource($resourceId,$update);
            $start++;
            $resourceId = $resourceModel->getNotOverResource($start);
            continue;
        }
        $update['schedule_over_date'] = strtotime(date("Y-m-d", time()));
        $update['wx_placement'] = 0;
        $update['bd_placement'] = 0;
        $update['sort'] = 0;
        $update['modify_time'] = time();
        $resourceModel->updateResource($resourceId,$update);
        $redisKeyManage = new RedisKeyManageModel('resource');
        $redisKeyManage->delResourceKey($resourceId);

        $resourceInfo = $resourceModel->getResourceInfo($resourceId);
        $expertId = $resourceInfo['expert_id'];
        $lastReleaseTime = $resourceModel->getLastResourceReleaseTime($expertId);
        $expertModel->updateExpert($expertId, ['push_resource_time' => $lastReleaseTime]);
    }
    $start++;
    $resourceId = $resourceModel->getNotOverResource($start);

}


