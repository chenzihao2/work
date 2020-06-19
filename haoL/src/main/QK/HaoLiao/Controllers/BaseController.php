<?php
/**
 * User: WangHui
 * Date: 2018/7/19
 * Time: 14:21
 */

namespace QK\HaoLiao\Controllers;

use QK\HaoLiao\Common\BaiDuParams;
use QK\HaoLiao\Common\WeChatParams;
use QK\HaoLiao\Model\RedisModel;
use QK\HaoLiao\XML\ErrorCodeConfig;
use QK\WeChat\Http;
use QK\WeChat\WeChatJsTicket;
use QK\WeChat\WeChatToken;
use QK\WSF\Core\WebController;
use QK\WSF\Settings\AppSetting;

class BaseController extends WebController {

    protected $_tokenCheck = true;

    protected $_apiData = [
        'code' => 200, 'message' => 'SUCCESS', 'data' => [],
    ];

    public function __construct(AppSetting $appSetting) {

        parent::__construct($appSetting);
    }

    public function setTokenCheck(bool $tokenStatus) {
        $this->_tokenCheck = $tokenStatus;
    }


    /**
     * 获取并验证传入的参数
     * @param array $must
     * @param array $default
     * @return array
     */
    protected function checkApiParam($must = [], $default = []) {
        $param = [];
        //获取并验证必须参数
        if (!empty($must)) {
            foreach ($must as $m) {
                $para = isset($_REQUEST[$m]) ? trim($_REQUEST[$m]) : null;
                if ($para === null || $para === '') {
                    $this->responseJsonError(101, '参数 \'' . $m . '\' 不存在 !');
                }
                $param[$m] = trim($para);
            }
        }

        //非必须参数
        if (!empty($default)) {
            foreach ($default as $k => $def) {
                $para = isset($_REQUEST[$k]) ? trim($_REQUEST[$k]) : null;
                if ($para === null) {
                    $param[$k] = $def;
                } else {
                    $param[$k] = trim($para);
                }
            }
        }
        return $param;
    }

    /**
     * JSON返回结果数据
     * @param array $data
     * @param string $message
     */
    protected function responseJson($data = [], $message = "") {
        header('Content-type:text/json;charset=utf-8');
        // 返回数据
        $this->_apiData['data'] = $data;
        if ($message !== "") {
            $this->_apiData['message'] = $message;
        }
        $result = json_encode($this->_apiData);
        if (isset($_GET['callback'])) {
            $result = $_GET['callback'] . '(' . $result . ')';
        }
        echo $result;
        die();
    }

    /**
     * JSON输出结果数据
     * @param array $data
     * @param string $message
     */
    protected function echoJson($data = [], $message = "") {
        header('Content-type:text/json;charset=utf-8');
        // 返回数据
        $this->_apiData['data'] = $data;
        if ($message !== "") {
            $this->_apiData['message'] = $message;
        }
        $result = json_encode($this->_apiData);
        if (isset($_GET['callback'])) {
            $result = $_GET['callback'] . '(' . $result . ')';
        }
        echo $result;
    }

    /**
     * JSON返回API错误
     * @param $errorCode
     * @param string $message
     * @param array $data
     */
    protected function responseJsonError($errorCode, $message = "", $data = []) {
        header('Content-type:text/json;charset=utf-8');
        $this->_apiData['code'] = $errorCode;
        if (!empty($message)) {
            $this->_apiData['message'] = $message;
        } else {
            $errorCodeConfig = new ErrorCodeConfig();
            $this->_apiData['message'] = $errorCodeConfig->getErrorMessageByCode($errorCode);
        }
        $this->_apiData['data'] = $data;
        $result = json_encode($this->_apiData);
        if (isset($_GET['callback'])) {
            $result = $_GET['callback'] . '(' . $result . ')';
        }
        echo $result;
        die();
    }

    /**
     * 微信AccessToken获取
     * @return bool|string
     */
    public function weChatToken() {
        $accessTokenRedisKey = "Access_Token_" . $GLOBALS['weChatId'];
        $redisModel = new RedisModel('wechat');
        if ($redisModel->redisGet($accessTokenRedisKey)) {
            return $redisModel->redisGet($accessTokenRedisKey);
        } else {
            $weChatParams = $this->getWeChatParams();
            $appId = $weChatParams['id'];
            $appKey = $weChatParams['appKey'];
            $token = new WeChatToken($appId, $appKey);
            $tokenInfo = $token->getToken();
            if (array_key_exists('code', $tokenInfo)) {
                return false;
            } else {
                $redisModel->redisSet($accessTokenRedisKey, $tokenInfo['access_token'], 7150);
                return $tokenInfo['access_token'];
            }
        }
    }

    /**
     * 获取微信参数
     * @return mixed
     */
    public function getWeChatParams() {
        $weChatParamsController = new WeChatParams();
        return $weChatParamsController->getNewWeChatParams();
    }

    /**
     * 获取百度参数
     * @return mixed
     */
    public function getBaiDuParams(){
        $baiDuParams = new BaiDuParams();
        return $baiDuParams->getBaiDuParams();
    }

    /**
     * 获取百度小程序参数
     * @return mixed
     */
    public function geBaiDuSmallRoutineParams(){
        $baiDuParams = new BaiDuParams();
        return $baiDuParams->geBaiDuSmallRoutineParams();
    }

    public function geBaiDuSmallRoutineParamsV2(){
        $baiDuParams = new BaiDuParams();
        return $baiDuParams->geBaiDuSmallRoutineParamsV2();
    }

    public function getJSTicket() {
        $redisKey = WECHAT_JS_TICKET . $GLOBALS['weChatId'];
        $redisModel = new RedisModel('wechat');
        //通过缓存获取jsTicket信息
        $ticket = $redisModel->redisGet($redisKey);
        if (!empty($ticket)) {
            return $ticket;
        }
        //微信接口获取jsTicket信息
        $accessToken = $this->weChatToken();
        $weChatTicket = new WeChatJsTicket($accessToken);
        $ticketInfo = $weChatTicket->getTicket();
        if (array_key_exists('code', $ticketInfo)) {
            return false;
        } else {
            $redisModel->redisSet($redisKey, $ticketInfo['ticket'], $ticketInfo['expires_in'] - 200);
            return $ticketInfo['ticket'];
        }
    }
}
