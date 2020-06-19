<?php

/**
 * 每周一执行
 */

require(__DIR__ . "/cron.php");

use QK\HaoLiao\Model\ExpertModel;

ini_set('display_errors', 'On');
error_reporting(E_ALL | E_STRICT);

$expertModel = new ExpertModel();

// 统计全部专家盈利率
$expertModel->resetAllExpert();

echo "盈利率统计结束\n";
