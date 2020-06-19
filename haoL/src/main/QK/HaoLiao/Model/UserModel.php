<?php
/**
 * User: WangHui
 * Date: 2018/9/29
 * Time: 9:50
 */

namespace QK\HaoLiao\Model;

use QK\HaoLiao\Common\BaiDuParams;
use QK\HaoLiao\Common\CommonHandler;
use QK\HaoLiao\Common\WeChatParams;
use QK\HaoLiao\DAL\DALLogUserLogin;
use QK\HaoLiao\DAL\DALUser;
use QK\HaoLiao\DAL\DALUserExtra;
use QK\HaoLiao\DAL\DALUserSubSidiaryBaiDu;
use QK\HaoLiao\DAL\DALUserSubSidiaryBaiDuSmallRoutine;
use QK\HaoLiao\DAL\DALUserSubSidiaryWeChat;
use QK\HaoLiao\DAL\DALUserBalance;
use QK\WeChat\WeChatLogin;
use QK\HaoLiao\DAL\DALUserVcRecord;
use QK\HaoLiao\Model\ChannelModel;
use QK\HaoLiao\Model\OrderModel;

class UserModel extends BaseModel {
    protected $_weChatModifyTime;
    protected $_dalUser;
    protected $_dalUserSubSidiaryWeChat;
    protected $_dalLogUserLogin;
    protected $_dalUserSubSidiaryBaiDu;
    protected $_dalUserSubSidiaryBaiDuSmallRoutine;

    public function __construct() {
        parent::__construct();
        $this->_dalUser = new DALUser($this->_appSetting);
        $this->_dalUserSubSidiaryWeChat = new DALUserSubSidiaryWeChat($this->_appSetting);
        $this->_dalUserSubSidiaryBaiDu = new DALUserSubSidiaryBaiDu($this->_appSetting);
        $this->_dalUserSubSidiaryBaiDuSmallRoutine = new DALUserSubSidiaryBaiDuSmallRoutine($this->_appSetting);
        $this->_dalLogUserLogin = new DALLogUserLogin($this->_appSetting);
        $this->_weChatModifyTime = time() - 3600 * 48;

    }

    public function weChatLogin($params) {
        //获取微信参数
        $weChatParamsController = new WeChatParams();
        //$weChatParams = $weChatParamsController->getWeChatParams();
        $weChatParams = $weChatParamsController->getNewWeChatParams();
        $appId = $weChatParams['id'];
        $appKey = $weChatParams['appKey'];

        //获取游戏参数
        $gameParams = $weChatParamsController->loginType();
        $gameType = $gameParams['type'];
        $userLoginInfo = [];
        switch ($gameType) {
            case 1:
                //小程序微信登录处理
                $code = $params['code'];
                $enCryTedData = $params['enCryTedData'];
                $iv = $params['iv'];
                $weChat = new WeChatLogin($appId, $appKey);
                $weChatInfo = $weChat->Login($code, 1, $enCryTedData, $iv);
                if ($weChatInfo['code'] != 1) {
                    return false;
                }
                $weChatUserInfo = json_decode($weChatInfo['data'], true);
                $userInfo['openid'] = $weChatUserInfo['openId'];
                $userInfo['unionid'] = $weChatUserInfo['unionId'];
                $userInfo['nickname'] = $weChatUserInfo['nickName'];
                $userInfo['sex'] = $weChatUserInfo['gender'];
                $userInfo['city'] = $weChatUserInfo['city'];
                $userInfo['country'] = $weChatUserInfo['country'];
                $userInfo['province'] = $weChatUserInfo['province'];
                $userInfo['avatarurl'] = $weChatUserInfo['avatarUrl'];

                $userLoginInfo = $this->userLogin($userInfo);

                break;
            case 2:
                //公众号微信登录处理
                $code = $params['code'];
                //分销商ID
                $distId = $params['dist_id'];
                $WeChatWeb = new WeChatLogin($appId, $appKey);

                $wechatInfo = $WeChatWeb->Login($code, 2);

                if ($wechatInfo['code'] != 1) {
                    return false;
                }

                $weChatUserInfo = $wechatInfo['data'];
                $userInfo = [];
                $userInfo['openid'] = $weChatUserInfo['openid'];
                $userInfo['unionid'] = $weChatUserInfo['unionid'];
                $userInfo['nickname'] = $weChatUserInfo['nickname'];
                $userInfo['sex'] = $weChatUserInfo['sex'];
                $userInfo['city'] = $weChatUserInfo['city'];
                $userInfo['country'] = $weChatUserInfo['country'];
                $userInfo['province'] = $weChatUserInfo['province'];
                $userInfo['avatarurl'] = $weChatUserInfo['headimgurl'];
                $userInfo['dist_id'] = $distId;
                $userLoginInfo = $this->userLogin($userInfo);
                break;
        }

        return $userLoginInfo ? $userLoginInfo : false;
    }

