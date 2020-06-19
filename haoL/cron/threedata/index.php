<?php
require(__DIR__ . "/../cron.php");

ini_set('display_errors', 'On');
date_default_timezone_set('Asia/Shanghai');
error_reporting(E_ALL | E_STRICT);

use QK\HaoLiao\Common\CommonHandler;

$domain = 'http://datafeed2.tysondata.com:8080';


$t = date('YmdH');
$user_name = 'yingxun';
$code = '35b04ecda927a196d9d3834eab0a6844';
$secreteKey = 'bdd663b01868b9506746e6862b1c5bbf';
$auth_token = md5(md5($user_name) . $secreteKey . $code . $t);

$api = [
    'allHistoryData' => [
        'name' => '历史数据',
        'url' => '/datashare/allHistoryData',
        'method' => 'post',
    ],
    'compareOddsData' => [
        'name' => '百家赔率',
        'url' => '/datashare/compareOddsData',
        'method' => 'get',
    ],
    'matchLottery' => [
        'name' => '彩票数据',
        'url' => '/datashare/matchLottery',
        'method' => 'get',
        'params' => [
            'date' => '2019-01-29'
        ],
    ],
];

$filePath = __DIR__ . '/result/';
if (!file_exists($filePath)) {
    mkdir($filePath);
}

foreach ($api as $key => $one) {
    if (isset($one['params'])) {
        $params = $one['params'];
    } else {
        $params = [];
    }
    $paramsDesc = "接口:\n";
    $paramsDesc .= $one['name'] . ' ' . $one['url'] . "\n";
    $paramsDesc .= "参数:\n";
    if (!empty($params)) {
        foreach ($params as $k => $item) {
            if ($k == 'type') {
                continue;
            }
            $paramsDesc .= $k . ": " . $item . "\n";
        }
    }
    $params['t'] = $t;
    $params['code'] = $code;
    $params['auth_token'] = $auth_token;
    $paramsDesc .= "----------------------------------\n";
    $result = CommonHandler::newInstance()->httpGetRequest($domain . $one['url'], $params);
    $fileName = $one['name'] . '.json';
    $paramsDesc = '';
    file_put_contents($filePath . $fileName, $paramsDesc . $result);
    echo $key . "接口执行完成\n";
}
