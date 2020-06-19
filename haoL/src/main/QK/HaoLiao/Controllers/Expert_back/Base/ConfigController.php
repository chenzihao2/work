<?php
/**
 * 各种配置获取
 * User: WangHui
 * Date: 2018/10/22
 * Time: 上午10:55
 */

namespace QK\HaoLiao\Controllers\Expert\Base;

use QK\HaoLiao\Controllers\Expert\ExpertController;
use QK\WeChat\WeChatJsTicket;
use QK\WSF\Settings\AppSetting;

class ConfigController extends ExpertController {
    public function __construct(AppSetting $appSetting) {
        $this->setTokenCheck(false);
        parent::__construct($appSetting);
    }
    /**
     * 微信jsTicket配置获取
     */
    public function wxJsConfig() {
        $params = $this->checkApiParam(['url']);
        $url = urldecode($params['url']);
        $ticket = $this->getJSTicket();
        $token = $this->weChatToken();
        $weChatParams = $this->getWeChatParams();
        $id = $weChatParams['id'];
        $weChatTicket = new WeChatJsTicket($token);
        $data = $weChatTicket->wxJsConfig($id, $ticket, $url);
        $this->responseJson($data);
    }

}