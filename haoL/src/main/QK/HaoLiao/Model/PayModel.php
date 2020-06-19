<?php
/**
 * 付款部分模块
 */
namespace QK\HaoLiao\Model;

use QK\AliPay\Pay\AliPayWapPay;
use QK\HaoLiao\Common\CommonHandler;
use QK\HaoLiao\Common\PayParams;
use QK\HaoLiao\Common\WeChatParams;
use QK\WeChat\Pay\WeChatPayConfig;

class PayModel extends BaseModel {

    private $_userModel;
    private $_channelModel;
    private $_userId;
    private $_userInfo;
    private $_orderType;
    private $_paymentMethod;
    private $_tradeType;
    private $_payReturnUrl;
    private $_isWeChat;
    private $_payParams;
    private $_paymentChannel;
    private $_payConfig;
    private $_weChatParams;
    private $_orderNum;
    private $_price;
    private $_goodsName;

    public function __construct($userId, $orderType, $paymentMethod, $tradeType, $payReturnUrl) {

        parent::__construct();
        $this->_userModel = new UserModel();
	$this->_channelModel = new ChannelModel();

        $this->_userId = $userId;
        //获取用户信息
        $userInfo = $this->_userModel->getUserInfo($this->_userId);
        // 获取用户渠道信息
        $channelModel = new ChannelModel();
        $userChannel = $channelModel->getUserByChannel(['cid' => $userInfo['cid']]);
        $userInfo['target'] = $userChannel['target'];
        $userInfo['platform'] = $userChannel['platform'];
        $this->_userInfo = $userInfo;

        $this->_orderType = $orderType;
        $this->_paymentMethod = $paymentMethod;
        $this->_tradeType = $tradeType;
        $this->_payReturnUrl = $payReturnUrl;

        //判断是否微信内访问
        $this->_isWeChat = (int)CommonHandler::newInstance()->isWeChat();

        //根据支付渠道  获取支付配置
        $this->_payParams = new PayParams();
    }

    public function initPay() {

        if($this->_userInfo['user_status'] == 2){
            //用户被封禁
            return $this->retError(1003);
        }

        if($this->_paymentMethod == 2 && $this->_isWeChat){
            //微信内调用支付宝支付
            $data = [];
            $data['is_weChat'] = 1;
            $data['pay_url'] = "";
            return $this->retSuccess(200, $data);
        }

        $configRes = $this->payConfig();
        if ($configRes['status_code'] !== 200) {
            return $configRes;
        }

        return $this->retSuccess();
    }