    /**
     * 百度账号授权登录
     * @param $params
     * @param $redirect
     * @return bool|mixed
     */
    public function baiDuLogin($params, $redirect) {
        //获取微信参数
        $baiDuParamsController = new BaiDuParams();
        $baiDuParams = $baiDuParamsController->getBaiDuParams();
        //百度账号登录处理
        $code = $params['code'];

        //获取百度accessToken
        $commonHandler = new CommonHandler();
        $postParams = [];
        $postParams['grant_type'] = 'authorization_code';
        $postParams['code'] = $code;
        $postParams['client_id'] = $baiDuParams['Api-Key'];
        $postParams['client_secret'] = $baiDuParams['Secret-Key'];
        $api_url = $this->_appSetting->getConstantSetting("DOMAIN_API");
        $postParams['redirect_uri'] = $api_url . "index.php?c=login&do=baiduLogin&v=1&p=user&weChatId=" . $GLOBALS['weChatId'] . "&redirect=" . $redirect;
        $baiDuTokenJson = $commonHandler->httpPost('https://openapi.baidu.com/oauth/2.0/token', $postParams);
        $baiDuTokenArr = json_decode($baiDuTokenJson, true);
        $baiDuToken = $baiDuTokenArr['access_token'];

        $baiDuUserInfoJson = $commonHandler->httpPost('https://openapi.baidu.com/rest/2.0/passport/users/getInfo', ['access_token' => $baiDuToken]);
        $baiDuUserInfo = json_decode($baiDuUserInfoJson, true);

        if(isset($baiDuUserInfo['error_code'])){
            return false;
        }

        $baidu_userid = $baiDuUserInfo['userid'];
        $is_reg = 0;

        //主库所需用户信息
        $regUser = [];

        if (get_magic_quotes_gpc()) {
            $regUser['nick_name'] = $baiDuUserInfo['username'];
        } else {
            $regUser['nick_name'] = addslashes($baiDuUserInfo['username']);
        }
        $regUser['sex'] = $baiDuUserInfo['sex'] ? 1 : 2;
        $regUser['headimgurl'] = "http://tb.himg.baidu.com/sys/portrait/item/" . $baiDuUserInfo['portrait'];
        $regUser['modify_time'] = time();

        $uid = $this->_dalUserSubSidiaryBaiDu->getUserIdByBaiDuUserId($baidu_userid);
        if ($uid) {
            //登录  获取用户信息
            $user_info = $this->getUserInfo($uid);
//            if (!empty($user_info) && $user_info['modify_time'] < $this->_weChatModifyTime) {
            if (!empty($user_info)) {
                $regUser['last_login_time'] = $regUser['modify_time'] = time();
                $regUser['last_login_ip'] = CommonHandler::newInstance()->clientIpAddress();
                //48小时更新修改用户主库信息
                $this->updateUser($uid, $regUser);
            }
        } else {
            //注册新用户
            $regUser['source'] = 4;
            $regUser['create_time'] = time();
            $regUser['last_login_time'] = $regUser['modify_time'] = time();
            $regUser['last_login_ip'] = CommonHandler::newInstance()->clientIpAddress();
            $regUser['identity'] = 1;
            $this->_dalUser->newUser($regUser);
            $uid = $this->_dalUser->getInsertId();
            //用户附属表
            $dalUserExtra = new DALUserExtra($this->_appSetting);
            $regUserExtra['user_id'] = $uid;
            $dalUserExtra->newUserExtra($regUserExtra);
            $is_reg = 1;
            //百度账户绑定表
            $baiDuUser = [];
            $baiDuUser['user_id'] = $uid;
            $baiDuUser['baidu_userid'] = $baidu_userid;
            $baiDuUser['realname'] = $baiDuUserInfo['realname'];
            $baiDuUser['create_time'] = $baiDuUser['modify_time'] = $baiDuUser['last_login_time'] = time();
            $baiDuUser['wechat_id'] = $GLOBALS['weChatId'];
            $this->_dalUserSubSidiaryBaiDu->newBaiDuAccount($baiDuUser);
        }

        //记录用户登录日志
        $logUserLoginModel = new LogUserLoginModel();
        $logUserLoginModel->setLoginLogToRds($uid, $is_reg);

        $data['uid'] = $uid;
        $data['firstLogin'] = $is_reg;
        return $data;
    }



