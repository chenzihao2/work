<?php

require(__DIR__ . "/cron.php");

use QK\HaoLiao\Model\ResourceModel;
use QK\HaoLiao\Model\MatchModel;
use QK\HaoLiao\Model\RedisKeyManageModel;
use QK\HaoLiao\Model\ExpertExtraModel;
use QK\HaoLiao\Model\ExpertModel;
use QK\HaoLiao\Model\OrderModel;
use QK\HaoLiao\Model\StatModel;

$matchModel = new MatchModel();
$resourceModel = new ResourceModel();
$matches = $resourceModel->getResourceSchedules(['bet_status' => ['<>', 0]]);
foreach($matches as $match) {
    $resourceId = $match['resource_id'];
    $resourceInfo = $resourceModel->getResourceInfo($resourceId);
    
    $resourceSchedules = $resourceModel->getResourceSchedules(['resource_id' => $resourceId, 'bet_status' => 0]);
    if (empty($resourceSchedules)) {
      $resourceUpdateInfo = array('is_over_bet' => 1); //is_over_bet为1表示料已判完
      $resourceModel->updateResource($resourceId, $resourceUpdateInfo, false);
    }
    //近十场战绩删除/重置
    $redisManageModel = new RedisKeyManageModel('betRecord');
    $redisManageModel->delExpertStat($resourceInfo['expert_id']);

}