    /**
     * 下单
     */
    public function pay($goodsName, $orderNum, $price) {

        $this->_orderNum = $orderNum;
        $this->_price = $price;

        //获取商品马甲名称
        $this->_goodsName = $goodsName;

        switch($this->_paymentMethod){
            case 1:
                return $this->wxPay();
            case 2:
                //支付宝支付
                switch($this->_tradeType){
                    case 4:
                        //支付宝PC支付(暂无用)
                        return $this->retError(3003);
                        break;
                    case 5:
                        //支付宝H5支付
                        $aliPayWapPay = new AliPayWapPay($this->_payConfig['appId'], $this->_payConfig['merchantPrivateKey'], $this->_payConfig['merchantPublicKey'], $this->_payConfig['aliPayPublicKey'], $this->_payConfig['notifyUrl'], $this->_payConfig['returnUrl']);
                        $payRes = $aliPayWapPay->createOrder($orderNum, $price / 100, $this->_goodsName);

                        $data = [];
                        $data['is_weChat'] = $this->_isWeChat;
                        $data['pay_url'] = $payRes;
                        return $this->retSuccess($data);
                        break;
                    case 6:
                        //支付宝APP支付
                        $aop = new \AopClient;
                        $aop->gatewayUrl = "https://openapi.alipay.com/gateway.do";
                        $aop->appId = $this->_payConfig['appId'];
                        $aop->rsaPrivateKey = $this->_payConfig['merchantPrivateKey'];
                        $aop->signType = 'RSA2';
                        $aop->alipayrsaPublicKey = $this->_payConfig['aliPayPublicKey'];

                        $request = new \AlipayTradeAppPayRequest();
                        $bizcontent = [
                            'body' => $this->_goodsName,
                            'subject' => $this->_goodsName,
                            'out_trade_no' => $orderNum,
                            'timeout_express' => '10m',
                            'total_amount' => $price / 100
                        ];
                        $bizcontent = json_encode($bizcontent);

                        $request->setNotifyUrl($this->_payConfig['notifyUrl']);
                        $request->setBizContent($bizcontent);

                        $response = $aop->sdkExecute($request);

                        return $this->retSuccess($response);
                        break;
                    default:
                        return $this->retError(3003);
                        break;
                }
                break;
            case 3:
                //百度支付
                switch($this->_tradeType){
                    //小程序支付
                    case 7:
                        $rsaPriviateKeyFilePath = $this->_payConfig['privateKeyPath'];
                        if( !file_exists($rsaPriviateKeyFilePath) || !is_readable($rsaPriviateKeyFilePath)){
                            return $this->retError(3003);
                        }

                        $rsaPrivateKeyStr = file_get_contents($rsaPriviateKeyFilePath);

                        $requestParams = [
                            'dealId' => $this->_payConfig['dealId'],
                            'appKey' => $this->_payConfig['appKey'],
                            'totalAmount' => $price,
                            'tpOrderId' => $orderNum,
                        ];

                        $rsaSign = \NuomiRsaSign::genSignWithRsa($requestParams, $rsaPrivateKeyStr);

//                        $rsaPublicKeyFilePath =  $paymentChannelConfig['pay_config']['publicKeyPath'];
//                        $rsaPublicKeyStr = file_get_contents($rsaPublicKeyFilePath);
//                        $requestParams['sign'] = $rsaSign;
//                        $checkSignRes = \NuomiRsaSign::checkSignWithRsa($requestParams, $rsaPublicKeyStr);

                        $requestParams['dealTitle'] = $this->_goodsName;
                        $requestParams['signFieldsRange'] = 1;
                        $requestParams['rsaSign'] = $rsaSign;

                        $bizInfo = [];
                        $bizInfo['tpData'] = [
                            'appKey' => $this->_payConfig['appKey'],
                            'dealId' => $this->_payConfig['dealId'],
                            'tpOrderId' => $orderNum,
                            'totalAmount' => $price,
                        ];

                        $requestParams['bizInfo'] = json_encode($bizInfo);
                        return $this->retSuccess($requestParams);
                        break;
                    default:
                        return $this->retError(3003);
                        break;
                }
                break;
            case 4:  // apple pay
            case 100:  // 虚拟币支付
                return $this->retSuccess(['orderNum' => $orderNum, 'orderStatus' => 0]);
                break;
            default:
                return $this->retError(3003);
                break;
        }
    }

    /**
     * 获取支付配置
     */
    public function payConfig() {

        switch($this->_paymentMethod){
            case 1:
                $platform = '';
                if ($this->_tradeType == 3) {
                    $platform = 'android';
                } elseif ($this->_tradeType == 1) {
                    $platform = 'h5';
                }
                //微信支付
                $weChatId = $GLOBALS['weChatId'] = $this->_appSetting->getConstantSetting('DEFAULT_WECHATID');
                $weChatParamsCommon = new WeChatParams();
                $weChatParams = $weChatParamsCommon->getNewWeChatParams($platform, $weChatId);
                if (!$weChatParams) {
                    return $this->retError(-1, '配置信息错误');
                }
                $this->_weChatParams = $weChatParams;
                //获取微信支付配置
                $payConfig = $this->_payParams->getNewWeChatPayConfig($platform);
                if (!$payConfig) {
                    return $this->retError(-1, '配置信息错误');
                }
                $paymentChannelConfig['pay_config'] = $payConfig;
                $paymentChannelConfig['pay_key'] = $payConfig['mchId'];
                break;
            case 2:
                //支付宝支付
                $paymentChannelConfig = $this->_payParams->getAliPayPayConfig();
                $paymentChannelConfig['pay_config']['returnUrl'] = $this->_payReturnUrl != '' && !$this->_isWeChat ? $this->_payReturnUrl : $paymentChannelConfig['pay_config']['returnUrl'];
                break;
            case 3:
                // 百度支付
                $paymentChannelConfig = $this->_payParams->getBaiDuPayConfig();
                break;
            case 4:
                // 苹果支付
                break;
            case 100:
                // 虚拟币支付
                break;
            default:
                return $this->retError(3003);
                break;
        }

        $this->_paymentChannel = isset($paymentChannelConfig['pay_key']) ? $paymentChannelConfig['pay_key'] : 0;
        $this->_payConfig = isset($paymentChannelConfig['pay_config']) ? $paymentChannelConfig['pay_config'] : [];

        return $this->retSuccess();
    }