    /**
     * 百度小程序账号授权登录
     * @param $baiDuUserInfo
     * @return bool|mixed
     */
    public function baiDuSmallRoutineLogin($baiDuUserInfo) {
        if(isset($baiDuUserInfo['error_code'])){
            return false;
        }

        $openId = $baiDuUserInfo['openid'];
        $is_reg = 0;

        //主库所需用户信息
        $regUser = [];

        if (get_magic_quotes_gpc()) {
            $regUser['nick_name'] = $baiDuUserInfo['nickname'];
        } else {
            $regUser['nick_name'] = addslashes($baiDuUserInfo['nickname']);
        }
        $regUser['sex'] = $baiDuUserInfo['sex'];
        $regUser['headimgurl'] = $baiDuUserInfo['headimgurl'];
        $regUser['modify_time'] = time();

        $uid = $this->_dalUserSubSidiaryBaiDuSmallRoutine->getUserIdByBaiDuOpenId($openId);
        if ($uid) {
            //登录  获取用户信息
            $user_info = $this->getUserInfo($uid);
            if (!empty($user_info)) {
                $regUser['last_login_time'] = $regUser['modify_time'] = time();
                $regUser['last_login_ip'] = CommonHandler::newInstance()->clientIpAddress();
                //48小时更新修改用户主库信息
                $this->updateUser($uid, $regUser);
            }
        } else {
            //注册新用户
            $regUser['source'] = 5;
            $regUser['create_time'] = time();
            $regUser['last_login_time'] = $regUser['modify_time'] = time();
            $regUser['last_login_ip'] = CommonHandler::newInstance()->clientIpAddress();
            $regUser['identity'] = 1;
            $this->_dalUser->newUser($regUser);
            $uid = $this->_dalUser->getInsertId();
            //用户附属表
            $dalUserExtra = new DALUserExtra($this->_appSetting);
            $regUserExtra['user_id'] = $uid;
            $dalUserExtra->newUserExtra($regUserExtra);
            $is_reg = 1;
            //百度账户绑定表
            $baiDuUser = [];
            $baiDuUser['user_id'] = $uid;
            $baiDuUser['openid'] = $openId;
            $baiDuUser['create_time'] = $baiDuUser['modify_time'] = $baiDuUser['last_login_time'] = time();
            $baiDuUser['wechat_id'] = $GLOBALS['weChatId'];
            $this->_dalUserSubSidiaryBaiDuSmallRoutine->newBaiDuAccount($baiDuUser);
        }

        //记录用户登录日志
        $logUserLoginModel = new LogUserLoginModel();
        $logUserLoginModel->setLoginLogToRds($uid, $is_reg);

        $data['uid'] = $uid;
        $data['firstLogin'] = $is_reg;
        return $data;
    }

