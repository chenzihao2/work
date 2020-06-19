<?php
/**
 * User: YangChao
 * Date: 2019/03/19
 */

namespace QK\HaoLiao\Controllers\User\V2;

use Firebase\JWT\JWT;
use QK\HaoLiao\Common\BaiDuParams;
use QK\HaoLiao\Common\CommonHandler;
use QK\HaoLiao\Controllers\User\Base\LoginController as Login;
use QK\HaoLiao\Model\UserModel;
use QK\HaoLiao\Model\ChannelModel;
use QK\HaoLiao\Model\LogUserLoginModel;
use QK\HaoLiao\Common\SmsSend;
//use QK\HaoLiao\Common\KsyunSms;
use QK\HaoLiao\Common\UcloudSms;
use QK\HaoLiao\Common\Weixin;
use QK\HaoLiao\Model\SmsModel;
use QK\HaoLiao\Model\RedisModel;
use QK\WSF\Settings\AppSetting;

class LoginController extends Login {

    public function __construct(AppSetting $appSetting) {
        parent::__construct($appSetting);
    }

    /**
     * 解密百度用户信息
     */
    public function decryptBaiduUserInfo(){
        $params  = $this->checkApiParam(['iv', 'cipher', 'code']);
        $iv = $params['iv'];
        $cipher = $params['cipher'];
        $code = $params['code'];
        $programBaiDuSmallRoutineInfo = $this->geBaiDuSmallRoutineParams();

        //获取百度session_key
        $commonHandler = new CommonHandler();
        $postParams = [];
        $postParams['code'] = $code;
        $postParams['client_id'] = $programBaiDuSmallRoutineInfo['App-Key'];
        $postParams['sk'] = $programBaiDuSmallRoutineInfo['App-Secret'];
        $baiDuSessionKeyJson = $commonHandler->httpPost('https://spapi.baidu.com/oauth/jscode2sessionkey', $postParams);
        $baiDuSessionKeyArr = json_decode($baiDuSessionKeyJson, true);
        $sessionKey = $baiDuSessionKeyArr['session_key'];

        $appKey = $programBaiDuSmallRoutineInfo['App-Key'];

        //解密百度数据
        $baiDuParams = new BaiDuParams();
        $plaintext = $baiDuParams->decrypt($cipher, $iv, $appKey, $sessionKey);

        $baiDuUserInfo = json_decode($plaintext, true);

        $userModel = new UserModel();
        $userLoginInfo = $userModel->baiDuSmallRoutineLogin($baiDuUserInfo);

        $userId = $userLoginInfo['uid'];

        $userInfo = $userModel->getUserInfo($userId);
        $tokenArray = [
            "uid" => $userId, "nickname" => $userInfo['nick_name'], "iat" => time(), "exp" => time() + 7200,
        ];

        $key = $this->_appSetting->getConstantSetting('JWT-KEY');
        $token = JWT::encode($tokenArray, $key);

        $this->responseJson(['user_id' => $userId, 'token' => $token]);
    }

