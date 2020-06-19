<?php

/**
 * 更新盈利率
 */

require(__DIR__ . "/cron.php");

ini_set('display_errors', 'On');
error_reporting(E_ALL | E_STRICT);

use QK\HaoLiao\Model\ExpertModel;
use QK\HaoLiao\Model\ExpertExtraModel;
use QK\HaoLiao\Model\ResourceModel;
use QK\HaoLiao\Model\BetRecordModel;
use QK\HaoLiao\Model\RedisModel;

$expertModel = new ExpertModel();
$expertExtraModel = new ExpertExtraModel();
$resourceModel = new ResourceModel();
$betRecordModel = new BetRecordModel();
$redisModel = new RedisModel();

$expertList = $expertModel->expertList();

var_dump($expertList);
echo "-----------------------------------------";

foreach ($expertList as $expert) {
    // 盈利率
    $profitAll = 0;
    $profitRate = 0;
    $resourceNum = 0;
    $resourceList = $resourceModel->lists('expert_id = ' . $expert['expert_id'] . ' AND resource_status = 1 AND bet_status <> 0', '', '', ['join' => [['hl_resource_extra', 'hl_resource_extra.resource_id = hl_resource.resource_id']]]);
    var_dump($expert, $resourceList);
    foreach ($resourceList as $source) {
        if (intval($source['odds']) >= 1) {
            switch ($source['bet_status']) {
                case 1:  // 红单
                    $profit = floatval($source['odds']) - 1;  // 收益 - 本金
                    break;
                case 2:  // 走单
                    $profit = 0;
                    break;
                case 3:  // 黑单
                    $profit = -1;
            }
            $profitAll += $profit;
            $resourceNum++;
        }
    }
    if ($resourceNum > 0) {
        $profitAll = $profitAll * 100;
        $profitRate = ceil($profitAll / $resourceNum);
    }
    $expertExtraModel->updateExtra($expert['expert_id'], ['profit_all' => $profitAll, 'profit_rate' => $profitRate, 'profit_resource_num' => $resourceNum]);
}
echo "盈利率计算完毕\n";
