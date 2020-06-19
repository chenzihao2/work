<?php

namespace QK\HaoLiao\Model;

use QK\HaoLiao\Common\Upush;
use QK\HaoLiao\Common\CommonHandler;
use QK\HaoLiao\DAL\DALPushMsg;
use QK\HaoLiao\Model\UserModel;


class PushMsgModel extends BaseModel {

    private $upush_status_uri = "http://msg.umeng.com/api/status";
    private $upush_cancel_uri = "http://msg.umeng.com/api/cancel";
    private $push_match_title = '【最新动态】比赛马上开始';
    private $push_match_text = '您关注的比赛 “%s vs %s” 还有5分钟就要开战啦，快来点击查看~';
    private $push_resource_title = '【新方案】专家爆料';
    private $push_resource_text = '您关注的专家“%s”发布新方案啦，点击查看~';
    private $soccer_skip = ['route' => 'EventDetail', 'data' => ['schid' => 0, 'status' => 0, 'ballType' => 1]];
    private $basket_skip = ['route' => 'EventDetail', 'data' => ['schid' => 0, 'status' => 0, 'ballType' => 2]];
    private $resource_skip = ['route' => 'Content', 'data' => ['rid' => 0]];


    public function createMsg($params) {

        $send_time = $params['send_time'];
        $send_limit = $params['send_limit'];
        if (ENV == 'test' && $send_limit != 7) {
            // return false;
        }
        if($send_limit==0 && !$params['platform']){
            $send_limit=1;//全部用户
            $params['send_limit']=$send_limit;
        }
        if($send_limit==0 && $params['platform']){
            if($params['platform']=='ios'){
                $send_limit=2;//ios用户
            }
            if($params['platform']=='android'){
                $send_limit=3;//android用户
            }
            $params['send_limit']=$send_limit;
        }

        $params['is_at_once'] = 0;
        $now = date('Y-m-d H:i:s', time());
        if (!$send_time || $send_time < $now) {
            $params['is_at_once'] = 1;
            $send_time = date('Y-m-d H:i:s', time());
            $params['send_time'] = $send_time;
        }

        $user_ids = $params['user_ids'];
        unset($params['user_ids']);
        if (isset($params['user_device']) && !empty($params['user_device'])) {
            $user_device = $params['user_device'];
            unset($params['user_device']);
        } else {
            $user_device = $this->sendLimit($send_limit, $user_ids,$params['platform']);
        }

        if (empty($user_device)) {
            return false;
        }
        $params['send_count'] = count($user_device);

        if (!$params['expire_time']) {
            $params['expire_time'] = date('Y-m-d H:i:s', strtotime($send_time) + 86400 * 3);
        }

        //过期时间-发送时间 小于10 分钟  过期时间增加十分钟
        if(strtotime($params['expire_time'])-strtotime($send_time)<10*60){
            $params['expire_time'] = date('Y-m-d H:i:s', strtotime($params['expire_time']) +600);
        }


        $msg_id = $this->msg_dal->addMsg($params);
        return $this->createRelation($user_device, $send_time, $msg_id,$send_limit);

        //$device_tokens = array_column($user_device, 'device_token');
        //var_dump($device_tokens);die;
    }

    public function createTouchMsg($msg_type, $touch_info, $user_ids) {
        $user_ids = implode(',', $user_ids);
        $user_device = $this->sendLimit(7, $user_ids);
        if (empty($user_device)) {
            return false;
        }
        $params = [];
        $params['user_device'] = $user_device;
        $params['msg_type'] = $msg_type;
        $params['send_time'] = '';
        $params['send_limit'] = 7;
        $params['is_at_once'] = 1;
        $params['title'] = $params['text'] = '';
        $params['after_open'] = 'go_app'; //todo
        $params['user_ids'] = $user_ids;
        $condition['msg_type'] = $msg_type;
        $condition['status'] = [' in (', '0,1,4)' ];
        switch ($msg_type) {
            case 2:
                $condition['relate_id'] = $touch_info['resource_id'];
                $exists = $this->msg_dal->existsMsg($condition);
                if ($exists) {
                    return;
                }
                $after_open = $this->resource_skip;
                $after_open['data']['rid'] = $touch_info['resource_id'];
                $params['after_open'] = json_encode($after_open);
                $params['relate_id'] = $touch_info['resource_id'];
                $params['expire_time'] = date('Y-m-d H:i:s', time() + 60 * 60);
                $params['title'] = $this->push_resource_title;
                $params['text'] = sprintf($this->push_resource_text, $touch_info['expert_name']);
                $this->createMsg($params);
                break;
            case 3:
            case 4:
                $condition['relate_id'] = $touch_info['match_num'];
                $exists = $this->msg_dal->existsMsg($condition);
                if ($exists) {
                    return;
                }
                $after_open = $this->soccer_skip;
                $after_open['data']['schid'] = $touch_info['match_num'];
                $after_open['data']['status'] = $touch_info['status'];
                $after_open['data']['ballType'] = 1;
                $params['relate_id'] = $touch_info['match_num'];
                $params['expire_time'] = date('Y-m-d H:i:s', time() + 10 * 60);
                $params['title'] = $this->push_match_title;
                $params['text'] = sprintf($this->push_match_text, $touch_info['host_name'], $touch_info['guest_name']);
                if ($msg_type == 4) { //篮球比赛
                    $after_open['data']['ballType'] = 2;
                    $params['text'] = sprintf($this->push_match_text, $touch_info['guest_name'], $touch_info['host_name']);
                }
                $params['after_open'] = json_encode($after_open);
                $this->createMsg($params);
                break;
            default:
                return false;
        }
    }

