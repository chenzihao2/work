<?php

/**
 * 料浏览数量造假
 */

require(__DIR__ . "/cron.php");

ini_set('display_errors', 'On');
error_reporting(E_WARNING | E_STRICT);

use QK\HaoLiao\Model\ResourceModel;
use QK\HaoLiao\Model\RedisModel;
$resourceModel = new ResourceModel();

$nowtime = time();

const EXCE_TIME = 30 * 60;


$redisModule = new RedisModel('resource');


// 获取料的扩展信息
$condition = '1 = 1';
// $condition .= ' AND hl_resource.release_time > ' . $soldNumRefreshLastTime;
 $condition .= ' AND resource_status = 1';
// $condition .= ' AND ((schedule_time > ' . (string)($nowtime + EXCE_TIME) . ') OR (schedule_time = 0 and is_schedule_over = 0))';
$condition .= ' AND (schedule_time = 0 and is_schedule_over = 0)';

$order['join'] = [
    ['hl_resource_extra', 'hl_resource.resource_id = hl_resource_extra.resource_id']
];

$lists = $resourceModel->lists($condition, 'hl_resource.resource_id, hl_resource.expert_id, release_time, is_groupbuy, schedule_time, cron_sold_num', '', $order, 0, 100);


$resource_ids=array_column($lists,'resource_id');


//计算随机
$count=(int) round(count($resource_ids)*0.6);
$resource_index=array_rand($resource_ids,$count);
if($count<2){
	$resourceIdArr[]=$resource_ids[$resource_index];
}else{
	
	foreach($resource_index as $v){
		$resourceIdArr[]=$resource_ids[$v];
	}
}



 //0,5 05-24 * * *  表示在每天06 : 00至23 : 00之间每隔5分钟执行


foreach ($resourceIdArr as $item) {
		$redisKey = RESOURCE_VIEW;
		$num=getIncNum();//获取数量
        $redisModule->redisHincrby($redisKey, $item, $num);
		
}





// 获取随机数
function getIncNum() {
    return rand(0, 2);
}



