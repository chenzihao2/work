<?php
/**
 * User: WangHui
 * Date: 2018/10/9
 * Time: 11:07
 */

namespace QK\HaoLiao\Controllers\Console;


use Firebase\JWT\JWT;
use QK\HaoLiao\Controllers\BaseController;
use QK\WSF\Settings\AppSetting;

class ConsoleController extends BaseController {
    public function __construct(AppSetting $appSetting) {
        parent::__construct($appSetting);
        $this->checkToken();
    }

    /**
     * Token检查
     * @return string
     */
    public function checkToken() {
        $token = isset($_REQUEST['token']) ? trim($_REQUEST['token']) : "";
        $uid = isset($_REQUEST['uid']) ? trim($_REQUEST['uid']) : "";
        if ($this->_tokenCheck) {
            if ($token != "") {
                try {
                    $decoded = JWT::decode($token, $this->_appSetting->getConstantSetting('JWT-KEY'), array('HS256'));
                    if ($decoded->uid != $uid) {
                        $this->setResultFailed();
                        $this->setResultMessage('Token 有误，请重新登录');
                        $this->output();
                    } else {
                        return $decoded;
                    }
                } catch (\Exception $e) {
                    $this->setResultFailed();
                    $this->setResultMessage('Token 有误，请重新登录');
                    $this->output();
                }
            } else {
                $this->setResultFailed();
                $this->setResultMessage('Token 无效');
                $this->output();
            }
        } else {
            return true;
        }
        return true;
    }
}