    /**
     * 用户登录操作，没有则注册
     * @param $userInfo
     * @return bool|mixed
     */
    private function userLogin($userInfo) {
        $openid = $userInfo['openid'];
        $unionId = $userInfo['unionid'];
        $is_reg = 0;

        //主库所需用户信息
        $regUser = [];

        if (get_magic_quotes_gpc()) {
            $regUser['nick_name'] = $userInfo['nickname'];
            $regUser['city'] = $userInfo['city'];
            $regUser['country'] = $userInfo['country'];
            $regUser['province'] = $userInfo['province'];
        } else {
            //$regUser['nick_name'] = addslashes($userInfo['nickname']);
            $regUser['nick_name'] = $userInfo['nickname'];
            $regUser['city'] = addslashes($userInfo['city']);
            $regUser['country'] = addslashes($userInfo['country']);
            $regUser['province'] = addslashes($userInfo['province']);
        }
        $regUser['sex'] = $userInfo['sex'];
        $regUser['headimgurl'] = $userInfo['avatarurl'];
        $regUser['modify_time'] = time();

        $uid = $this->_dalUserSubSidiaryWeChat->getUserIdByUnionId($unionId);
        if ($uid) {
            //登录  获取用户信息
            $user_info = $this->getUserInfo($uid);
//            if (!empty($user_info) && $user_info['modify_time'] < $this->_weChatModifyTime) {
            if (!empty($user_info)) {
                $regUser['last_login_time'] = $regUser['modify_time'] = time();
                $regUser['last_login_ip'] = CommonHandler::newInstance()->clientIpAddress();
                //48小时更新修改用户主库信息
                $this->updateUser($uid, $regUser);
            }
        } else {
            //注册新用户
            $regUser['dist_id'] = $userInfo['dist_id'];
            $regUser['create_time'] = time();
            $regUser['last_login_time'] = $regUser['modify_time'] = time();
            $regUser['last_login_ip'] = CommonHandler::newInstance()->clientIpAddress();
            $regUser['identity'] = 1;
            $this->_dalUser->newUser($regUser);
            $dalUserExtra = new DALUserExtra($this->_appSetting);

            $uid = $this->_dalUser->getInsertId();

            $regUserExtra['user_id'] = $uid;
            $dalUserExtra->newUserExtra($regUserExtra);
            $is_reg = 1;

            if($userInfo['dist_id']){
                //增加分销商邀请人数
                $distIncOrDec['gain_user'] = "+1";
                $distExtraModel = new DistExtraModel();
                $distExtraModel->setDistExtraIncOrDec($userInfo['dist_id'], $distIncOrDec);
            }

        }
        //微信信息表数据检查
        $this->weChatInfoCheck($uid, $openid, $unionId);

        //记录用户登录日志
        $logUserLoginModel = new LogUserLoginModel();
        $logUserLoginModel->setLoginLogToRds($uid, $is_reg);

        $data['uid'] = $uid;
        $data['firstLogin'] = $is_reg;
        return $data;
    }

    /**
     * 获取用户列表
     * @param $where
     * @param $page
     * @param $pagesize
     * @return mixed
     */
    public function getUserList($where, $page, $pagesize,$order=[]){
        $start = ($page - 1) * $pagesize;
       // $res['total'] = $this->_dalUser->getUserTotal($where);
       // $userList = $this->_dalUser->getUserList($where, $start, $pagesize);
        $res['total'] = $this->_dalUser->getUserTotalV2($where);
        $userList = $this->_dalUser->getUserListV2($where, $start, $pagesize,$order);
		$channelModel=new ChannelModel();
        $orderModel = new OrderModel();
        if(!empty($userList)){
            foreach($userList as $key => $val){
                $val['pay_amount'] = 0;
                $val['subscribe_num'] = 0;
                $val['follow_num'] = 0;
				$val['platform']='';
                $val['channel']='';
                $userExtraInfo = $this->getUserExtraInfo($val['user_id']);
				 //查询渠道信息
                if($val['cid']){
                    $channelInfo=$channelModel->getChannel($val['cid']);
                    $val['platform']=$channelInfo['platform'];
                    $val['channel']=$channelInfo['channel'];
                }
                //$val['pay_amount'] = $orderModel->getUserPayAmount($val['user_id']);
                //$val['pay_amount'] = $this->ncPriceFen2Yuan($val['pay_amount']);
                if(!empty($userExtraInfo)){
//                    $userExtraInfo['pay_amount'] = $this->ncPriceFen2Yuan($userExtraInfo['pay_amount']);
                    //unset($userExtraInfo['pay_amount']);
                    $userInfo = array_merge($val, $userExtraInfo);
                } else {
                    $userInfo = $val;
                }
                $userInfo['vc_balance'] = $this->getUserBalanceByUserId($val['user_id']);
                $userInfo['headimgurl']=$userInfo['headimgurl']?$userInfo['headimgurl']:'https://hl-static.haoliao188.com/resource/202002/27/ca2ed48d3859896ff049903652c287db.png';

                $userList[$key] = $userInfo;
            }
        }
        $res['list'] = $userList;
        return $res;
    }

