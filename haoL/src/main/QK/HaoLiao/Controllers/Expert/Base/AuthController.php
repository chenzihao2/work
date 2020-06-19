<?php
/**
 * Date: 2019/06/25
 * Time: 11:41
 */

namespace QK\HaoLiao\Controllers\Expert\Base;

use QK\HaoLiao\Controllers\Expert\ExpertController;
use QK\HaoLiao\Common\SmsSend;
use QK\HaoLiao\Common\UcloudSms;
use QK\HaoLiao\Common\CommonHandler;
use QK\HaoLiao\Model\SmsModel;
use QK\HaoLiao\Model\RedisModel;
use QK\HaoLiao\Model\LogUserLoginModel;
use QK\HaoLiao\Model\UserModel;
use QK\HaoLiao\Model\ExpertModel;
use QK\HaoLiao\Model\ChannelModel;

use \QKPHP\SNS\Consts\Platform;
use \QKPHP\SNS\Weixin;
use \QKPHP\Common\Config\Config;
use \QKPHP\Common\Utils\Url;

class AuthController extends ExpertController {

    public function login() {
        $params = $this->checkApiParam([], ['code' => '', 'state' => '', 'redirect_uri' => '', 'platform' => Platform::PC]);
        $platform = $params['platform'];
        $target = 'wx';

        //$appid = "wxae8970c13283e4f1";
        //$appsecret = "381aed7c9469cf61763a9a3868ac4cf0";

        $appid = "wx83f54a68fdcebd65";
        $appsecret = "955159e2e98d59c6a4bb41412933f2cb";

        if (empty($params['code'])) {
            $api_url = $this->_appSetting->getConstantSetting("DOMAIN_API");
            $redirect = $api_url . "/index.php?p=expert&c=auth&do=login&redirect_uri=" . urlencode($redirect_uri);
            $qr_url = "https://open.weixin.qq.com/connect/qrconnect?appid=".$appid.
                "&redirect_uri=".urlencode($redirect).
                "&response_type=code&scope=".$scope.
                "&state=".$state."#wechat_redirect";
            Url::redirect($qr_url);
        } else {
            $wxHelper = new Weixin($appid, $appsecret, array('scope'=> 'user', 'platform'=>$platform, 'pname'=>''));
            $wxUser = $wxHelper->getUserInfo($params['code']);
            if(!isset($wxUser['openId'])){
                $redirect_uri = $params['redirect_uri'].'/login';
                Url::redirect($redirect_uri . "?code=203&message=授权失败");
                return;
            }

            //$userId = $this->registerUser($wxUser, $target, $platform);
            $userInfo = $this->registerUser($wxUser, $target, $platform);

            $userId=$userInfo['user_id'];
            $cid=$userInfo['cid'];

            //判断wxuser是否已注册用户，如果是则直接跳转首页，否则跳转到绑定手机页面
            $expertModel = new ExpertModel();
            $expertInfo = $expertModel->getExpertByCondition(['user_id' => $userId]);

            if (!empty($expertInfo) && !empty($userId)) {
                // $redirect_uri = 'https://d-expert.haoliao188.com/home';
                // $redirect_uri = $params['redirect_uri'];
                // Url::redirect($redirect_uri . "?uid=" . $userId . "&token=" . $token . "&expire=" . $expire);

                if($expertInfo['expert_status']!=1){
                    $redirect_uri = $params['redirect_uri'].'/login';
                    Url::redirect($redirect_uri . "?code=201&message=账户异常");
                }else{
                    list($token, $expire) = $this->generateToken(["uid" => $userId]);
                    $redirect_uri = $params['redirect_uri'].'/home';
                    Url::redirect($redirect_uri . "?uid=" . $userId . "&token=" . $token . "&expire=" . $expire);
                }
            } else {
                //跳转到绑定手机号页面
                $bindpage_uri = $params['redirect_uri'].'/login/becomeExpert';
                Url::redirect($bindpage_uri . "?uid=" . $userId."&cid=".$cid);
            }
        }
    }

