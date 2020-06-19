<?php
/**
 * 篮球数据抓取
 * User: WangHui
 * Date: 2018/10/31
 * Time: 上午10:29
 */
require(__DIR__ . "/../cron.php");

use QL\QueryList;
use QK\HaoLiao\Model\CrawlerModel;

date_default_timezone_set('PRC');
$html = QueryList::get('https://www.qtx.com/dbbasketball')->getHtml();
//获取所有联赛
$data = QueryList::html($html)->rules([
    'link' => [
        '.subMenuShow a',
        'href'
    ],
    'text' => [
        '.subMenuShow a',
        'text'
    ]
])->query()->getData();

foreach ($data->all() as $info) {
//    $leagueId = explode("_", $info['link']);
//    $leagueId = $leagueId[1];
    $legendName = $info['text'];
    //联赛入库
    $crawlerModel = new CrawlerModel();
//    $crawlerModel->newLeague($leagueId, $legendName, 2);
    $leagueId = $crawlerModel->newLeague($legendName,$info['link'], 2);
    //赛程抓取
    ScheduleGet($info['link'], $leagueId, $legendName);
}

function ScheduleGet($url, $leagueId, $leagueName) {
    $matchType = 2;
    $html = QueryList::get($url)->getHtml();
    $data = QueryList::html($html)->find('.restb tbody tr')->map(function ($item) {
        $time = $item->find('td:eq(0)')->text();
        $timeExecute = explode("
", $time);
        //比赛时间处理
        $time1Execute = explode("(", trim($timeExecute['0']));
        $time1 = $time1Execute[0];
        $timeNoExecute = mb_substr($time1Execute[1],3);
        $time2 = str_replace("/", "-", trim($timeExecute[1]));
        $data['time'] = $time2 . " " . $time1 . ":00";
        $data['master'] = $item->find('td:eq(1)')->text();
        $score = $item->find('td:eq(2)')->text();
        $scoreExecute = explode('-', $score);
        if (trim($scoreExecute[0]) != "") {
            $data['masterScore'] = trim($scoreExecute[0]);
        }
        if (trim($scoreExecute[1]) != "") {
            $data['guessScore'] = trim($scoreExecute[1]);
        }
        $data['guess'] = $item->find('td:eq(3)')->text();
        return $data;
    });

    foreach ($data->all() as $info) {
        if ($info['master'] == "" || $info['guess'] == "" || $info['time'] == "") {
            continue;
        }
        $params = [];
        $params['match_type'] = $matchType;
        $params['league_id'] = $leagueId;
//        $params['league_name'] = $leagueName;
        $params['master_team'] = $info['master'];
        $params['guest_team'] = $info['guess'];
        $result = 0;
        if (isset($info['masterScore']) && isset($info['guessScore'])) {
            $params['master_score'] = $info['masterScore'];
            $params['guest_score'] = $info['guessScore'];
            if ($info['masterScore'] > $info['guessScore']) {
                $result = 1;
            } elseif ($info['masterScore'] < $info['guessScore']) {
                $result = 2;
            } else {
                $result = 3;
            }
        }
        $params['result'] = $result;
        $params['schedule_time'] = strtotime($info['time']);
        $params['schedule_status'] = 2;
        $crawlerModel = new CrawlerModel();
        $crawlerModel->scheduleToDB($params);
    }
}