    /**
     * 获取用户主表信息
     * @param $userId
     * @return mixed
     */
    public function getUserInfo($userId) {
        $redisModel = new RedisModel('user');
        //获取用户主库信息
        $redisKey = USER_INFO . $userId;
        $user_info_json = $redisModel->redisGet($redisKey, true);
        if (!empty($user_info_json)) {
            $user_info = $user_info_json;
        } else {
            $user_info = $this->_dalUser->getUserInfo($userId);
            $redisModel->redisSet($redisKey, $user_info);
        }
        return $user_info;
    }

    /**
     * 根据手机号获取用户信息
     * @param $phone
     * @return mixed
     */
    public function getUserInfoByPhone($phone){
        return $this->_dalUser->getUserInfoByPhone($phone);
    }

    /**
     * 获取用户扩展信息
     * @param $userId
     * @return bool|mixed|null|string
     */
    public function getUserExtraInfo($userId){
        $redisModel = new RedisModel('user');
        //获取用户主库信息
        $redisKey = USER_EXTRA_INFO . $userId;
        $userExtraInfo = $redisModel->redisGet($redisKey, true);
        if (empty($user_info)) {
            $dalUserExtra = new DALUserExtra($this->_appSetting);
            $userExtraInfo = $dalUserExtra->getUserExtraInfo($userId);
            $redisModel->redisSet($redisKey, $userExtraInfo);
        }
        $orderModel = new OrderModel();
        $userExtraInfo['pay_amount'] = $orderModel->getUserPayAmount($userId);
        if(!empty($userExtraInfo)){
            $userExtraInfo['pay_amount'] = $this->ncPriceFen2Yuan($userExtraInfo['pay_amount']);
        }
        return $userExtraInfo;
    }

    /**
     * 修改用户信息
     * @param $uid
     * @param $content
     * @return bool
     */
    public function updateUser($uid, $content) {
        $redisModel = new RedisModel('user');
        $result = $this->_dalUser->updateUser($uid, $content);
        if ($result) {
            $redisKey = USER_INFO . $uid;
            $redisModel->redisDel($redisKey);
            return true;
        } else {
            return false;
        }
    }

    /**
     * 修改用户扩展信息
     * @param $userId
     * @param $params
     * @return int
     */
    public function setUserExtraIncOrDec($userId, $params){
        $dalUserExtra = new DALUserExtra($this->_appSetting);
        return $dalUserExtra->setUserExtraIncOrDec($userId, $params);
    }

    public function weChatInfoCheck($uid, $openid, $unionId) {
        $weChatId = $this->_dalUserSubSidiaryWeChat->accountInfoCheck($unionId, $openid);
        if ($weChatId) {
            $update['last_login_time'] = time();
            $this->_dalUserSubSidiaryWeChat->updateWeChatUserInfo($weChatId, $update);
        } else {
            $weChatInfo['user_id'] = $uid;
            $weChatInfo['wechat_id'] = $GLOBALS['weChatId'];
            $weChatInfo['unionid'] = $unionId;
            $weChatInfo['openid'] = $openid;
            $weChatInfo['create_time'] = time();
            $weChatInfo['modify_time'] = time();
            $weChatInfo['last_login_time'] = time();
            $this->_dalUserSubSidiaryWeChat->newWeChatAccount($weChatInfo);
        }
    }

    public function updateWechatInfo($uid, $openid, $unionId) {
      $updateInfo = array(
        'openid' => $openid,
        'unionid' => $unionId,
        'modify_time' => time(),
        'last_login_time' => time()
      );
      $this->_dalUserSubSidiaryWeChat->updateUserWechatInfo($uid, $updateInfo);
    }

        public function getWechatSubUsers() {
        return $this->_dalUserSubSidiaryWeChat->getWechatSubUsers();
        }

