<?php
/**
 * 从Redis获取登录日志填充至Mysql
 * User: YangChao
 * Date: 2018/11/21
 */

require(__DIR__ . "/../cron.php");

use QK\HaoLiao\Model\LogUserLoginModel;

$logUserLoginModel = new LogUserLoginModel();

$logUserLoginModel->setLoginLog();
