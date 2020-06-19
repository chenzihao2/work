<?php
/**
 * User: WangHui
 * Date: 2018/10/9
 * Time: 11:06
 */

namespace QK\HaoLiao\Controllers\Expert;


use Firebase\JWT\JWT;
use QK\HaoLiao\Controllers\BaseController;
use QK\HaoLiao\Model\ExpertModel;
use QK\WSF\Settings\AppSetting;

class ExpertController extends BaseController {

    public function __construct(AppSetting $appSetting){
        parent::__construct($appSetting);
        $this->initChannel();
        $this->checkToken();
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

    /**
     * 用户Token检查
     * @return bool|object
     */
    public function checkToken(){
        if ($this->_tokenCheck) {
            $param = $this->checkApiParam(['user_id', 'token'], ['expert_id'=>'', 'expert_token'=>'']);
            $userId = intval($param['user_id']);
            $token = $param['token'];
            $expertId = intval($param['expert_id']);
            $expertToken = $param['expert_token'];
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

            //专家TOKEN校验
            if ($expertId != "" || $expertToken != "") {
                try {
                    $decoded = JWT::decode($expertToken, $this->_appSetting->getConstantSetting('JWT-KEY'), array('HS256'));
                    if ($decoded->uid != $expertId) {
                        $this->responseJsonError(102);
                    } else {
                        return $decoded;
                    }
                } catch (\Exception $e) {
                    $this->responseJsonError(102);
                }
            }

            if($userId && $expertId){
                //校验用户是否和专家账号匹配
                $expertModel = new ExpertModel();
                $userIdentity = $expertModel->checkIdentity($userId);
                switch ($userIdentity){
                    //普通用户
                    case 1:
                        $this->responseJsonError(1004);
                        break;
                    //专家子用户
                    case 2:
                        $subAccountInfo = $expertModel->getExpertInfoBySubAccount($userId);
                        if($expertId != $subAccountInfo['expert_id']){
                            //您不是当前专家的子账户
                            $this->responseJsonError(1005);
                        }
                        break;
                    //专家用户
                    case 3:
                        if($userId != $expertId){
                            //您不能访问该专家下的信息
                            $this->responseJsonError(1006);
                        }
                        break;
                    default:
                        $this->responseJsonError(102);
                }
            }
        } else {
            return true;
        }
        return true;
    }
}