    /**
     * 获取用户微信扩展信息
     * @param $userId
     * @return mixed
     */
    public function getUserWeChatInfo($userId){
        $redisModel = new RedisModel('user');
        $redisKey = USER_WECHAT_INFO . $userId . ':' . $GLOBALS['weChatId'];
        //$userWeChatInfo = $redisModel->redisGet($redisKey, true);
        //if(empty($userWeChatInfo)){
            $userWeChatInfo = $this->_dalUserSubSidiaryWeChat->getUserWeChatInfo($userId);
            //$redisModel->redisSet($redisKey, $userWeChatInfo);
        //}
        return $userWeChatInfo;
    }

    /**
     * 更新用户微信扩展信息
     * @param $userId
     * @return mixed
     */
    public function updateUserWeChatInfoV2($userId, $wechatInfo){
      $redisModel = new RedisModel('user');
      $res = $this->_dalUserSubSidiaryWeChat->updateUserWechatInfoV2($wechatInfo, ['user_id' => $userId]);
      if($res) {
        $redisKey = USER_WECHAT_INFO . $userId . ':' . $GLOBALS['weChatId'];
        $redisModel->redisDel($redisKey);
      }
      return $res;
    }

    /**
     * 获取用户微信信息（后台提现用）
     * @param $userId
     * @return mixed
     */
    public function getUserWithDrawWeChatInfo($userId) {
        return $this->_dalUserSubSidiaryWeChat->getUserWithDrawWeChatInfo($userId);
        
    }


    /**
     * 后台注册新用户（后台）
     * @param $userInfo
     * @return mixed
     */
    public function consoleNewUser($userInfo) {
        if (get_magic_quotes_gpc()) {
            $regUser['nick_name'] = $userInfo['nickname'];
        } else {
            $regUser['nick_name'] = addslashes($userInfo['nickname']);
        }
        $regUser['sex'] = 0;
        $regUser['headimgurl'] = $userInfo['avatarurl'];
        $regUser['modify_time'] = time();
        $regUser['create_time'] = time();
        $regUser['identity'] = 1;
        $this->_dalUser->newUser($regUser);
        $dalUserExtra = new DALUserExtra($this->_appSetting);
        $uid = $this->_dalUser->getInsertId();
        $regUserExtra['user_id'] = $uid;
        $dalUserExtra->newUserExtra($regUserExtra);
        return $uid;
    }

    public function getUserBalanceByUserId($userId, $isYuan = true) {
        $redisModel = new RedisModel('user');
        $redisKey = USER_BALANCE_INFO . $userId;
        $balance = $redisModel->redisGet($redisKey, true);
        if (empty($balance)) {
          $uids = array($userId);
          $userModel = new UserModel();
          $userInfo = $userModel->getUserInfo($userId);
          if($userInfo['uuid']) {
            $users = $userModel->getUsersByUUid($userInfo['uuid']);
            $uids = array_column($users, 'user_id');
          }

          $dalUserBalance = new DALUserBalance($this->_appSetting);
          foreach($uids as $uid) {
            $userBalanceInfo = $dalUserBalance->getUserBalanceInfo($uid);
            if (!empty($userBalanceInfo)) {
              $balance += $userBalanceInfo['vc_balance'];
            }
          }
        }
        return $isYuan ? $this->ncPriceFen2YuanInt($balance) : $balance;
    }

    public function getUserBalanceAllUser($userId, $isYuan = true) {
        $uids = array($userId);
        $userModel = new UserModel();
        $userInfo = $userModel->getUserInfo($userId);
        if($userInfo['uuid']) {
            $users = $userModel->getUsersByUUid($userInfo['uuid']);
            $uids = array_column($users, 'user_id');
        }

        $allBalance = [];

        foreach($uids as $uid) {
            $userBalanceInfo = $this->getUserBalanceOneUser($uid, $isYuan);
            if (!empty($userBalanceInfo)) {
                $allBalance[$uid] = $userBalanceInfo;
            } else {
                $allBalance[$uid] = null;
            }
        }

        return $allBalance;
    }

