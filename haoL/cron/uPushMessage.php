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


function run() {
    $push_model = new PushMsgModel();
    $start_time = time();
    $buffer = 0;
    while($buffer < 57) {

        $buffer = time() - $start_time;
        $need_send = $push_model->getNeedSendMsg();
        // dump($need_send);
        //echo $buffer;
        if ($need_send){
            foreach ($need_send as $v) {
                $push_model->sendMsg($v);
            }
        } else {
            sleep(1);
        }

        $need_check_status = $push_model->getNeedCheckMsg();
        //dump($need_check_status);
        //var_dump($need_check_status);die;
        if ($need_check_status) {
            var_dump("【消息状态查询结果】");
            foreach ($need_check_status as $v) {
                $receive_count=0;
                $open_count=0;
                $dismiss_count=0;
                //ios
                if($v['upush_ios_id']){
                    $tmp_result = $push_model->checkSendStatus($v['upush_ios_id'],2);

                    if($tmp_result){
                        $push_model->dealMsgStatus($v['id'], $tmp_result,2);
                    }
                    $receive_count+=$tmp_result['sent_count']?:0;
                    $open_count+=$tmp_result['open_count']?:0;
                    $dismiss_count+=$tmp_result['dismiss_count']?:0;

                }
                if($v['upush_id']){
                    $tmp_result = $push_model->checkSendStatus($v['upush_id'],1);

                    if($tmp_result){
                        $push_model->dealMsgStatus($v['id'], $tmp_result);
                    }
                    $receive_count+=$tmp_result['sent_count']?:0;
                    $open_count+=$tmp_result['open_count']?:0;
                    $dismiss_count+=$tmp_result['dismiss_count']?:0;
                }



                $tmp_result['sent_count']=$receive_count;
                $tmp_result['open_count']=$open_count;
                $tmp_result['dismiss_count']=$dismiss_count;

                if ($tmp_result) {

                    $push_model->statisticsMsg($v['id'], $tmp_result);
                }

            }
            var_dump("【消息状态查询结果】");
        } else {
            sleep(1);
        }
        sleep(1);
    }
}

run();