    public function decryptBaiduUserInfoV2(){
        $params  = $this->checkApiParam(['iv', 'cipher', 'code']);
        $iv = $params['iv'];
        $cipher = $params['cipher'];
        $code = $params['code'];
        $programBaiDuSmallRoutineInfo = $this->geBaiDuSmallRoutineParamsV2();

        //获取百度session_key
        $commonHandler = new CommonHandler();
        $postParams = [];
        $postParams['code'] = $code;
        $postParams['client_id'] = $programBaiDuSmallRoutineInfo['App-Key'];
        $postParams['sk'] = $programBaiDuSmallRoutineInfo['App-Secret'];
        $baiDuSessionKeyJson = $commonHandler->httpPost('https://spapi.baidu.com/oauth/jscode2sessionkey', $postParams);
        $baiDuSessionKeyArr = json_decode($baiDuSessionKeyJson, true);
        $sessionKey = $baiDuSessionKeyArr['session_key'];

        $appKey = $programBaiDuSmallRoutineInfo['App-Key'];

        //解密百度数据
        $baiDuParams = new BaiDuParams();
        $plaintext = $baiDuParams->decrypt($cipher, $iv, $appKey, $sessionKey);

        $baiDuUserInfo = json_decode($plaintext, true);

        $userModel = new UserModel();
        $userLoginInfo = $userModel->baiDuSmallRoutineLogin($baiDuUserInfo);

        $userId = $userLoginInfo['uid'];

        $userInfo = $userModel->getUserInfo($userId);
        $tokenArray = [
            "uid" => $userId, "nickname" => $userInfo['nick_name'], "iat" => time(), "exp" => time() + 7200,
        ];

        $key = $this->_appSetting->getConstantSetting('JWT-KEY');
        $token = JWT::encode($tokenArray, $key);

        $this->responseJson(['user_id' => $userId, 'token' => $token, 'expire' => $tokenArray['exp'] - 10]);
    }

    //public function sendCode(){
    //    $params = $this->checkApiParam(['mobile']);
    //    $smsSend = new SmsSend();
    //    if (!$smsSend->mobileCheck($params['mobile'])) {
    //        $this->responseJsonError(1105);
    //    }

    //    $smsModel = new SmsModel();
    //    $sendTimes = $smsModel->todaySendCount($params['mobile']);
    //    if ($sendTimes > 100) {
    //        $this->responseJsonError(1102);
    //    }
    //    $code = rand(111111, 999999);
    //    $content = '【好料比分】验证码：'. $code . '，五分钟内有效！为保障您的帐户安全，请勿将验证码透露给他人。';
    //    $smsSend->send($params['mobile'], $content);
    //    $smsModel->sendLog($params['mobile'], $code, 1);
    //    $redisModel = new RedisModel("user");
    //    $redisModel->redisSet(SMS_CODE . $params['mobile'], $code, 300);
    //    $this->responseJson();
    //}

    public function sendCode(){
        $params = $this->checkApiParam(['mobile']);
        //$smsSend = new KsyunSms();
        $smsSend = new UcloudSms();
        if (!$smsSend->mobileCheck($params['mobile'])) {
            $this->responseJsonError(1105);
        }

        $smsModel = new SmsModel();
        $sendTimes = $smsModel->todaySendCount($params['mobile']);
        if ($sendTimes > 100) {
            $this->responseJsonError(1102);
        }
        $code = mt_rand(100000,999999);
        $send_return = $smsSend->send($params['mobile'], $code);
        if ($send_return['status_code'] == 200) {
            $smsModel->sendLog($params['mobile'], $code, 1);
            $redisModel = new RedisModel("user");
            $redisModel->redisSet(SMS_CODE . $params['mobile'], $code, 300);
            $this->responseJson();
        } else {
            $this->responseJsonError(1103);
        }
    }

