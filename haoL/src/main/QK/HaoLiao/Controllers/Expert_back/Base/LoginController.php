<?php
/**
 * User: WangHui
 * Date: 2018/9/29
 * Time: 16:55
 */

namespace QK\HaoLiao\Controllers\Expert\Base;

use Firebase\JWT\JWT;
use QK\HaoLiao\Controllers\Expert\ExpertController;
use QK\HaoLiao\Model\UserModel;
use QK\WSF\Settings\AppSetting;

class LoginController extends ExpertController {

    public function __construct(AppSetting $appSetting) {
        $this->setTokenCheck(false);
        parent::__construct($appSetting);
    }
    /**
     * 微信登录
     */
    public function login() {
        $code = isset($_REQUEST['code']) ? trim($_REQUEST['code']) : 0;
        $redirect = isset($_REQUEST['redirect']) ? trim($_REQUEST['redirect']) : '';
        $programWeChatInfo = $this->getWeChatParams();
        $appId = $programWeChatInfo['id'];
        //当没有传递微信code参数为跳转微信授权登录
        if (!$code) {
            $api_url = $this->_appSetting->getConstantSetting("DOMAIN_API");
            $redirect_uri = urlencode($api_url . "/index.php?c=login&do=login&v=1&p=expert&weChatId=" . $GLOBALS['weChatId'] . "&redirect=" . $redirect);
            $response_type = 'code';
            $scope = 'snsapi_userinfo';
            $state = 'STATE';
            $url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=" . $appId . "&redirect_uri=" . $redirect_uri . "&response_type=" . $response_type . "&scope=" . $scope . "&state=" . $state . "#wechat_redirect";
            Header("Location:" . $url);
            exit;
        }
        $userModel = new UserModel();
        $params['code'] = $code;
        $userLoginInfo = $userModel->weChatLogin($params);
        if ($userLoginInfo && !empty($userLoginInfo)) {
            $userId = $userLoginInfo['uid'];
            if (strpos($redirect, '?')) {
                $redirect .= '&';
            } else {
                $redirect .= '?';
            }
            $userInfo = $userModel->getUserInfo($userId);
            $tokenArray = [
                "uid" => $userId, "nickname" => $userInfo['nick_name'], "iat" => time(), "exp" => time() + 7200,
            ];
            $key = $this->_appSetting->getConstantSetting('JWT-KEY');
            $token = JWT::encode($tokenArray, $key);
            $redirect .= "user_id=" . $userId . "&token=" . $token;
        }
        Header("Location:" . $redirect);
        exit;
    }

}