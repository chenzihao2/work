<?php
/**
 * User: YangChao
 * Date: 2018/10/16
 */

namespace QK\HaoLiao\Controllers\User;

use Firebase\JWT\JWT;
use QK\HaoLiao\Controllers\BaseController;
use QK\WSF\Settings\AppSetting;
use QK\HaoLiao\Model\RedisModel;

class UserController extends BaseController {
    private $tokenExpire = 3600 * 24;
    //private $tokenExpire = 300;

    public function __construct(AppSetting $appSetting) {
        parent::__construct($appSetting);
        $this->initChannel();
//        $this->checkToken();
    }

    /**
     * 定义请求来源
     */
    public function initChannel() {
        $param = $this->checkApiParam([], ['from' => 'common', 'from_sub' => '', 'weChatId' => $this->_appSetting->getConstantSetting('DEFAULT_WECHATID'), 'display' => 2]);
        $GLOBALS['weChatId'] = isset($param['weChatId']) ? intval($param['weChatId']) : $this->_appSetting->getConstantSetting('DEFAULT_WECHATID');
        $GLOBALS['display'] = isset($param['display']) ? intval($param['display']) : 2;
        $GLOBALS['From'] = trim($param['from']);
        $GLOBALS['FromSub'] = isset($param['from_sub']) ? trim($param['from_sub']) : '';
    }

    public function getAccountUids($userId) {
      //获取与userId相同uuid的uid
      $userModel = new UserModel();
      $userInfo = $userModel->getUserInfo($userId);
      $users = $userModel->getUsersByUUid($userInfo['uuid']);
      return array_column($users, 'user_id');
    }

    public function generateToken($tokenInfo) {
      $tokenInfos = $tokenInfo;
      $tokenInfos['iat'] = time();
      $tokenInfos['exp'] = time() + $this->tokenExpire;
      $key = $this->_appSetting->getConstantSetting('JWT-KEY');
      $token = JWT::encode($tokenInfos, $key);
      $this->tokenDependRedis($token, 'set', $tokenInfo);
      $expire = $tokenInfos['exp'] - 10;
      return array($token, $expire);
    }

    /**
     * 用户Token检查
     * @return bool|object
     */
    public function checkToken() {
        if ($this->_tokenCheck) {
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
                        header('Authorization:' . $token);
                        return $decoded;
                    }
                } catch (\Firebase\JWT\ExpiredException $e) {
                    $new_token = $this->tokenDependRedis($token, 'expire');
                    if ($new_token) {
                        header('Authorization:' . $new_token['new_token']);
                        return $new_token['userInfo'];
                    } else {
                        $this->responseJsonError(102);
                    }
                } catch (\Exception $e) {
                    $this->responseJsonError(102);
                }
            } else {
                $this->responseJsonError(200);
            }
        } else {
            return true;
        }
        return true;
    }

    /**
     * 验证用户平台
     */
    public function checkPlatform(&$info) {
        switch ($GLOBALS['display']) {
            case '2':  // 百度
                if (empty($info['bd_display'])) {
                    return false;
                }
                break;
            default: // 1 微信
                if (empty($info['wx_display'])) {
                    return false;
                }
                break;
        }
        return true;
    }

    public function tokenDependRedis($token, $action = 'set', $tokenInfo = []) {
        $redisModel = new RedisModel('match');
        $expireTime = $this->tokenExpire * 7;
        if ($action == 'set') {
            $redisModel->redisSet($token, $tokenInfo, $expireTime);
            return;
        } 
        if ($action == 'continue') {
            $redisModel->redisUpdateExpire($token, $expireTime);
            return;
        }
        if ($action == 'expire') {
            $exists = $redisModel->redisKeys($token);
            if ($exists) {
                $userInfo = $redisModel->redisGet($token, true);
                $new_token = $this->generateToken($userInfo);
                $new_token = $new_token[0];
                return ['new_token' => $new_token, 'userInfo' => $userInfo];
            } else {
                return false;
            }
        }
    }


    public function getCurrentUserId() {
        $this->setTokenCheck(true);
        $user_info = $this->checkToken();
        if (is_object($user_info)) {
            $user_info = (array)$user_info;
        }
        return $user_info['uid'];
    }
    
}