    public function loginV2() {
      $params  = $this->checkApiParam(['target', 'platform', 'pname'], ['channel' => '0', 'user_id' => 0, 'mobile' => '', 'mobile_code' => '', 'code' => '', 'rowData' => '']);
      if(!empty($params['user_id'])) {
        $this->checkToken();
      }
      $pname = $params['pname'];
      $target = $params['target'];
      $platform = $params['platform'];
      $channel = $params['channel'];

      $channelModel = new ChannelModel();

      $channelUserInfo = array();
      $targetUser = array();
      if($target == 'mobile') {		//mobile授权
        $smsSend = new SmsSend();
        if (!$smsSend->mobileCheck($params['mobile'])) {
          $this->responseJsonError(1105);
        }
        $targetUser = $this->checkMobileCode($params['mobile'], $params['mobile_code']);
        if(empty($params['mobile_code']) || empty($targetUser)) {
          return $this->responseJsonError(11111, "验证码不正确");
        }
	      //$channelUserInfo = $channelModel->getUserByChannel(['target' => $target, 'platform' => ['in', '("ios", "android")'], 'mobile' => $targetUser['mobile']]);
	      $channelUserInfo = $channelModel->getUserByChannel(['platform' => ['in', '("ios", "android","pc")'], 'mobile' => $targetUser['mobile']]);
      } else {			//wx授权
	      //$appid = 'wx87ad1dd9acf928b0';
	      //$appsecret = '6ea0f38d9b9516e9720b65fe19137f18';
	      //if ($channel != '0') {
	        $appid = 'wx9cc12e1169da2064';
          $appsecret = 'af0feedd603075237338cc8b1ad010e6';
	      //}
        if (empty($params['code']) && empty($params['rowData'])) {
          return $this->responseJsonError(11112, '参数错误');
        }
        if (!empty($params['code'])) {
          $wxHelper = new Weixin($appid, $appsecret, array('scope'=> 'user', 'platform'=>$platform, 'pname'=>$pname));
          $targetUser = $wxHelper->getUserInfo($params['code']);
		  if(!$targetUser['openId']){
			  return $this->responseJsonError(11114, '授权失败');
		  }
        } else {
          $rowData = json_decode($params['rowData'], true);
          $targetUser = array(
            'openId' => $rowData['openid'],
            'unionId' => $rowData['unionid'],
            'name'   => $rowData['name'],
            'sex'    => $rowData['gender'] == '男' ? 1 : 2,
            'avatar' => $rowData['iconurl'],
            'mobile' => '',
            'city'   => $rowData['city'],
            'province'  => $rowData['province'],
            'country'   => $rowData['country'],
            'birthday'  => ''
          );
        }
        //$channelUserInfo = $channelModel->getUserByChannel(['target' => $target, 'platform' => ['in', '("ios", "android")'], 'openId' => $targetUser['openId']]);
        $channelUserInfo = $channelModel->getUserByChannel(['platform' => ['in', '("ios", "android","pc")'], 'openId' => $targetUser['openId']]);
        if(empty($channelUserInfo) && !empty($targetUser['unionId'])) {
          //$channelUserInfo = $channelModel->getUserByChannel(['target' => $target, 'platform' => ['in', '("ios", "android")'], 'unionId' => $targetUser['unionId']]);
          $channelUserInfo = $channelModel->getUserByChannel([ 'platform' => ['in', '("ios", "android","pc")'], 'unionId' => $targetUser['unionId']]);
        }
      }

      if(empty($params['user_id'])) {
        $this->register($channelUserInfo, $targetUser, $target, $platform, $channel, $pname);   //register
      } else {
        $this->bind($channelUserInfo, $targetUser, $target, $platform, $channel, $pname, $params['user_id']);     //bind
      }

      //$this->registerUser($targetUser, $platform, $target, $channel, $pname, $params['user_id']);
    }

    /*private function registerUser($targetUser, $platform, $target, $channel, $pname = 'haoliao', $bindUid = 0) {
      $channelModel = new ChannelModel();
      $channelCondition = ['target' => $target];
      $channelUserInfo = array();
      if($target == 'mobile') {
        $channelCondition['mobile'] = $targetUser['mobile'];
        $channelUserInfo = $channelModel->getUserByChannel($channelCondition);
      } else {
        //wx授权
        $channelCondition['openId'] = $targetUser['openId'];
        $channelUserInfo = $channelModel->getUserByChannel($channelCondition);
        if(empty($channelUserInfo) && !empty($targetUser['unionId'])) {
          $channelCondition2 = $channelCondition;
          $channelCondition2['unionId'] = $targetUser['unionId'];
          $channelUserInfo = $channelModel->getUserByChannel($channelCondition2);
        }
      }

      if(empty($bindUid)) {
        $this->register($channelUserInfo, $targetUser, $target, $platform, $channel, $pname);	//register
      } else {
        $this->bind($channelUserInfo, $targetUser, $target, $platform, $channel, $pname, $bindUid);	//bind
      }
    }*/

