<?php
/**
 * User: WangHui
 * Date: 2018/9/29
 * Time: 16:55
 */

namespace QK\HaoLiao\Controllers\User\Base;

use Firebase\JWT\JWT;
use QK\HaoLiao\Common\BaiDuParams;
use QK\HaoLiao\Common\CommonHandler;
use QK\HaoLiao\Common\StringHandler;
use QK\HaoLiao\Controllers\User\UserController;
use QK\HaoLiao\Model\UserModel;
use QK\WSF\Settings\AppSetting;

class LoginController extends UserController {
    private $token_expire = 300;

    public function __construct(AppSetting $appSetting) {
        $this->setTokenCheck(false);
        parent::__construct($appSetting);
    }

    /**
     * 微信登录
     */
    public function login() {
        $code = isset($_REQUEST['code']) ? trim($_REQUEST['code']) : 0;
        $distId = isset($_REQUEST['dist_id']) ? $_REQUEST['dist_id'] : 0;
        $redirect = isset($_REQUEST['redirect']) ? trim($_REQUEST['redirect']) : '';
        $programWeChatInfo = $this->getWeChatParams();
        $appId = $programWeChatInfo['id'];
        //当没有传递微信code参数为跳转微信授权登录
        if (!$code) {
            $api_url = $this->_appSetting->getConstantSetting("DOMAIN_API");
            $redirect_uri = urlencode($api_url . "/index.php?c=login&do=login&v=1&p=user&weChatId=" . $GLOBALS['weChatId'] . "&redirect=" . $redirect. "&dist_id=" . $distId);
            $response_type = 'code';
            $scope = 'snsapi_userinfo';
            $state = 'STATE';
            $url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=" . $appId . "&redirect_uri=" . $redirect_uri . "&response_type=" . $response_type . "&scope=" . $scope . "&state=" . $state . "#wechat_redirect";
            Header("Location:" . $url);
            exit;
        }
        $userModel = new UserModel();
        $params['code'] = $code;
        $params['dist_id'] = $distId ? StringHandler::newInstance()->decode($distId) : 0 ;
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
                "uid" => $userId, "nickname" => $userInfo['nick_name'], "iat" => time(), "exp" => time() + $this->token_expire,
            ];
            $key = $this->_appSetting->getConstantSetting('JWT-KEY');
            $token = JWT::encode($tokenArray, $key);
            $redirect .= "user_id=" . $userId . "&token=" . $token . "&expire=" . ($tokenArray['exp'] - 10);
        }
        Header("Location:" . $redirect);
        exit;
    }

    /**
     * 百度网页版授权登录
     */
    public function baiduLogin(){
        $code = isset($_REQUEST['code']) ? trim($_REQUEST['code']) : 0;
        $redirect = isset($_REQUEST['redirect']) ? trim($_REQUEST['redirect']) : '';
        $redirect = str_replace('#', '!', $redirect);
        $programBaiDuInfo = $this->getBaiDuParams();
        $apiKey = $programBaiDuInfo['Api-Key'];

        //当没有传递code参数为跳转百度授权登录
        if (!$code) {
            $api_url = $this->_appSetting->getConstantSetting("DOMAIN_API");
            $redirect_uri = urlencode($api_url . "index.php?c=login&do=baiduLogin&v=1&p=user&weChatId=" . $GLOBALS['weChatId'] . "&redirect=" . $redirect);
            $url = "https://openapi.baidu.com/oauth/2.0/authorize?client_id=" . $apiKey . "&response_type=code&redirect_uri=" . $redirect_uri . "&scope=&display=mobile";
            Header("Location:" . $url);
            exit;
        }
        $userModel = new UserModel();
        $params['code'] = $code;
        $userLoginInfo = $userModel->baiDuLogin($params, $redirect);

        if ($userLoginInfo && !empty($userLoginInfo)) {
            $userId = $userLoginInfo['uid'];
            $redirect = str_replace('!', '#', $redirect);
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

    /**
     * 获取百度session_key
     */
    public function getBaiduSessionKey(){
        $params  = $this->checkApiParam(['code']);
        $code = $params['code'];

        $programBaiDuSmallRoutineInfo = $this->geBaiDuSmallRoutineParams();

        //获取百度session_key
        $commonHandler = new CommonHandler();
        $postParams = [];
        $postParams['code'] = $code;
        $postParams['client_id'] = $programBaiDuSmallRoutineInfo['App-Key'];
        $postParams['sk'] = $programBaiDuSmallRoutineInfo['App-Secret'];
//        $baiDuSessionKeyJson = $commonHandler->httpPost('https://openapi.baidu.com/nalogin/getSessionKeyByCode', $postParams);
        $baiDuSessionKeyJson = $commonHandler->httpPost('https://spapi.baidu.com/oauth/jscode2sessionkey', $postParams);
        $baiDuSessionKeyArr = json_decode($baiDuSessionKeyJson, true);
        $this->responseJson($baiDuSessionKeyArr);
    }

    /**
     * 解密百度用户信息
     */
    public function decryptBaiduUserInfo(){
        $params  = $this->checkApiParam(['iv', 'cipher', 'session_key']);
        $iv = $params['iv'];
        $cipher = $params['cipher'];
        $sessionKey = $params['session_key'];

        $programBaiDuSmallRoutineInfo = $this->geBaiDuSmallRoutineParams();

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

}
