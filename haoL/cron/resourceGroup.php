<?php
/**
 * 合买料失败轮询
 */
require(__DIR__ . "/cron.php");

ini_set('display_errors', 'On');
error_reporting(E_ALL | E_STRICT);

use QK\HaoLiao\Model\ResourceModel;

$resourceModel = new ResourceModel();
$nowtime = time();
$other['join'] = [['hl_resource', 'hl_resource.resource_id = hl_resource_group.resource_id']];
$groupList = $resourceModel->groupList('resource_status = 1 AND status = 0 AND limit_time < ' . $nowtime, 'hl_resource_group.*', '', $other);

foreach ($groupList as $item) {
    try {
        $r = $resourceModel->setRefundResource($item['resource_id'], 3);
        $resourceModel->updateResourceGroup($item['resource_id'], 'status = 2, over_time = limit_time');
    } catch (Exception $e) {
        error_log('cron ' . __FILE__ . ': ' . $item['resource_id'] . '处理失败');
    }
}

