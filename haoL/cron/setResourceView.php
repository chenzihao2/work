<?php
/**
 * 料浏览量处理
 * User: YangChao
 * Date: 2018/11/27
 */

require(__DIR__ . "/cron.php");

use QK\HaoLiao\Model\ResourceModel;

$resourceModel = new ResourceModel();
$resourceModel->setResourceViewToMysql();