    public function sendMsg($msg_info) {
        if (empty($msg_info)) {
            return false;
        }
        $send_info = [];
        $msg_id = $msg_info['id'];
        $send_info['title'] = $msg_info['title'];
        $send_info['text'] = $msg_info['text'];
        $text = json_decode($send_info['text'], 1);
        $builder_id = count($text) - 1;
        if ($builder_id > 0) {
            $send_info['builder_id'] = $builder_id;
        } else {
            $send_info['text'] = $text['text1'];
            $send_info['builder_id'] = 0;
        }
        if ($send_info['text'] == null) {
            $send_info['text'] = $msg_info['text'];
        }
        $send_info['after_open'] = $msg_info['after_open'];
        $send_info['send_limit'] = $msg_info['send_limit'];
        $send_info['is_at_once'] = $msg_info['is_at_once'];
        $send_info['expire_time'] = $msg_info['expire_time'];
        $send_info['send_time'] = $msg_info['send_time'];
        // $send_info['device_tokens'] = array_column($this->msg_dal->getDeviceByMsgId($msg_id), 'device_token');

        $userMsg=$this->msg_dal->getDeviceByMsgId($msg_id);

        //dump($userMsg);
        $deviceArr=$this->distinguishDevice($userMsg);//拆分ios和android

        $send_info['device_tokens'] = $deviceArr;

        $send_res = $this->send($send_info, $msg_id);


        if (!$send_res['android'] && !$send_res['ios']) {
            return false;
        }

        if($send_res['android']){
            $send_android_result = json_decode($send_res['android'], 1);
            if ($send_android_result['ret'] == "SUCCESS") {
                $res_android=$this->msgStatusSent($msg_id, $send_android_result['data']['task_id'],4,1);
            }
        }
        if($send_res['ios']){
            $send_ios_result = json_decode($send_res['ios'], 1);
            if ($send_ios_result['ret'] == "SUCCESS") {
                $res_ios=$this->msgStatusSent($msg_id, $send_ios_result['data']['task_id'],4,2);
            }
        }

        if(!$res_android && !$res_ios){
            return false;
        }else{
            return true;
        }
    }

    public function cancelMsg($msg_id) {
        $condition = ['id' => $msg_id, 'status' => [' in (', '1,4)']];
        $msg_info = $this->msg_dal->getMsgByCondition($condition);
        if (empty($msg_info) || empty($msg_info[0]['upush_id'])) {
            return false;
        }
        $upush_id = $msg_info[0]['upush_id'];

        $upush = new Upush();
        $url = $this->upush_cancel_uri;
        $params = $upush->makeParamsForCheckStatus($url, $upush_id,$msg_info[0]['send_limit']);

        $sign = $params['sign'];
        $post_body = $params['post_body'];
        $post_url = $url . '?sign=' . $sign;
        $result = $this->common->httpPost($post_url, $post_body);
        $result = json_decode($result, 1);
        if ($result['ret'] == 'SUCCESS') {
            return $this->msg_dal->updateMsg(['id' => $msg_id], ['status' => 5]);
        } else {
            return $result;
        }
    }


    //取消ios 任务

    public function cancelIosMsg($msg_id) {
        $condition = ['id' => $msg_id, 'ios_status' => [' in (', '1,4)']];
        $msg_info = $this->msg_dal->getMsgByCondition($condition);
        if (empty($msg_info) || empty($msg_info[0]['upush_ios_id'])) {
            return false;
        }

        $upush = new Upush();
        $url = $this->upush_cancel_uri;
        $params = $upush->makeParamsForCheckStatus($url, $msg_info[0]['upush_ios_id'],2);

        $sign = $params['sign'];
        $post_body = $params['post_body'];
        $post_url = $url . '?sign=' . $sign;
        $result = $this->common->httpPost($post_url, $post_body);
        $result = json_decode($result, 1);
        if ($result['ret'] == 'SUCCESS') {
            return $this->msg_dal->updateMsg(['id' => $msg_id], ['ios_status' => 5]);
        } else {
            return $result;
        }
    }