    public function getUserBalanceOneUser($userId, $isYuan = true) {
        $redisModel = new RedisModel('user');
        $redisKey = USER_BALANCE_INFO . $userId;
        $userBalanceInfo = $redisModel->redisGet($redisKey, true);
        if (empty($userBalanceInfo)) {
            $dalUserBalance = new DALUserBalance($this->_appSetting);
            $userBalanceInfo = $dalUserBalance->getUserBalanceInfo($userId);
        }
        if (empty($userBalanceInfo)) {
            return false;
        }
        if ($isYuan) {
            $userBalanceInfo['vc_balance'] = $this->ncPriceFen2YuanInt($userBalanceInfo['vc_balance']);
        }
        return $userBalanceInfo;
    }

    public function updateUserBalanceByUserId($userId, $data, $insert = 0) {
        $dalUserBalance = new DALUserBalance($this->_appSetting);
        $data['modify_time'] = time();
        if ($insert) {
            $data['user_id'] = $userId;
            return $dalUserBalance->createUserBalance($data);
        } else {
          $redisModel = new RedisModel('user');
          $userModel = new UserModel();
          $userInfo = $userModel->getUserInfo($userId);
          if($userInfo['uuid']) {
            $users = $userModel->getUsersByUUid($userInfo['uuid']);
            $uids = array_column($users, 'user_id');
            foreach($uids as $uid) {
              $redisKey = USER_BALANCE_INFO . $uid;
              $redisModel->redisDel($redisKey);
            }
          }else {
            $redisKey = USER_BALANCE_INFO . $userId;
            $redisModel->redisDel($redisKey);
          }
          return $dalUserBalance->updateUserBalance(['user_id' => $userId], $data);
        }
    }

    public function userVcChange($userId, $type, $amount, $ext_params, $model) {
        if ($type == 1) {
            $userBalance = $this->getUserBalanceOneUser($userId, false);
            $type = 1;
            $symbol = '+';

            $vcRecordId = $this->saveVcRecord($userId, $userBalance, $type, $amount, $symbol, $ext_params, $model);
        } else {
            $checkRes = $this->checkVcBalanceIsEnough($userId, $amount);
            if ($checkRes === false) {
                return false;
            }
            $type = 2;
            $symbol = '-';

            $userBalanceInfo = $this->getUserBalanceAllUser($userId, false);

            $lastAmount = $amount;

            if ($lastAmount < $userBalanceInfo[$userId]['vc_balance']) { // 当前账户余额充足
                $vcRecordId = $this->saveVcRecord($userId, $userBalanceInfo[$userId], $type, $amount, $symbol, $ext_params, $model);
            } else {
                $addUser = [];
                $addUser[$userId] = $userBalanceInfo[$userId];
                unset($userBalanceInfo[$userId]);
                foreach ($userBalanceInfo as $uid => $userBalanceOne) {
                    $addUser[$uid] = $userBalanceOne;
                }

                $index = 0;
                $vcRecordIds = [];
                while ($lastAmount > 0) {
                    $userBalanceOne = current($addUser);
                    $decAmount = min($lastAmount, $userBalanceOne['vc_balance']);

                    if ($index > count($addUser)) {
                        break;
                    }
                    if (!empty($decAmount)) {
                        $vcRecordIds[] = $this->saveVcRecord(key($addUser), $userBalanceOne, $type, $decAmount, $symbol, $ext_params, $model);
                        $lastAmount -= $decAmount;
                    }

                    $index++;
                    next($addUser);
                }

                if ($lastAmount > 0) {
                    return false;
                }
                $vcRecordId = implode(',', $vcRecordIds);
            }
        }

        return ['vcRecordId' => $vcRecordId];
    }

