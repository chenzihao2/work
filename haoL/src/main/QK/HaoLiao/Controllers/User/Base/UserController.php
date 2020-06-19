<?php
/**
 * 用户中心
 * User: WangHui
 * Date: 2018/10/19
 * Time: 下午4:17
 */

namespace QK\HaoLiao\Controllers\User\Base;

use QK\HaoLiao\Common\SmsSend;
use QK\HaoLiao\Controllers\User\UserController as User;
use QK\HaoLiao\Model\OrderModel;
use QK\HaoLiao\Model\RedisModel;
use QK\HaoLiao\Model\SmsModel;
use QK\HaoLiao\Model\UserFollowModel;
use QK\HaoLiao\Model\UserModel;
use QK\HaoLiao\Model\ChannelModel;
use QK\HaoLiao\Model\UserSubscribeModel;
use QK\HaoLiao\Model\ResourceModel;
use QK\HaoLiao\Common\CommonHandler;
use QK\WSF\Settings\AppSetting;

class UserController extends User {

    /**
     * 用户信息
     */
    public function userInfo() {
        $param = $this->checkApiParam([], ['user_id' => 0]);
        $userId = $param['user_id'];
        if($userId){
            $this->checkToken();
        } else {
            $this->responseJson([]);
        }

        $userModel = new UserModel();
        $userInfo = $userModel->getUserInfo($userId);
        $data = [];
        $data['user_id'] = $userId;
        $data['cid'] = $userInfo['cid'];
        $data['uuid'] = $userInfo['uuid'];
        $data['phone'] = intval($userInfo['phone']);
        $data['nick_name'] = $userInfo['nick_name'];
        $data['sex'] = $userInfo['sex'];
        $data['headimgurl'] = $userInfo['headimgurl'];
        $data['user_status'] = $userInfo['user_status'];
        $channelModel = new ChannelModel();
        if(!empty($userInfo['uuid'])) {
          $boundInfo = $channelModel->getBoundInfo($userInfo['uuid']);
          $data['boundInfo'] = $boundInfo;
        } else {
          $userChannelInfo = $channelModel->getChannel($userInfo['cid']);
          $data['boundInfo'] = array($userChannelInfo['target'] => $userInfo['nick_name']);
        }
        $userFollowModel = new UserFollowModel();
        $followNum = $userFollowModel->getUserFollowExpertNumber($userId);
        $data['follow_num'] = $followNum;
        // 获取用户余额
        //$userBalanceInfo = $userModel->getUserBalanceByUserId($userId);
        //$data['vc_balance'] = empty($userBalanceInfo['vc_balance']) ? 0 : $userBalanceInfo['vc_balance'];
        $data['vc_balance'] = $userModel->getUserBalanceByUserId($userId);
        $this->responseJson($data);
    }

    /**
     * 用户订阅列表
     */
    public function subscribe() {
        $this->checkToken();
        $param = $this->checkApiParam(['user_id'], ['page' => 0, 'pagesize' => 0]);
        $userId = intval($param['user_id']);
        $page = intval($param['page']);
        $pageSize = intval($param['pagesize']);
        $userSubscribe = new UserSubscribeModel();
        $list = $userSubscribe->getUserSubscribeList($userId, $page, $pageSize);
        $this->responseJson($list);
    }

    /**
     * 用户购买列表
     */
    public function buyList() {
        $this->checkToken();
        $param = $this->checkApiParam(['user_id'], ['page' => 1, 'pagesize' => 10]);
        $userId = intval($param['user_id']);
        $page = intval($param['page']);
        $pageSize = intval($param['pagesize']);
        $orderModel = new OrderModel();
        $list = $orderModel->userOrderResourceList($userId, $page, $pageSize);

        $resourceModel=new ResourceModel();
        foreach($list as $key=>$val){
                $resourceExtraInfo = $resourceModel->getResourceExtraInfo($val['resource_id']);
                //通过比赛判定红黑单
                $resourceScheduleList = $resourceModel->getResourceScheduleList($val['resource_id']);
                $bet_status = $resourceModel->getBetStatus($resourceScheduleList);
                if(empty($resourceScheduleList)){
                    $bet_status=$resourceExtraInfo['bet_status'];
                }
                //如果有手动判的 已手动判的为准
                if ($resourceExtraInfo['bet_status']) {
                    $bet_status=$resourceExtraInfo['bet_status'];
                }
            $list[$key]['bet_status']=$bet_status;
        }


        $this->responseJson($list);
    }

