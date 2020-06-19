<?php
/**
 * 支付相关参数处理
 * User: YangChao
 * Date: 2018/10/29
 */

namespace QK\HaoLiao\Common;

use QK\WSF\Settings\AppSetting;

class PayParams {

    /**
     * 随机获取微信支付配置
     * @return mixed
     */
    public function getWeChatPayConfig() {
        $appSetting = AppSetting::newInstance(APP_ROOT);
        $paymentChannelConfig = $appSetting->getSettingByPath("pay:WeChat:" . $GLOBALS['weChatId']);
        foreach ($paymentChannelConfig as $key => $val) {
            $paymentChannelConfig[$key]['notifyUrl'] = $appSetting->getConstantSetting("DOMAIN_API") . $val['notifyUrl'];
            $paymentChannelConfig[$key]['refundNotifyUrl'] = $appSetting->getConstantSetting("DOMAIN_API") . $val['refundNotifyUrl'];
            if (!$val['status']) {
                //过滤不可用商户号
                unset($paymentChannelConfig[$key]);
            }
        }
        $paymentChannelConfigKey = array_rand($paymentChannelConfig, 1);
        $paymentChannelConfig['pay_key'] = $paymentChannelConfigKey;
        $paymentChannelConfig['pay_config'] = $paymentChannelConfig[$paymentChannelConfigKey];
        return $paymentChannelConfig;
    }

    public function getNewWeChatPayConfig($platform='') {

        $appSetting = AppSetting::newInstance(APP_ROOT);

        // 默认为 h5 支付, 百度小程序不会用到微信支付相关配置
        if (empty($platform) && !empty($_REQUEST['platform'])) {
            $platform = $_REQUEST['platform'];
        }

        switch ($platform) {
            case 'android':
                $field = '1537647631';
                break;
            case 'h5':
            default:
                $field = '1521536081';
                break;
        }

        $paymentChannelConfig = $appSetting->getSettingByPath("pay:NewWeChat:" . $field);

        $paymentChannelConfig['notifyUrl'] = $appSetting->getConstantSetting("DOMAIN_API") . $paymentChannelConfig['notifyUrl'];
        $paymentChannelConfig['refundNotifyUrl'] = $appSetting->getConstantSetting("DOMAIN_API") . $paymentChannelConfig['refundNotifyUrl'];

        return $paymentChannelConfig;
    }

    /**
     * 随机获取支付宝支付配置
     * @return mixed
     */
    public function getAliPayPayConfig() {
        $appSetting = AppSetting::newInstance(APP_ROOT);
        $paymentChannelConfig = $appSetting->getSettingByPath("pay:AliPay");
        foreach ($paymentChannelConfig as $key => $val) {
            $paymentChannelConfig[$key]['notifyUrl'] = $appSetting->getConstantSetting("DOMAIN_API") . $val['notifyUrl'];
            $paymentChannelConfig[$key]['returnUrl'] = $appSetting->getConstantSetting("DOMAIN_CUSTOMER") . $val['returnUrl'];
            if (!$val['status']) {
                //过滤不可用商户号
                unset($paymentChannelConfig[$key]);
            }
        }
        $paymentChannelConfigKey = array_rand($paymentChannelConfig, 1);
        $paymentChannelConfig['pay_key'] = $paymentChannelConfigKey;
        $paymentChannelConfig['pay_config'] = $paymentChannelConfig[$paymentChannelConfigKey];
        return $paymentChannelConfig;
    }


    /**
     * 随机获取百度支付配置
     * @return mixed
     */
    public function getBaiDuPayConfig() {
        $appSetting = AppSetting::newInstance(APP_ROOT);
        $paymentChannelConfig = $appSetting->getSettingByPath("pay:BaiDuPay");
        foreach ($paymentChannelConfig as $key => $val) {
            $paymentChannelConfig[$key]['notifyUrl'] = $appSetting->getConstantSetting("DOMAIN_API") . $val['notifyUrl'];
            if (!$val['status']) {
                //过滤不可用商户号
                unset($paymentChannelConfig[$key]);
            }
        }
        $paymentChannelConfigKey = array_rand($paymentChannelConfig, 1);
        $paymentChannelConfig['pay_key'] = $paymentChannelConfigKey;
        $paymentChannelConfig['pay_config'] = $paymentChannelConfig[$paymentChannelConfigKey];
        return $paymentChannelConfig;
    }

