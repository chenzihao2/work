<?php
/**
 * User: YangChao
 * Date: 2018/10/16
 */

namespace QK\HaoLiao\Controllers\Dist;


use Firebase\JWT\JWT;
use QK\HaoLiao\Controllers\BaseController;
use QK\WSF\Settings\AppSetting;

class DistController extends BaseController {

    public function __construct(AppSetting $appSetting){
        parent::__construct($appSetting);
        $this->initChannel();
        $this->checkToken();
    }

    /**
     * 定义请求来源
     */
    public function initChannel(){
        $param = $this->checkApiParam([], ['from' => 'common', 'weChatId' => 2]);
        $GLOBALS['weChatId'] = intval($param['weChatId']);
        $GLOBALS['From'] = trim($param['from']);
        $GLOBALS['FromSub'] = isset($param['from_sub']) ? trim($param['from_sub']) : '';
    }

    /**
     * 用户Token检查
     * @return bool|object
     */
    public function checkToken(){
        if ($this->_tokenCheck) {
            $param = $this->checkApiParam(['user_id', 'token']);
            $userId = intval($param['user_id']);
            $token = $param['token'];
            //普通用户TOKEN校验
            if ($token != "") {
                try {
                    $decoded = JWT::decode($token, $this->_appSetting->getConstantSetting('JWT-KEY'), array('HS256'));
                    if ($decoded->uid != $userId) {
                        $this->responseJsonError(102);
                    } else {
                        return $decoded;
                    }
                } catch (\Exception $e) {
                    $this->responseJsonError(102);
                }
            } else {
                $this->responseJsonError(102);
            }
        } else {
            return true;
        }
        return true;
    }
}