    private function sendLimit($send_limit, $user_ids = '',$platform='') {
        $user_model = new UserModel();
        $data = [];


        switch ($send_limit) {
            case 1:
                $data = $user_model->getAllUserDevice([],$platform);
                break;
            case 2: //IOS
                $data = $user_model->getDeviceByPlatform('ios');
                break;
            case 3: //Android
                $data = $user_model->getDeviceByPlatform('android');
                break;
            case 4:
                $data = $user_model->getNoLogin(7,$platform);

                break;
            case 5:
                $data = $user_model->getNoLogin(30,$platform);

                break;
            case 6: //付费
                $data = $user_model->getPayingUser($platform);

                break;
            case 7:
                $user_ids = str_replace('，', ',', $user_ids);
                $data = $user_model->getAllUserDevice(['user_ids' => $user_ids]);
                break;
        }

        return $data;
    }

    private function sendLimit_bak($send_limit, $user_ids = '') {
        $user_model = new UserModel();
        $data = [];
        switch ($send_limit) {
            case 1:
                $data = $user_model->getAllUserDevice();
                break;
            case 2: //IOS
                $data = $user_model->getDeviceByPlatform('ios');
                break;
            case 3: //Android
                $data = $user_model->getDeviceByPlatform('android');
                break;
            case 4:
                $data = $user_model->getNoLogin(7);
                break;
            case 5:
                $data = $user_model->getNoLogin(30);
                break;
            case 6: //付费
                $data = $user_model->getPayingUser();
                break;
            case 7:
                $user_ids = str_replace('，', ',', $user_ids);
                $data = $user_model->getAllUserDevice(['user_ids' => $user_ids]);
                break;
        }
        return $data;
    }

    private function createRelation($user_device, $send_time, $msg_id,$send_limit) {
        foreach ($user_device as $v) {
            //ios
            if($send_limit==2 && strlen($v['device_token']) != 64){
                continue;
            }
            $tmp = [];
            $tmp['user_id'] = $v['user_id'];
            $tmp['device_token'] = $v['device_token'];
            $tmp['platform'] =  strlen($v['device_token'])== 64 ? 2 : 1;
            $tmp['msg_id'] = $msg_id;
            $tmp['send_time'] = $send_time;
            $this->msg_dal->addRelation($tmp);
        }
        return true;
    }

    private function send($send_info, $msg_id) {
        $upush = new Upush();
        $title = $send_info['title'];
        $text = $send_info['text'];
        $after_open = $send_info['after_open'];
        $send_limit = $send_info['send_limit'];
        $is_at_once = $send_info['is_at_once'];
        $device_tokens = $send_info['device_tokens'];
        $send_time = $send_info['send_time'];
        $expire_time = $send_info['expire_time'];
        $builder_id = $send_info['builder_id'];
        $url = '';
        if ($after_open != 'go_app') {
            //$url = $after_open;
            //$after_open = "go_url";
            $url = $after_open;
            $after_open = 'go_custom';
        }
        if ($is_at_once) {
            $send_time = '';
        }

        if ($send_limit == 1) {
            //android
            $resAndroid=$upush->sendAndroidBroadcast($msg_id, $title, $text, $after_open, $url, $send_time, $expire_time, $builder_id);
            //ios
            $resIos=$upush->sendIOSBroadcast($msg_id, $title, $text, $after_open, $url, $send_time, $expire_time, $builder_id);


        } else {

            //android
            if(isset($device_tokens['android']) && $device_tokens['android']){
                $resAndroid=$upush->sendAndroidFilecast($msg_id, $title, $text, $after_open, $url, $send_time, $expire_time, $device_tokens['android'], $builder_id);
            }
            //ios
            if(isset($device_tokens['ios']) && $device_tokens['ios']){
                $resIos=$upush->sendIOSFilecast($msg_id, $title, $text, $after_open, $url, $send_time, $expire_time, $device_tokens['ios'], $builder_id);
            }

        }

        $result['android']=$resAndroid;//安卓返回值
        $result['ios']=$resIos;//ios返回值

        return $result;


    }


    //ios Android拆分
    public function distinguishDevice($data){
        $newArr=[];
        foreach ($data as $k => $val) {
            $keyStr=$val['platform']==1?'android':'ios';
            $newArr[$keyStr][] = $val['device_token'];
        }
        return $newArr;
    }


