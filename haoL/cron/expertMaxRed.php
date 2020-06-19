<?php
/**
 * 近几中几
 * User: zyj
 * Date: 2019/12/03
 * Time: 下午1:23
 */

require(__DIR__ . "/cron.php");

use QK\HaoLiao\Model\ResourceModel;
use QK\HaoLiao\Model\RedisModel;
use QK\HaoLiao\Model\ExpertModel;

//获取专家
$resourceModel = new ResourceModel();
$expertModel = new ExpertModel();

$expertModel = new ExpertModel();
$expertList=$expertModel->expertList();

foreach($expertList as $v){
    $expertModel->getExpertMaximum($v['expert_id'],$v['platform']);
}
echo '近几中几 计算完毕';

?>