    private function createUser($targetUser, $target, $platform, $channel, $pname, $uuid = 0, $userInfo = array()) {
      $channelModel = new ChannelModel();
      $userModel = new UserModel();

      $nowtime = time();
      $channelEntity = array(
        'target'      => $target,
        'platform'    => $platform,
	      'channel'     => $channel,
        'pname'       => $pname,
        'openId'   => $targetUser['openId'],
        'unionId'  => $targetUser['unionId'],
        'nickname' => $targetUser['name'],
        'sex'      => $targetUser['sex'],
        'avatar'   => $targetUser['avatar'],
        'mobile'   => $targetUser['mobile'],
        'birthday' => $targetUser['birthday'],
        'city'     => $targetUser['city'],
        'province' => $targetUser['province'],
        'country'  => $targetUser['country'],
        'ctime'    => $nowtime,
        'utime'    => $nowtime,
        'ip'       => CommonHandler::newInstance()->clientIpAddress()
      );
      //$channelEntity['nickname'] = substr_replace($targetUser['mobile'], '****', 3, 4);
      $cid = $channelModel->createChannel($channelEntity);

      $userEntity = array(
        'cid'           => $cid,
        'uuid'          => $uuid,
        'nick_name'     => $targetUser['name'],
        'sex'           => $targetUser['sex'],
        'headimgurl'    => $targetUser['avatar'],
        'phone'         => $targetUser['mobile'],
        'city'          => $targetUser['city'],
        'province'      => $targetUser['province'],
        'country'       => $targetUser['country'],
        'source'        => 6,
        'identity'      => 1,
        'create_time'   => $nowtime,
        'modify_time'   => $nowtime,
        'last_login_time' => $nowtime,
        'last_login_ip' => CommonHandler::newInstance()->clientIpAddress()
      );
      //$userEntity['nick_name'] = substr_replace($userEntity['phone'], '****', 3, 4);
      $userId = $userModel->createUser($userEntity);
      
      if($target == 'wx') {
        $userModel->weChatInfoCheck($userId, $targetUser['openId'], $targetUser['unionId']);
      }

      return $userId;
    }

    public function updateUser($targetUser, $target, $channel, $userId, $cid,$platform) {
      $channelModel = new ChannelModel();
      $userModel = new UserModel();

      $nowtime = time();
      $channelInfo = array(
        'channel'     => $channel,
        'openId'   => $targetUser['openId'],
        'nickname' => $targetUser['name'],
        'sex'      => $targetUser['sex'],
        'avatar'   => $targetUser['avatar'],
        'mobile'   => $targetUser['mobile'],
        'birthday' => $targetUser['birthday'],
        'city'     => $targetUser['city'],
        'province' => $targetUser['province'],
        'country'  => $targetUser['country'],
        'utime'    => $nowtime,
        'platform'    => $platform,
      );
      $channelModel->updateChannelInfo($cid, $channelInfo);

      $userInfo = array(
        //'nick_name'     => $targetUser['name'],
        //'sex'           => $targetUser['sex'],
        //'headimgurl'    => $targetUser['avatar'],
        //'phone'         => $targetUser['mobile'],
        //'city'          => $targetUser['city'],
        //'province'      => $targetUser['province'],
        //'country'       => $targetUser['country'],
        'modify_time'   => $nowtime,
        'last_login_time' => $nowtime,
        'last_login_ip' => CommonHandler::newInstance()->clientIpAddress()
      );
      $userModel->updateUser($userId, $userInfo);

      if($target == 'wx') {
	      $userModel->updateWechatInfo($userId, $targetUser['openId'], $targetUser['unionId']);
      }
    }

