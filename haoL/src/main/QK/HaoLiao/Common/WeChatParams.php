<?php
/**
 * 微信相关参数处理
 * User: WangHui
 * Date: 2018/9/29
 * Time: 10:01
 */

namespace QK\HaoLiao\Common;

use QK\HaoLiao\XML\ProgramConfig;
use QK\WSF\Settings\AppSetting;

class WeChatParams
{
    /**
     * 获取微信参数
     * @param int $weChatId
     * @return mixed
     */
	public function getWeChatParams($weChatId = 1) {
		$appSetting = AppSetting::newInstance(APP_ROOT);
        $weChatId = $GLOBALS['weChatId'] ? $GLOBALS['weChatId'] : $weChatId;
		$programWeChatInfo = $appSetting->getConstantSetting("WeChatLogin:".$weChatId);
		$appId = $programWeChatInfo['WeChat-Mini-Id'];
		$appKey = $programWeChatInfo['WeChat-Mini-Key'];
		$data['id'] = $appId;
		$data['appKey'] = $appKey;
		return $data;
	}

	public function getNewWeChatParams($platform = '', $weChatId = 1) {

	    if (empty($platform) && isset($_REQUEST['platform'])) {
	        $platform = $_REQUEST['platform'];
        }
        // 百度小程序不会用到微信支付相关配置
        switch ($platform) {
            case 'android':
            case 'ios':
                $field = 'wx87ad1dd9acf928b0';
                break;
            case 'h5':       // h5
            default:        // 默认
                switch ($weChatId) {
                    case '1':
                        $field = 'wx144ea638af427946';
                        break;
                    case '2':
                        $field = 'wx7e5cf7c2d3526f09';
                        break;
                    case '3':
                        $field = 'wx8d2225dceef93bf6';
                        break;
                    case '4':
                        $field = 'wxe14a6e6d04f394f4';
                        break;
                    default:
                        return false;
                        break;
                }
                break;
        }

	    $appSetting = AppSetting::newInstance(APP_ROOT);
		$programWeChatInfo = $appSetting->getConstantSetting("NewWeChatLogin:".$field);
		$appId = $programWeChatInfo['WeChat-Mini-Id'];
		$appKey = $programWeChatInfo['WeChat-Mini-Key'];
		$data['id'] = $appId;
		$data['appKey'] = $appKey;
		return $data;
    }

    public function getNewWeChatParamsByAppId($appid, $weChatId = '') {
        $appSetting = AppSetting::newInstance(APP_ROOT);
        $programWeChatInfo = $appSetting->getConstantSetting("NewWeChatLogin:".$appid);
        // 兼容旧订单
        if (empty($programWeChatInfo)) {
            return $this->getWeChatParams($weChatId);
        } else {
            $appId = $programWeChatInfo['WeChat-Mini-Id'];
            $appKey = $programWeChatInfo['WeChat-Mini-Key'];
            $data['id'] = $appId;
            $data['appKey'] = $appKey;
            return $data;
        }
    }

    /**
     * 获取当前登陆渠道信息
     * @return array|bool
     */
	public function loginType() {
		$program = new ProgramConfig();
		$programInfo = $program->getConfigById($GLOBALS['weChatId']);
		return $programInfo;
	}
	
}