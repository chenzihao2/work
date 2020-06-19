<?php
require(__DIR__ . "/../cron.php");

use QK\HaoLiao\Common\CommonHandler;

ini_set('display_errors', 'On');
date_default_timezone_set('Asia/Shanghai');
error_reporting(E_ALL | E_STRICT);

$filePath = __DIR__ . '/result/';

// 哥伦甲, 英冠, 亚冠, 俄青联, 乌克U21, NBA, 俄篮超, 立陶甲
// $season_ids = [1510,104,2506,3645,3679,8389,9698,10417];

$data = [
    // [
    //     'file' => '107_10565_1556513352180.txt',
    //     'match_time' => '2019-04-23 09:00:00.0'
    // ],
    // [
    //     'file' => '10_104_1556448392159.txt',
    //     'match_time' => '2019-04-23 00:15:00.0'
    // ],
    // [
    //     'file' => '190_2506_1556441383174.txt',
    //     'match_time' => '2019-04-23 17:30:00.0'
    // ],
    // [
    //     'file' => '314_3645_1556448729980.txt',
    //     'match_time' => '2019-04-23 17:00:00.0'
    // ],
    // [
    //     'file' => '319_3679_1556405432721.txt',
    //     'match_time' => '2019-04-23 17:00:00.0'
    // ],
    // [
    //     'file' => '580_8389_1556448844628.txt',
    //     'match_time' => '2019-04-24 10:30:00.0'
    // ],
    // [
    //     'file' => '580_8389_1556448844628.txt',
    //     'match_time' => '2019-04-25 10:30:00.0'
    // ],
    // [
    //     'file' => '1715_9698_1556218915594.txt',
    //     'match_time' => '2019-04-25 23:00:00.0'
    // ],
    [
        'file' => '640_8966_1556520698356.txt',
        'match_time' => '2019-04-25 23:15:00.0'
    ],
];


foreach ($data as $one) {
    $filename = '/Applications/MAMP/htdocs/haoliao/backend-api/cron/threedata/result/' . $one['file'];
    if (filesize($filename) >= 20000000) {
        $file = fopen($filename, 'r');
        while (!feof($file)) {
            $str = fgets($file, 1000);
            $isHas = strpos($str, '"match_time":"' . $one['match_time'] . '"');
            if ($isHas) {
                $time_pos = ftell($file) - strlen($str) + $isHas;
                $pos = $time_pos;
                $a = 0;
                // 开始位置查找
                fseek($file, $pos);
                while ($a < 1 && !feof($file)) {
                    $char = fgetc($file);
                    if ($char === '{') {
                        $a++;
                    } elseif ($char === '}') {
                        $a--;
                    }
                    $pos--;
                    fseek($file, $pos);
                }
                $start = $pos + 1;
                // 结束位置查找
                $pos = $time_pos;
                $a = 0;
                fseek($file, $pos);
                while ($a < 1 && !feof($file)) {
                    $char = fgetc($file);
                    if ($char === '}') {
                        $a++;
                    } elseif ($char === '{') {
                        $a--;
                    }
                    $pos++;
                    fseek($file, $pos);
                }
                $end = $pos + 1;
                fseek($file, $start);
                $match = fgets($file, $end - $start);
                $match = json_decode($match);
                file_put_contents($filePath . $match->season_id . '_' . $match->match_time . '.json', json_encode($match));
            }
        }
    } else {
        $data = file_get_contents('/Applications/MAMP/htdocs/haoliao/backend-api/cron/threedata/result/' . $one['file']);
        $data = json_decode($data);

        $matchs = $data->matchs;
        unset($data);

        foreach ($matchs as $match) {
           if ($match->match_time == $one['match_time']) {
               file_put_contents($filePath . $match->season_id . '_' . $match->match_time . '.json', json_encode($match));
           }
        }
    }

}