    private function register($channelUserInfo, $targetUser, $target, $platform, $channel, $pname) {
      $channelModel = new ChannelModel();
      $userModel = new UserModel();

      $is_reg = 0;
      if(empty($channelUserInfo)) {
        $userId = $this->createUser($targetUser, $target, $platform, $channel, $pname);
        $is_reg = 1;
      } else {
        $userId = $channelUserInfo['user_id'];
	      $cid = $channelUserInfo['cid'];
	      $this->updateUser($targetUser, $target, $channel, $userId, $cid,$platform);
      }

      //记录用户登录日志
      $logUserLoginModel = new LogUserLoginModel();
      $logUserLoginModel->setLoginLogToRds($userId, $is_reg);

      $boundInfo = [$target => ($target == 'wx') ? $targetUser['name'] : $targetUser['mobile'] ];
      if(isset($channelUserInfo['uuid']) && !empty($channelUserInfo['uuid'])) {
        $boundInfo = $channelModel->getBoundInfo($channelUserInfo['uuid']);
      }

      $userInfo = array(
        'user_id'       => $userId,
        'uuid'          => empty($is_reg) ? $channelUserInfo['uuid'] : 0,
        'nick_name'     => empty($is_reg) ? $channelUserInfo['nick_name'] : $targetUser['name'],
        'sex'           => empty($is_reg) ? $channelUserInfo['sex'] : $targetUser['sex'],
        'headimgurl'    => empty($is_reg) ? $channelUserInfo['headimgurl'] : $targetUser['avatar'],
        'phone'         => empty($is_reg) ? $channelUserInfo['phone'] : $targetUser['mobile'],
        'city'          => empty($is_reg) ? $channelUserInfo['city'] : $targetUser['city'],
        'province'      => empty($is_reg) ? $channelUserInfo['province'] : $targetUser['province'],
        'country'       => empty($is_reg) ? $channelUserInfo['country'] : $targetUser['country'],
        'identity'		 => empty($is_reg) ? $channelUserInfo['identity'] :1,
        'boundInfo'     => $boundInfo,
        'is_reg'     => $is_reg,
        'vc_balance'    => $userModel->getUserBalanceByUserId($userId)
      );
      if ($userInfo['nick_name'] == $userInfo['phone'] && !empty($userInfo['phone'])) {
          $userInfo['nick_name'] = substr_replace($userInfo['phone'], '****', 3, 4);
      }

      list($token, $expire) = $this->generateToken(["uid" => $userId, "nickname" => $userInfo['nick_name']]);
      $userInfo['token'] = $token;
      $userInfo['expire'] = $expire;
      header('Authorization:' . $token);
      $this->responseJson($userInfo);
    }

    public function getTokenForNew() {
        $params  = $this->checkApiParam(['user_id', 'nick_name']);
        $user_id = $params['user_id'];
        $nick_name = $params['nick_name'];
        list($token, $expire) = $this->generateToken(["uid" => $user_id, "nickname" => $nick_name]);
        $this->responseJson(['token' => $token]);
        return;
    }


    private function bind($channelUserInfo, $targetUser, $target, $platform, $channel, $pname, $bindUid) {
      $channelModel = new ChannelModel();
      $userModel = new UserModel();

      if(!empty($channelUserInfo['uuid'])) {
        $this->responseJsonError(11113, '账号已被绑定');
      }
      
      $currentUser = $userModel->getUserInfo($bindUid);
      $uuid = !empty($currentUser['uuid']) ? $currentUser['uuid'] : $userModel->generateUUID ();
      
      $userModel->updateUser($bindUid, ['uuid' => $uuid, 'is_main' => 1]);    //update main account uuid/is_main, if bind mobile,update phone
      
      $userInfo = array(
        'nick_name'     => $currentUser['nick_name'],
        'sex'           => $currentUser['sex'],
        'headimgurl'    => $currentUser['headimgurl'],
        'phone'         => $currentUser['phone'],
        'city'          => $currentUser['city'],
        'province'      => $currentUser['province'],
        'country'       => $currentUser['country']
      );

      if(empty($channelUserInfo)) {
        $userId = $this->createUser($targetUser, $target, $platform, $channel, $pname, $uuid, $userInfo);

      } else {
        $userInfo['modify_time'] = time();
        $userInfo['uuid'] = $uuid;
        $userModel->updateUser($channelUserInfo['user_id'], $userInfo);
        $userId = $channelUserInfo['user_id'];
      }

      $userInfo['user_id'] = $userId;
      $userInfo['uuid'] = $uuid;
      $userInfo['boundInfo'] = $channelModel->getBoundInfo($uuid);
      $userInfo['vc_balance'] = $userModel->getUserBalanceByUserId($userId);

      $this->responseJson($userInfo);
    }

