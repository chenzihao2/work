<?php
require(__DIR__ . "/../cron.php");

ini_set('display_errors', 'On');
date_default_timezone_set('Asia/Shanghai');
error_reporting(E_ALL | E_STRICT);

$filePath = __DIR__ . '/result';
$historyPath = $filePath . '/history';
$data = file_get_contents('/Applications/MAMP/htdocs/haoliao/backend-api/cron/threedata/result/历史数据.json');
$data = json_decode($data);

$tour_ids = [3, 580];
$zip = new ZipArchive();
$tourString = '"tournament"';
$seasonString = '"season"';
$fileList = [];

foreach ($data as $one) {
    $name = basename($one->path);
    list($id, $season_id, $time) = explode('_', $name);

    if (in_array($id, $tour_ids)) {
        $zipfile = $historyPath . '/' . $name;

        if (!file_exists($zipfile)) {
            file_put_contents($zipfile, fopen($one->path, 'r'));
            echo $zipfile . "   已下载\n";
        }

        if ($zip->open($zipfile) === true) {
            $zip->extractTo($historyPath);
            echo $zipfile . "解压成功\n";
        } else {
            throw new Exception($zipfile . '解压失败');
        }
        $dataPath = $historyPath . '/data.txt';
        $dataFile = fopen($dataPath, 'r');

        $fileName = $id . '.json';

        $fileExists = file_exists($historyPath . '/' . $fileName);

        $strings = [];

        if (!$fileExists) {
            $strings = [$tourString, $seasonString];
        } else {
            $strings = [$seasonString];
        }

        $saveResult = '';
        $result = [];

        while (!feof($dataFile)) {
            $substr = fgets($dataFile, 1000);

            foreach ($strings as $string) {
                $subpos = strpos($substr, $string);
                if ($subpos) {
                    $pos = ftell($dataFile) - strlen($substr) + $subpos;

                    $saveResult = ",{";

                    if ($string === $tourString) {
                        fseek($dataFile, $pos + 13);
                        if (fgetc($dataFile) != '{') break;
                        fseek($dataFile, $pos + 14);
                    } else {
                        fseek($dataFile, $pos + 9);
                        if (fgetc($dataFile) != '{') break;
                        fseek($dataFile, $pos + 10);
                    }

                    $a = 0;
                    while ($a < 1 && !feof($dataFile)) {
                        $char = fgetc($dataFile);
                        $saveResult .= $char;
                        if ($char == '{') {
                            $a--;
                        } elseif ($char == '}') {
                            $a++;
                        }
                    }
                    $result[$string] = $saveResult;
                    if (count($result) >= count($strings)) break;
                }
            }
        }

        fclose($dataFile);

        foreach ($strings as $item ){
            $saveFileName = $historyPath . '/' . $fileName;
            if (!in_array($saveFileName, $fileList)) {
                $fileList[] = $saveFileName;
            }
            file_put_contents($saveFileName, $result[$item], FILE_APPEND);
            echo $name . $item . "已保存\n";
        }
    }
}

foreach ($fileList as $f) {
    $fhandler = fopen($f, 'r+');
    fwrite($fhandler, '[');
    fseek($fhandler, filesize($f));
    fwrite($fhandler, ']');
    fclose($fhandler);
}

