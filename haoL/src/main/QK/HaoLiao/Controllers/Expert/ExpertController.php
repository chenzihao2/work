<?php

namespace QK\HaoLiao\Controllers\Expert;

use Firebase\JWT\JWT;
use QK\HaoLiao\Controllers\BaseController;
use QK\HaoLiao\Model\ExpertModel;
use QK\HaoLiao\Model\RedisModel;
use QK\WSF\Settings\AppSetting;

class ExpertController extends BaseController {

  private $tokenExpire = 3600 * 8;
  public function __construct(AppSetting $appSetting){
    parent::__construct($appSetting);
    $this->initChannel();
    //$this->checkToken();
  }

  /**
   * 定义请求来源
   */
  public function initChannel(){
    $param = $this->checkApiParam([], ['from' => 'common',  'weChatId' => '']);
    $GLOBALS['weChatId'] = isset($param['weChatId']) ? intval($param['weChatId']) : $this->_appSetting->getConstantSetting('DEFAULT_WECHATID');
    $GLOBALS['From'] = trim($param['from']);
    $GLOBALS['FromSub'] = isset($param['from_sub']) ? trim($param['from_sub']) : '';
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
    public function checkToken(){
	 
    if ($this->_tokenCheck) {
      $param = $this->checkApiParam([],['noToken'=>false,'uid'=>'', 'token'=>'']);
      $userId = intval($param['uid']);
      $token = $param['token'];
	
	// dump($param);die;
      //普通用户TOKEN校验
      if ($token != ""||$token != "null") {
		  
        try {
          $decoded = JWT::decode($token, $this->_appSetting->getConstantSetting('JWT-KEY'), array('HS256'));
		 
          if ($decoded->uid != $userId) {
			 
            $this->responseJsonError(102);
          } else {
            return $decoded;
          }
        } catch (\Exception $e) {
			
			if($param['noToken']==false){
				$this->responseJsonError(102);
			}
				//$this->responseJsonError(102);
        }
      } else if($param['noToken']==false){
		 
        $this->responseJsonError(102);
      }
    } else {
      return true;
    }
    return true;
  }

  private function tokenDependRedis($token, $action = 'set', $tokenInfo = []) {
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
}