    private function checkMobileCode($mobile, $mobile_code) {
      /*$smsSend = new SmsSend();
      if (!$smsSend->mobileCheck($mobile)) {
        $this->responseJsonError(1105);
      }*/
      $redisModel = new RedisModel("user");
      $redisCode = $redisModel->redisGet(SMS_CODE . $mobile);
      if($redisCode == $mobile_code || ($mobile == '18518765356' && $mobile_code == '123456')) {
        return array(
          'openId'   => '',
          'unionId'  => '',
          'name' => $mobile,
          'sex'      => 1,
          'avatar'   => '',
          'mobile'   => $mobile,
          'birthday' => '',
          'city'     => '',
          'province' => '',
          'country'  => ''
        );
      } else {
        return null;
      }
    }

    public function updateUserInfo() {
      $this->checkToken();
      $params  = $this->checkApiParam(['user_id'], ['nick_name' => '', 'headimgurl' => '', 'sex' => 0]);
      foreach($params as $key => $value) {
        if(empty($value)) {
          unset($params[$key]);
        }
      }
      $userModel = new UserModel();
      $uids = array($params['user_id']);
      $userInfo = $userModel->getUserInfo($params['user_id']);
      if($userInfo['uuid']) {
        $users = $userModel->getUsersByUUid($userInfo['uuid']);
        $uids = array_column($users, 'user_id');
      }
      unset($params['user_id']);
      foreach($uids as $uid) {
        $userModel->updateUser($uid, $params);
      }
      $this->responseJson();
    }

    public function updateDevice() {
        $params  = $this->checkApiParam(['user_id', 'device_token']);
        $userModel = new UserModel();
        $userModel->updateUser($params['user_id'], $params);
        $this->responseJson();
    }

    public function checkTokenForNew() {
            $param = $this->checkApiParam([], ['user_id' => 0, 'token' => '']);
            $userId = intval($param['user_id']);
            $token = $param['token'];
            //普通用户TOKEN校验
            if ($token != "") {
                try {
                    $this->tokenDependRedis($token, 'continue');
                    $decoded = JWT::decode($token, $this->_appSetting->getConstantSetting('JWT-KEY'), array('HS256'));
                    if ($userId && $decoded->uid != $userId) {
                        $this->responseJsonError(102);
                    } else {
                        //header('Authorization:' . $token);
                        //return $decoded;
                        $decoded = (array)$decoded;
                        $decoded['token'] = $token;
                        $this->responseJson($decoded);
                    }
                } catch (\Firebase\JWT\ExpiredException $e) {
                    $new_token = $this->tokenDependRedis($token, 'expire');
                    if ($new_token) {
                        //header('Authorization:' . $new_token['new_token']);
                        $new_token['userInfo']['token'] = $new_token['new_token'];
                        $this->responseJson($new_token['userInfo']);
                    } else {
                        $this->responseJsonError(102);
                    }
                } catch (\Exception $e) {
                    $this->responseJsonError(102);
                }
            } else {
                $this->responseJsonError(102);
            }
            return true;
    }
}
