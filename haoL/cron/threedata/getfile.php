<?php
require(__DIR__ . "/../cron.php");

ini_set('display_errors', 'On');
date_default_timezone_set('Asia/Shanghai');
error_reporting(E_ALL | E_STRICT);

$data = file_get_contents('/Applications/MAMP/htdocs/haoliao/backend-api/cron/threedata/result/百家赔率.json');
$data = json_decode($data, true);

$filePath = __DIR__ . '/result/';
if (!file_exists($filePath)) {
    mkdir($filePath);
}

// 哥伦甲, 英冠, 亚冠, 乌克U21, NBA, 俄篮超, 立陶甲
// $season_ids = [1510,104,2506,3645,3679,8389,9698,10417];
// 西甲，NBA
// $season_ids = [38, 8389];

foreach ($data as $v) {
    $name = basename($v['path']);
    $create_time = $v['createtime'];
    // list($id, $season_id, $time) = explode('_', $name);
    // if (in_array($season_id, $season_ids)) {
    //     echo $v['path'] . "\n";
        file_put_contents($filePath . $name, fopen($v['path'], 'r'));
    // }
}