    public function checkSendStatus($upush_id,$send_limit=0) {
        $upush = new Upush();
        $url = $this->upush_status_uri;

        $params = $upush->makeParamsForCheckStatus($url, $upush_id,$send_limit);

        $sign = $params['sign'];
        $post_body = $params['post_body'];
        $post_url = $url . '?sign=' . $sign;
        $result = $this->common->httpPost($post_url, $post_body);

        $result = json_decode($result, 1);
        var_dump($result);
        if ($result['ret'] == 'SUCCESS') {
            return $result['data'];
        }
        return [];
    }

    public function msgStatusSent($msg_id, $upush_id, $status = 4, $platform = 1) {
        $condition['id'] = $msg_id;
        //安卓
        if ($upush_id && $platform==1) {
            $data['upush_id'] = $upush_id;
            $data['status'] = $status;
        }
        if($platform==2 && $upush_id){
            $data['upush_ios_id'] = $upush_id;
            $data['ios_status'] = $status;
        }
        if($platform==1 && !$upush_id){
            $data['status'] = $status;
        }
        if($platform==2 && !$upush_id){
            $data['ios_status'] = $status;
        }
        return $this->msg_dal->updateMsg($condition, $data);
    }

    public function dealMsgStatus($msg_id, $umeng_result,$platform=1) {
        // 消息状态: 0-排队中, 1-发送中，2-发送完成，3-发送失败，4-消息被撤销，
        // 5-消息过期, 6-筛选结果为空，7-定时任务尚未开始处理
        $status = $umeng_result['status'];
        $data = [];
        if (in_array($status, [2, 5, 6])) {
            $data['status'] = 1;
        }
        if (in_array($status, [3])) {
            $data['status'] = 3;
        }
        if (in_array($status, [4])) {
            $data['status'] = 2;
        }
        if (in_array($status , [1, 7])) {
            return;
        }
        //ios
        if($platform==2){
            $data['ios_status'] = $data['status'];
            unset($data['status']);
        }

        $this->msg_dal->updateMsg(['id' => $msg_id], $data);
    }

    public function statisticsMsg($msg_id, $umeng_result) {
        $data = [];
        $data['receive_count'] = $umeng_result['sent_count'] ?: 0;
        $data['open_count'] = $umeng_result['open_count'] ?: 0;
        $data['dismiss_count'] = $umeng_result['dismiss_count'] ?: 0;
        $this->msg_dal->updateMsg(['id' => $msg_id], $data);
    }

    public function msgCenter($params) {
        $user_id = $params['user_id'];
        $page = $params['page'];
        $pagesize = $params['pagesize'];
        return $this->msg_dal->getMsgByUserId($user_id, $page, $pagesize);
    }

    public function getNeedSendMsg() {
        $condition['status'] = 0;
        $condition['ios_status'] =0;
        return $this->msg_dal->getMsgByCondition($condition);
    }

    public function getNeedCheckMsg() {
        $expire_time="'".date('Y-m-d H:i:s',time())."'";
        $condition['status'] = [' in (' , '1,4,5)'];
        $condition['upush_id'] = [' <> ', "''"];
        $condition['expire_time'] = ['>=',$expire_time];
        //$now = "'" . date('Y-m-d H:i:s') . "'";
        //$condition['send_time'] = [' < ', $now];
        $result=$this->msg_dal->getMsgByCondition($condition);

        //ios
        $condition2['ios_status'] = [' in (' , '1,4,5)'];
        $condition2['expire_time'] = ['>=',$expire_time];
        $condition2['upush_ios_id'] = [' <> ', "''"];
        $result2=$this->msg_dal->getMsgByCondition($condition2);
        $data=array_merge($result,$result2);
        return $data;
    }

    public function getNeedStatisticsMsg() {
        $condition['status'] = 1;
        $now = "'" . date('Y-m-d H:i:s') . "'";
        $condition['expire_time'] = [' > ' , $now];
        return $this->msg_dal->getMsgByCondition($condition);
    }

    public function getMsgList($condition = [], $fields = [], $page = 1, $pagesize = 20, $orderBy = [], $relation = 0) {

        return $this->msg_dal->getMsgList($condition, $fields, $page, $pagesize, $orderBy, $relation);
    }

    public function getMsgCount($condition = [], $relation = 0) {
        $count = $this->msg_dal->getMsgCount($condition, $relation);
        return $count[0]['count'];
    }

    public function getMsgRelation($condition = [], $fields = [], $page = 1, $pagesize = 50, $orderBy = []) {
        return $this->msg_dal->getMsgRelation($condition, $fields, $page, $pagesize, $orderBy);
    }

    public function __construct() {
        parent::__construct();
        //$this->_redisModel = new RedisModel("match");
        $this->msg_dal = new DALPushMsg($this->_appSetting);
        $this->common = new CommonHandler();
    }
}