    /**
     * 关注专家列表
     */
    public function followList() {
        $this->checkToken();
        $param = $this->checkApiParam(['user_id'], ['page' => 1, 'pagesize' => 10]);
        $userId = intval($param['user_id']);
        $page = intval($param['page']);
        $pageSize = intval($param['pagesize']);
        $userFollowModel = new UserFollowModel();
        $list = $userFollowModel->followExpertList($userId, $page, $pageSize);
        $this->responseJson($list);
    }

    public function followList2() {
        $this->checkToken();
        $param = $this->checkApiParam(['user_id'], ['lastId' => '', 'pagesize' => 10, 'platform' => 1]);
        $userId = intval($param['user_id']);
        $condition = array('follow_status' => 1, 'user_id' => $userId);
        if (!empty($param['lastId'])) {
          $condition['create_time'] = ['<', $param['lastId']];
        }
        $userModel = new UserModel();
        $userInfo = $userModel->getUserInfo($userId);
        if($userInfo['uuid']) {
          $users = $userModel->getUsersByUUid($userInfo['uuid']);
          $uids = implode(', ', array_column($users, 'user_id'));
          $condition['user_id'] = ['in', "($uids)"];
        }else {
          $condition['user_id'] = $userId;
        }

        $pagesize = intval($param['pagesize']);
        $orderBy = ['create_time' => 'DESC'];

        $platform = intval($param['platform']);

        $userFollowModel = new UserFollowModel();
        $list = $userFollowModel->getFollowList($condition, array(), 0, $pagesize, $orderBy, $platform);
        $this->responseJson($list);
    }

    /**
     * 发送验证码
     */
    public function sendCode(){
        $this->checkToken();
        $params = $this->checkApiParam(['user_id', 'phone']);
        $smsSend = new SmsSend();
        if (!$smsSend->mobileCheck($params['phone'])) {
            $this->responseJsonError(1105);
        }

        $userModel = new UserModel();
        $userInfo = $userModel->getUserInfoByPhone($params['phone']);
        if(!empty($userInfo)){
            $this->responseJsonError(1001);
        }

        $smsModel = new SmsModel();
        $sendTimes = $smsModel->todaySendCount($params['phone']);
        if ($sendTimes > 3) {
            $this->responseJsonError(1102);
        }
        $code = rand(1111, 9999);
        $content = '注册验证码' . $code . '，五分钟内有效！为保障您的账户安全，请勿将验证码透露给他人。【好料精选】';
        $smsSend->send($params['phone'], $content);
        $smsModel->sendLog($params['phone'], $code, $params['user_id']);
        $redisModel = new RedisModel("user");
        $redisModel->redisSet(SMS_CODE . $params['phone'], $code, 300);
        $this->responseJson();
    }

    /**
     * 绑定手机号操作
     */
    public function bindPhone(){
        $this->checkToken();
        $param = $this->checkApiParam(['user_id', 'phone', 'code']);
        $userId = intval($param['user_id']);
        $phone = intval($param['phone']);
        $code = intval($param['code']);

        $redisModel = new RedisModel("user");
        $redisCode = $redisModel->redisGet(SMS_CODE . $phone);
        if ($code != $redisCode) {
            //验证失败
            $this->responseJsonError(1103);
        }

        $userModel = new UserModel();
        $userInfo = $userModel->getUserInfoByPhone($phone);
        if(!empty($userInfo)){
            $this->responseJsonError(1001);
        }

        $userModel = new UserModel();
        $userInfo = $userModel->getUserInfo($userId);
        if($userInfo['phone']){
            $this->responseJsonError(1001);
        }

        $res = $userModel->updateUser($userId, ['phone' => $phone]);
        if($res){
            $this->responseJson();
        }

        $this->responseJsonError(101);
    }

    public function domain() {
        $param = $this->checkApiParam([], ['version' => 1]);
        $version = $param['version'];
        if($version < 3.4){
            $this->responseJson(['domain' => 'https://api.haoliao188.com', 'show' => 0]);
        } else {
            $this->responseJson(['domain' => 'https://api.haoliao188.com', 'show' => 0]);
        }
    }