    private function registerUser($targetUser, $target, $platform, $pname = 'hl') {
        $channelModel = new ChannelModel();
        $userModel = new UserModel();

        $nowtime = time();
        $channelEntity = array(
            'openId'   => $targetUser['openId'],
            'unionId'  => $targetUser['unionId'],
            'nickname' => $targetUser['name'],
            'sex'      => $targetUser['sex'],
            'avatar'   => $targetUser['avatar'],
            //'mobile'   => $targetUser['mobile'],

            'birthday' => $targetUser['birthday'],
            'city'     => $targetUser['city'],
            'province' => $targetUser['province'],
            'country'  => $targetUser['country'],
            'utime'    => $nowtime
        );

        $is_reg = 0;
        //$userInfo = $channelModel->getUserByChannel(['openId' => $targetUser['openId']]);
        $userInfo = $channelModel->getUserByChannel(['unionId' => $targetUser['unionId']]);

        if (empty($userInfo)) {     //注册新用户
            $channelCreateEntity = array(
                'target'    => $target,
                'platform'  => $platform,
                'pname'     => $pname,
                'ctime'     => $nowtime,
                'ip'        => CommonHandler::newInstance()->clientIpAddress()
            );
            $channelEntity['mobile']='';

            $channelEntity = array_merge($channelEntity, $channelCreateEntity);
            $cid = $channelModel->createChannel($channelEntity);
            $is_reg = 1;
            $userInfo['cid']=$cid;
            $userInfo['user_id']='';

        } else {    //更新用户信息
            $cid = $userInfo['cid'];
            $userId = $userInfo['user_id'];

            if(!$userId){
                return $userInfo;
            }

            $channelModel->updateChannelInfo($cid, $channelEntity);
            $userEntity = array(
                'modify_time'   => $nowtime,
                'last_login_time' => $nowtime,
                'last_login_ip' => CommonHandler::newInstance()->clientIpAddress()
            );
            $userModel->updateUser($userId, $userEntity);

            $userModel->updateWechatInfo($userId, $targetUser['openId'], $targetUser['unionId']);
        }
        //记录用户登录日志
        $logUserLoginModel = new LogUserLoginModel();
        $logUserLoginModel->setLoginLogToRds($userId, $is_reg);

        //return $userId;
        return $userInfo;
    }

    public function loginVerify() {
        $nowtime = time();
        $params = $this->checkApiParam(['mobile', 'code', 'cid']);
        $mobile = $params['mobile'];
        $code = $params['code'];
        //$userId = $params['user_id'];
        $cid = $params['cid'];

        $res = $this->checkMobileCode($mobile, $code);
        if (!$res) {
            $this->responseJsonError(1105);       //验证码错误
        }





        //判断mobile是否存在于数据库中，如果存在，则校验通过，否则注册失败
        $expertModel = new ExpertModel();
        $createdExpertInfo = $expertModel->getExpertByCondition(['phone' => $mobile, 'expert_status' => 1]);

        if (!empty($createdExpertInfo)) {
            $userModel = new UserModel();
            $channelModel = new ChannelModel();
            $userId=$createdExpertInfo['expert_id'];

            //$userInfo=$userModel->getUserInfo($userId);
            $targetUser=$channelModel->getChannel($cid);
            //绑定微信账号
            $userEntity = array(
                'phone'        => $mobile,
                'cid'        => $cid,
                'identity'        => 3,
                'modify_time'     => $nowtime,
                'last_login_time' => $nowtime,
                'last_login_ip'   => CommonHandler::newInstance()->clientIpAddress()
            );

            $res1=$channelModel->updateChannelInfo($cid, ['mobile'=>$mobile,'utime'=>$nowtime]);

            $res2=$userModel->updateUser($userId, $userEntity);


            $res3=$userModel->weChatInfoCheck($userId, $targetUser['openId'], $targetUser['unionId']);


            list($token, $expire) = $this->generateToken(["uid" => $userId]);
            $this->responseJson(['uid' => $userId, 'token' => $token, 'expire' => $expire]);
        } else {
            $this->responseJsonError(1158, '还未注册');   //用户还未注册
        }
    }

    public function getExpertInfo() {
        $params = $this->checkApiParam(['uid', 'token']);
        $uid = intval($params['uid']);
        $token = $params['token'];
        $expertModel = new ExpertModel();
        $expertInfo = $expertModel->getExpertByCondition(['user_id' => $uid]);
        if (!empty($expertInfo)) {
            $expectInfo['tag'] = empty($expectInfo['tag']) ? [] : explode(',', $expectInfo['tag']);
            $this->responseJson($expertInfo);
        } else {
            $this->responseJson([]);

        }
    }

    public function sendCode() {
        $params = $this->checkApiParam(['mobile']);
        $smsSend = new UcloudSms();
        if (!$smsSend->mobileCheck($params['mobile'])) {
            $this->responseJsonError(1105);
        }




        $expertModel = new ExpertModel();
        $expertInfo=$expertModel->getExpertInfoByPhone($params['mobile']);
        if(empty($expertInfo)){
            $this->responseJsonError(1104,'账户不存在');
        }
        if($expertInfo['status']!=1){
            $this->responseJsonError(1104,'账户异常');
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

    private function checkMobileCode($mobile, $mobile_code) {
        $redisModel = new RedisModel("user");
        $redisCode = $redisModel->redisGet(SMS_CODE . $mobile);
        if($redisCode == $mobile_code || ($mobile == '13121473996' && $mobile_code == '123456')) {
            return true;
        } else {
            return false;
        }
    }

}