    public function saveVcRecord($userId, $userBalance, $type, $amount, $symbol, $ext_params, $model) {
        // 保存虚拟币变化记录
        $dalUserVcRecord = new DALUserVcRecord($this->_appSetting);

        $userVcData = [];
        $userVcData['user_id'] = $userId;
        $userVcData['vc_before'] = empty($userBalance['vc_balance']) ? 0 : $userBalance['vc_balance'];
        $userVcData['vc_amount'] = $amount;
        $userVcData['vc_after'] = $this->ncPriceCalculate($userBalance['vc_balance'], $symbol, $amount, 0);
        $userVcData['type'] = $type;
        $userVcData['create_time'] = time();
        $userVcData['ext_params'] = $ext_params;
        $userVcData['model'] = $model;
        $vcRecordId = $dalUserVcRecord->createUserVcRecord($userVcData);

        // 修改用户虚拟币余额
        $userBalanceUpData = [];
        $userBalanceUpData['vc_balance'] = $userVcData['vc_after'];
        $this->updateUserBalanceByUserId($userId, $userBalanceUpData, empty($userBalance));

        return $vcRecordId;

    }

    public function checkVcBalanceIsEnough($userId, $amount) {
        $userBalance = $this->getUserBalanceByUserId($userId, false);
        if ($userBalance < $amount) {
            return false;
        }
        return true;
    }

    public function getVcRecordByExt($ext_param) {
        $dalVcRecord = new DALUserVcRecord($this->_appSetting);
        return $dalVcRecord->getVcRecordByExt($ext_param);
    }

    public function createUser($params) {
      $uid = $this->_dalUser->createUser($params);

      $dalUserExtra = new DALUserExtra($this->_appSetting);
      $regUserExtra['user_id'] = $uid;
      $dalUserExtra->newUserExtra($regUserExtra);

      //$this->weChatInfoCheck($uid, $openid, $unionId);
      return $uid;
    }

    public function generateUUID () {
      list($msec, $sec) = explode(" ",microtime());
      return $sec.strval($msec*1000000);
    }

    public function getUsersByUUid($uuid) {
      $users = $this->_dalUser->getUsersByUUid($uuid);
      return $users;
    }

    public function getUserByWechat($openId) {
      $condition = array('openid' => $openId);
      $fields = array('user_id');
      return $this->_dalUserSubSidiaryWeChat->getUserByWechat($condition, $fields);
    }

    public function getAllUserDevice($condition = [],$platform='') {
        if ($condition['user_ids']) {
            $user_ids = $condition['user_ids'];
            unset($condition['user_ids']);
            $condition['user_id'] = [' in (' , $user_ids . ')'];
        }
        return $this->_dalUser->getUserDevice($condition,$platform);
    }

    //获取未登录的用户的device_token
    public function getNoLogin($day,$platform='') {
        $key_time = time() - $day * 86400;
        //$condition = ['last_login_time' => [' is not null and last_login_time < ', $key_time]];
        $condition = ['u.last_login_time' => [' is not null and u.last_login_time < ', $key_time]];
        if($platform){
            $condition['c.platform'] = $platform;
        }
        return $this->_dalUser->getUserDeviceAll($condition,$platform);
        //return $this->_dalUser->getUserDevice($condition,$platform);
    }

    public function getDeviceByPlatform($platform = 'ios') {
        return $this->_dalUser->getUserDevice([], $platform);
    }

    //获取付费用户的device_token
    public function getPayingUser($platform='') {
        return $this->_dalUser->getPayingUser($platform);
    }

    public function checkDevice($device_number, $user_id = 0) {
        if (empty($device_number)) {
            return ['id' => 0, 'status' => 1];
        }
        $condition = ['device_number' => $device_number];
        $device_info = $this->_dalUser->getDeviceByCondition($condition);
        if ($device_info) {
            return ['id' => $device_info['id'], 'status' => $device_info['status']];
        }
        $condition['user_id'] = $user_id;
        $id = $this->_dalUser->addDevice($condition);
        return ['id' => $id, 'status' => 0];
    }

    public function checkIdfa($idfa) {
        $condition = ['device_number' => $idfa];
        $device_info = $this->_dalUser->getDeviceByCondition($condition);
        if ($device_info) {
            return 'exists';
        }
        return true;
    }

    public function consumeDevice($device_number, $user_id) {
        if  (!$device_number || !$user_id) {
            return false;
        }
        $condition = ['device_number' => $device_number];
        $device_info = $this->_dalUser->getDeviceByCondition($condition);
        if ($device_info['status']) {
            return true;
        }
        $data = ['status' => 1, 'user_id' => $user_id];
        return $this->_dalUser->updateDeviceByCondition($condition, $data);
    }
}