    public function getApplePayConfig() {
        $appSetting = AppSetting::newInstance(APP_ROOT);
        $payConfig = $appSetting->getSettingByPath('pay:ApplePay');
        return $payConfig;
    }

    /**
     * 获取微信支付配置信息
     * @param $weChatId
     * @param $channelId
     * @return mixed
     */
    public function getWeChatPayConfigByParam($weChatId, $channelId) {
        $appSetting = AppSetting::newInstance(APP_ROOT);
        $payConfig = $appSetting->getSettingByPath("pay:WeChat:" . $weChatId . ":" . $channelId);
        $payConfig['notifyUrl'] = $appSetting->getConstantSetting("DOMAIN_API") . $payConfig['notifyUrl'];
        $payConfig['returnUrl'] = $appSetting->getConstantSetting("DOMAIN_CUSTOMER") . $payConfig['refundNotifyUrl'];
        return $payConfig;
    }

    public function getNewWeChatPayConfigByParam($mchId, $weChatId = '') {
        $appSetting = AppSetting::newInstance(APP_ROOT);
        $payConfig = $appSetting->getSettingByPath("pay:NewWeChat:" . $mchId);
        if (empty($payConfig)) {
            // 兼容旧订单
            return $this->getWeChatPayConfigByParam($weChatId, $mchId);
        } else {
            $payConfig['notifyUrl'] = $appSetting->getConstantSetting("DOMAIN_API") . $payConfig['notifyUrl'];
            $payConfig['returnUrl'] = $appSetting->getConstantSetting("DOMAIN_CUSTOMER") . $payConfig['refundNotifyUrl'];
            return $payConfig;
        }
    }

    /**
     * 根据支付号Key获取支付宝支付配置
     * @return mixed
     */
    public function getAliPayPayConfigByKey($key) {
        $appSetting = AppSetting::newInstance(APP_ROOT);
        $payConfig = $appSetting->getSettingByPath("pay:AliPay:" . $key);
        $payConfig['notifyUrl'] = $appSetting->getConstantSetting("DOMAIN_API") . $payConfig['notifyUrl'];
        $payConfig['returnUrl'] = $appSetting->getConstantSetting("DOMAIN_CUSTOMER") . $payConfig['returnUrl'];
        return $payConfig;
    }

    /**
     * 根据支付号Key获取百度支付配置
     * @return mixed
     */
    public function getBaiDuPayPayConfigByKey($key) {
        $appSetting = AppSetting::newInstance(APP_ROOT);
        $payConfig = $appSetting->getSettingByPath("pay:BaiDuPay:" . $key);
        $payConfig['notifyUrl'] = $appSetting->getConstantSetting("DOMAIN_API") . $payConfig['notifyUrl'];
        return $payConfig;
    }

    /**
     * 获取商品名称马甲
     * @return mixed
     */
    public function getVest() {
        $appSetting = AppSetting::newInstance(APP_ROOT);
        $payVestConfig = $appSetting->getSettingByPath("pay:Vest");
        $payVestKey = array_rand($payVestConfig, 1);
        return $payVestConfig[$payVestKey];
    }

    public function getVcRate() {
        $appSetting = AppSetting::newInstance(APP_ROOT);
        return $payVestConfig = $appSetting->getSettingByPath("pay:VcRate");
    }

    /**
     * 获取打款渠道
     * @return mixed
     */
    public function getWithDrawChannel() {
        $appSetting = AppSetting::newInstance(APP_ROOT);
        return $appSetting->getSettingByPath('pay:withDrawChannel');
    }

    /**
     * 获取提现信息
     * @param $type 1微信，2支付宝
     * @return mixed
     */
    public function getWithDrawInfo($type = 1) {
        $appSetting = AppSetting::newInstance(APP_ROOT);
        if ($type == 1) {
            $configKey = "pay:WeChatWithDraw";
        } else {
            $configKey = "pay:AliPayWithDraw";
        }
        $payVestConfig = $appSetting->getSettingByPath($configKey);
        $payVestKey = array_rand($payVestConfig, 1);
        return $payVestConfig[$payVestKey];
    }
}