    public function checkDevice() {
        $param = $this->checkApiParam(['device_number'], ['user_id' => 0]);
        $device_number = $param['device_number'];
        $user_id = $param['user_id'] ?: 0;
        $userModel = new UserModel();
        $result = $userModel->checkDevice($device_number, $user_id);
        $this->responseJson($result);
    }

    public function checkIdfa() {
        header('Content-type:text/json;charset=utf-8');
        $return = ["code" => 0,"msg" => "", "time" => "", "data" => []];
        $param = $this->checkApiParam([], ['idfa' => '']);
        $idfa = $param['idfa'];
        if (empty($idfa)) {
            $return["msg"] = '缺少参数idfa';
            $return["time"] = time();
            echo json_encode($return);
            exit;
        }
        $userModel = new UserModel();
        $result = $userModel->checkIdfa($idfa);
        $return["code"] = 1;
        $return["msg"] = "成功获取去重数据";
        if ($result === 'exists') {
            $return["data"] = [$idfa => 1];
        } else {
            $return["data"] = [$idfa => 0];
        }
        $return["time"] = time();
        echo json_encode($return);
        exit;
    }

    public function upgrade() {
	$channelCollection = ['vivo', 'yyb', 'ali', 'huawei'];
      $param = $this->checkApiParam([], ['channel' => 'vivo']);
	$channel = $param['channel'];
	if(!in_array($channel, $channelCollection)) {
		$this->responseJson([], "CHANNEL NOT EXIST");
	}
      $url = 'https://hl-static.haoliao188.com/packages/android/upgrade/' . $channel . '/app-' . $channel . '-release.apk';
	if ($channel == 'huawei') {
      $res = array(
        'version_code' => 1,
	'version_name' => 'V1.0.2',
        'package_url' => $url,
        'update_info' => '1.新增注册登录界面，可以绑定手机\n 2.新增比赛模块，调整专家页面\n 3.我的页面整体调整，新增货币系统\n 4.我的页面整体调整，新增货币系统'
      );
	}else {
	$res = array(
        'version_code' => 2,
        'version_name' => 'V1.0.2',
        'package_url' => $url,
        'update_info' => '1.新增注册登录界面，可以绑定手机\n 2.新增比赛模块，调整专家页面\n 3.我的页面整体调整，新增货币系统\n 4.我的页面整体调整，新增货币系统'
      );
	}
      $this->responseJson($res);
    }

    public function showlist() {
      $channelCollection = ['vivo', 'yyb', 'ali', 'huawei', 'dev', 'ios'];
      $param = $this->checkApiParam([], ['channel' => 'vivo', 'version' => '2.2.6']);
      $channel = $param['channel'];
      $version = $param['version'];
      $common = new CommonHandler();
      $appSetting = AppSetting::newInstance(APP_ROOT);
      $prefix_url = $appSetting->getConstantSetting("CheckConfigUrl");
      $url = $prefix_url . "channel=$channel&version=$version";
      $data = $common->httpGet($url, []);
      $data = json_decode($data, 1);
      $this->responseJson($data['data']);
      return;
      $tablist1 = [];
      $tablist1 = [['key' => 1, 'value' => '方案'], ['key' => 2, 'value' => '专家'], ['key' => 3, 'value' => '解读']];
      $data = ['tablist' => ['Information', 'Video', 'Competition', 'Recommend', 'My'], 'show' => 0, 'tablist1' => $tablist1, 'display' => 0];
      if (in_array($channel, ['ali', 'huawei', 'oppo', 'xiaomi', 'lenovo', 'yyb', 'samsung', 'vivo', 'baidu', 'meizu', '360','baidu'])) {
        //$this->responseJson(['tablist' => ['Information', 'Video', 'Competition', 'Recommend', 'My'], 'show' => 1]);
        $this->responseJson(['tablist' => ['Information', 'Video', 'My'], 'show' => 0]);
      } else if(in_array($channel, ['dev', 'ios'])) {
          $data['show'] = 1;
          $data['display'] = 1;
        $this->responseJson($data);
      } else if ($channel == 'ios_v2') {
        $this->responseJson(['tablist' => ['Recommend', 'Competition', 'Information', 'Video', 'My'], 'show' => 1]);
      } else {
        $this->responseJson(['Index', 'Expert', 'Competition', 'Information', 'My']);
      }
    }

}
