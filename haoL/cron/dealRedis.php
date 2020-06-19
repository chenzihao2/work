<?php

/**
 * 清除 redis 数据
 */

require(__DIR__ . "/cron.php");

ini_set('display_errors', 'On');
error_reporting(E_ALL | E_STRICT);

if ($argc < 3) {
    die("请输入要执行的操作和对应的模块\n");
}

// 例子
// php 文件名 操作名(-l 显示, -d 删除) 库名 字段名模糊搜索
// php dealRedis.php -l expert expert:

use QK\HaoLiao\Model\RedisModel;

$option = $argv[1];
$db = $argv[2];
$key = isset($argv[3]) ? $argv[3] : '';

$redisModel = new RedisModel($db);

switch ($option) {
    case '-l':
        $keys = $redisModel->redisKeys($key . '*');
        print_r($keys);
        break;
    case '-d':
        $keys = $redisModel->redisKeys($key . '*');
        foreach ($keys as $item) {
            $redisModel->redisDel($item);
            echo $item . "已删除\n";
        }
        break;
    default:
        die("请输入要执行的操作\n");

}
