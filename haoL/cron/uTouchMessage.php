<?php
require(__DIR__ . "/cron.php");

ini_set('display_errors', 'On');
ini_set('safe_mode', 'Off');
error_reporting(E_ERROR);
//set_time_limit(3);
//error_reporting(E_ALL);


use QK\HaoLiao\Model\PushMsgModel;
use QK\HaoLiao\Model\SoccerModel;
use QK\HaoLiao\Model\BasketballModel;


function run1() {
    $push_model = new PushMsgModel();
    $need_statistics_msg = $push_model->getNeedStatisticsMsg();
    //var_dump($need_statistics_msg);
    if ($need_statistics_msg) {
        foreach ($need_statistics_msg as $v) {
            $tmp_result = $push_model->checkSendStatus($v['upush_id']);
            if ($tmp_result) {
                $push_model->statisticsMsg($v['id'], $tmp_result);
            }
        }
    }
}

function run2() {
    $soccer = new SoccerModel();
    $basketball = new BasketballModel();
    $push_model = new PushMsgModel();
    $upcomingMatch = $soccer->getUpcomingMatch();
    //var_dump($upcomingMatch);
    if ($upcomingMatch) {
        foreach ($upcomingMatch as $v) {
            $user_ids = $soccer->getMatchAttentUser($v['match_num']);
            if ($user_ids) {
                $push_model->createTouchMsg(3, $v, $user_ids);
            }
        }
    }
    $upcomingMatch_b = $basketball->getUpcomingMatch();
        //var_dump($upcomingMatch_b);
        if ($upcomingMatch_b) {
                foreach ($upcomingMatch_b as $v) {
                        $user_ids = $basketball->getMatchAttentUser($v['match_num']);
                        //var_dump($user_ids);
                        if ($user_ids) {
                                $push_model->createTouchMsg(4, $v, $user_ids);
                        }
                }
        }
}

run1();
run2();
