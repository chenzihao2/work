<?php

/**
 * 专家信息每日结算
 */

require(__DIR__ . "/cron.php");

use QK\HaoLiao\Model\ExpertModel;

ini_set('display_errors', 'On');
error_reporting(E_ALL | E_STRICT);

$expertModel = new ExpertModel();

// 统计全部专家命中率
$expertModel->dayExpertBetRecord();


//临时统计 9，11场命中率
//$expertModel->dayExpertBetRecordV2();


echo "命中率及最大命中率统计结束\n";
