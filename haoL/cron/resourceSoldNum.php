<?php

/**
 * 料出售数量
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
$soldNumRefreshLastTime = $redisModule->redisGet(RESOURCE_CRONSOLDNUM_LAST);
!$soldNumRefreshLastTime && $soldNumRefreshLastTime = 0;

/**
 * 遍历新添加的料，满足条件的加入执行数据队列中
 */
// 获取料的扩展信息
$condition = '1 = 1';
$condition .= ' AND hl_resource.release_time > ' . $soldNumRefreshLastTime;
$condition .= ' AND resource_status = 1';
$condition .= ' AND ((schedule_time > ' . (string)($nowtime + EXCE_TIME) . ') OR (schedule_time = 0 and is_schedule_over = 0))';

$order['join'] = [
    ['hl_resource_extra', 'hl_resource.resource_id = hl_resource_extra.resource_id']
];

$lists = $resourceModel->lists($condition, 'hl_resource.resource_id, hl_resource.expert_id, release_time, is_groupbuy, schedule_time, cron_sold_num', '', $order, 0, 100);

foreach ($lists as &$item) {
    $info = $redisModule->redisGetHashList(RESOURCE_CRONSOLDNUM_DATA, $item['resource_id'], true);
    if (empty($info)) {
        $save['resource_id'] = $item['resource_id'];
        $save['is_groupbuy'] = $item['is_groupbuy'];
        $save['schedule_time'] = $item['schedule_time'];
        $save['times'] = getTargetTimes($item['schedule_time'], $item['expert_id']) - getOldTime($item['cron_sold_num']);
        $save['next'] = getNextTime($nowtime);
        $save['num'] = $item['cron_sold_num'];
        $save['max_num'] = getMaxNum($item['expert_id']);
        if ($save['is_groupbuy'] == 1) {
            $groupInfo = $resourceModel->getResourceGroupInfo($save['resource_id']);
            $save['group_num'] = $groupInfo['num'];
        }
        $redisModule->redisSetHashList(RESOURCE_CRONSOLDNUM_DATA, $save['resource_id'], $save);
    }
}

if (isset($item['release_time'])) {
    $soldNumRefreshLastTime = $item['release_time'];
}
// 将最后一个id存入redis
$redisModule->redisSet(RESOURCE_CRONSOLDNUM_LAST, $soldNumRefreshLastTime);

/**
 * 遍历执行队列
 */
$keys = $redisModule->redisHkeys(RESOURCE_CRONSOLDNUM_DATA);

if ($keys) {
    foreach ($keys as $key) {
        $info = $redisModule->redisGetHashList(RESOURCE_CRONSOLDNUM_DATA, $key, true);
        print_r($info);
        // 判断该信息是否执行
        $status = checkout($info, $nowtime);
        switch ($status) {
            case 3:  // 销毁
                $r = $redisModule->redisHdel(RESOURCE_CRONSOLDNUM_DATA, $key);
                break;
            case 1:  // 执行
                if ($info['is_groupbuy'] && isset($info['group_num'])) {
                    $resourceExtraInfo = $resourceModel->getResourceExtraInfo($info['resource_id']);
                    if (($resourceExtraInfo['sold_num'] + $resourceExtraInfo['cron_sold_num']) >= $info['group_num']) {
                        $resourceModel->groupSuccess($info['resource_id']);
                        break;
                    }
                }
                $inc = getIncNum();
		if (!empty($info['max_num'])) {
		  $afterNum = min($info['max_num'], $info['num'] + $inc);
		  $inc = $afterNum - $info['num'];
                  if ($inc < 0) break;
		}
                // 增加脚本出售数量
		$resourceInfo = $resourceModel->getResourceInfo($info['resource_id']);
		if (in_array($resourceInfo['expert_id'], [5881, 5853])) {
		  $resExtraInfo = $resourceModel->getResourceExtraInfo($info['resource_id']);
                  if (isset($resExtraInfo['cron_sold_num']) && $resExtraInfo['cron_sold_num'] + $inc < 2) {
                    $resourceModel->setResourceExtraIncOrDec($info['resource_id'], ['cron_sold_num' => '+' . (string)$inc]);
                  }
		} else {
		  $resourceModel->setResourceExtraIncOrDec($info['resource_id'], ['cron_sold_num' => '+' . (string)$inc]);
		}
                $info['num'] += $inc;
                $info['times']--; // 更新还需执行的次数
                $info['next'] = getNextTime($nowtime);  // 更新下次执行的时间
                $redisModule->redisSetHashList(RESOURCE_CRONSOLDNUM_DATA, $key, $info);
                echo $info["resource_id"] . "已执行\n";
                break;
            default:
                // status: 2 跳过
        }
    }
}

// 获取目标执行次数
function getTargetTimes($schedule_time) {
    if ($schedule_time == 0) {
        return rand(10, 20);
    } else {
        return rand(10, 20);
    }
}

// 获取下一次执行时间
function getNextTime($time) {
    return $time + (rand(0, 20) * 60);
}

// 获取新增购买人数
function getIncNum() {
    return rand(0, 1);
}

// 获取已经执行次数猜测
function getOldTime($num) {
    return $num * 2;
}

// 判断该条目状态
// 1 => 执行
// 2 => 跳过
// 3 => 销毁
function checkout($info, $nowtime) {
    // 如果时间已到开赛前三十分钟或者执行次数已全部完成，销毁redis队列中的该数据
    if (($info['schedule_time'] != 0 && $info['schedule_time'] < $nowtime + EXCE_TIME) || $info['times'] <= 0 || ($info['max_num'] != 0 && $info['num'] >= $info['max_num'])) {
        return 3;
    }
    // 如果下次执行时间小于等于当前时间，则执行
    if ($info['next'] <= $nowtime) {
        return 1;
    }
    return 2;
}

/**
 * 增长最大数量
 * @param $expert_id
 * @return int
 */
function getMaxNum($expert_id) {
    if (in_array($expert_id, [5853, 5881])) {
        return rand(1, 2);
    } else {
        return 0;
    }
}
