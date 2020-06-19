<?php
/**
 * User: WangHui
 * Date: 2018/5/21
 * Time: 17:52
 */ 


declare(strict_types=1);
require __DIR__ . '/constant.php';
require __DIR__ . '/rediskey.php';
require __DIR__ . '/vendor/autoload.php';

use QK\WSF\Settings\AppSetting;
use QK\HaoLiao\SysTemInit;



$webApp = new SysTemInit(AppSetting::newInstance(APP_ROOT));
$webApp->run();