    private function wxPay() {

        //获取用户微信身份信息
        $userWeChatInfo = $this->_userModel->getUserWeChatInfo($this->_userId);

        $desc = $this->_goodsName;
        $order = $this->_orderNum;
        $price = $this->_price;
        $notifyUrl = $this->_payConfig['notifyUrl'];
        $openId = $userWeChatInfo['openid'];

        $input = new \WxPayUnifiedOrder();
        $input->SetOut_trade_no($order);
        $input->SetTotal_fee($price);
        $input->SetTime_start(date("YmdHis"));
        $input->SetTime_expire(date("YmdHis", time() + 600));
        $input->SetNotify_url($notifyUrl);
        $input->SetOpenid($openId);

        $config = new WeChatPayConfig();
        $config->SetAppId($this->_weChatParams['id']);
        $config->SetAppSecret($this->_weChatParams['appKey']);
        $config->SetKey($this->_payConfig['mchSecretKey']);
        $config->SetMerchantId($this->_payConfig['mchId']);
        $config->SetNotifyUrl($this->_payConfig['notifyUrl']);
        $config->SetCertPath($this->_payConfig['certPath']);
        $config->SetKeyPath($this->_payConfig['keyPath']);

        //微信支付
        switch($this->_tradeType){
            case 1:
                // JS 支付
                $input->SetBody($desc);
                $input->SetTrade_type("JSAPI");
                break;
            case 2:
                //微信原生扫码支付(暂无用)
                return $this->retError(3003);
                break;
            case 3:
                //微信APP支付
                // 需传入 app 名字
		$channelInfo = $this->_channelModel->getUserChannelInfo($this->_userId);
		//if ($channelInfo['channel'] != 0) {
		  //如果channel是新的appid，则使用新的appid和对应的openid
		  $config->SetAppId('wx9cc12e1169da2064');
        	  $config->SetAppSecret('af0feedd603075237338cc8b1ad010e6');
		//}
                $input->SetBody($desc);
                $input->SetTrade_type("APP");
                break;
            default:
                return $this->retError(3003);
                break;
        }


        $wx_pre = \WxPayApi::unifiedOrder($config, $input);

        if ($wx_pre['return_code'] == 'SUCCESS') {
            if (!empty($wx_pre['err_code'])) {
                return $this->retError(-1, $wx_pre['err_code_des']);
            }
            $return['status_code'] = 200;
            if ($this->_tradeType == 3) {
                $res['appid'] = $wx_pre['appid'];
                $res['partnerid'] = $wx_pre['mch_id'];
                $res['prepayid'] = $wx_pre['prepay_id'];
                $res['package'] = "Sign=WXPay";
                $res['noncestr'] = $wx_pre['nonce_str'];
                $res['timestamp'] = (string)time();
            } else {
                $res['timeStamp'] = (string)time();
                $res['appId'] = $wx_pre['appid'];
                $res['nonceStr'] = $wx_pre['nonce_str'];
                $res['package'] = "prepay_id=" . $wx_pre['prepay_id'];
                $res['signType'] = 'MD5';
            }
            $keywords = '';
            $dealRes = $res;
            ksort($dealRes);
            foreach ($dealRes as $k => $v) {
                $keywords .= $k . '=' . $v . '&';
            }
            $keywords = trim($keywords, '&');
            $keywords .= '&key=' . $this->_payConfig['mchSecretKey'];

            if ($this->_tradeType == 3) {
                $res['sign'] = strtoupper(md5($keywords));
            } else {
                $res['paySign'] = strtoupper(md5($keywords));
                $res['timestamp'] = $res['timeStamp'];
                unset($res['timeStamp']);
            }
            $res['order_num'] = $this->_orderNum;
            return $this->retSuccess($res);
        } else {
            return $this->retError(-1, $wx_pre['return_msg']);
        }

    }

    public function getPaymentChannel() {
        return $this->_paymentChannel;
    }

    public function getUserInfo() {
        return $this->_userInfo;
    }

    public function getUserModel() {
        return $this->_userModel;
    }

    public function getAppId() {
        return isset($this->_weChatParams) ? $this->_weChatParams['id'] : '';
    }

}
