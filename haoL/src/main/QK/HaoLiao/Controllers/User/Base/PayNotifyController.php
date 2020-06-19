<?php
/**
 * 支付回调相关接口
 * User: YangChao
 * Date: 2018/11/13
 */

namespace QK\HaoLiao\Controllers\User\Base;

include "./vendor/qk/alipay/src/SDK/WapPay/aop/AopClient.php";

use QK\AliPay\Pay\AliPayNotify;
use QK\HaoLiao\Common\AliPayParams;
use QK\HaoLiao\Common\PayParams;
use QK\HaoLiao\Controllers\User\UserController;
use QK\HaoLiao\Model\OrderModel;
use QK\WSF\Settings\AppSetting;

class PayNotifyController extends UserController {

    public function __construct(AppSetting $appSetting) {
        $this->setTokenCheck(false);
        parent::__construct($appSetting);
    }

    /**
     * 微信支付回调处理
     */
    public function weChatNotify(){
        $xml = file_get_contents("php://input");
        //将xml转化为json格式
        $jsonxml = json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA));
        //转成数组
//        $jsonxml = '{"appid":"wx8d2225dceef93bf6","bank_type":"CMB_CREDIT","cash_fee":"112","fee_type":"CNY","is_subscribe":"N","mch_id":"1516878131","nonce_str":"kyell8s6zku8fzfkf2h4s1svh7x75idk","openid":"oFU745iD6GxXvzWDm_hMYCFm3Gjw","out_trade_no":"201904090221008209222936","result_code":"SUCCESS","return_code":"SUCCESS","sign":"36F65B7A8171F37744D9CA7AA97F0459","time_end":"20181130110720","total_fee":"1000","trade_type":"JSAPI","transaction_id":"4200000221201811303689851739"}';
//        $jsonxml = '{"appid":"wxe237328d2d0d93ad","bank_type":"CMB_CREDIT","cash_fee":"1","fee_type":"CNY","is_subscribe":"Y","mch_id":"1514952501","nonce_str":"348gmq0fpk99g1hjtoqsf7twanozwz48","openid":"o9a9L0w7Y7HR2Uwn1qn2A18dItwQ","out_trade_no":"201811221645129644226130","result_code":"SUCCESS","return_code":"SUCCESS","sign":"CF25242502D4A7A8462C554B2387A0BD","time_end":"20181122164518","total_fee":"1","trade_type":"JSAPI","transaction_id":"4200000222201811228803750264"}';
        $result = json_decode($jsonxml, true);
//        file_put_contents("./wechatpaylog.txt", date("Y-m-d H:i:s") . "  " . json_encode($result) . "\r\n", FILE_APPEND);

        if($result){
            //如果成功返回了
            if($result['return_code'] == 'SUCCESS' && $result['result_code'] == 'SUCCESS'){
                //进行改变订单状态等操作。。。。

                $orderNum = $result['out_trade_no'];

                $orderModel = new OrderModel();
                //获取订单详情
                $orderInfo = $orderModel->getOrderInfo($orderNum);
                //判断订单是否存在，是否未处理，支付方式是否为微信
                if(empty($orderInfo) || $orderInfo['order_status'] != 0 || $orderInfo['payment_method'] != 1){
                    $returnXml = '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
                    echo $returnXml;exit;
                }

                //微信交易号
                $channelOrderNum = $result['transaction_id'];

                //交易状态
                $tradeStatus = $result['result_code'];

                //订单总金额
                $totalAmount = $result['total_fee'];

                //实收总金额
                $receiptAmount = $result['cash_fee'];

                //判断付款金额是否正确
                if($totalAmount != intval($orderInfo['pay_amount'] * 100)){
                    $returnXml = '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
                    echo $returnXml;exit;
                }

                if($tradeStatus == 'SUCCESS'){
                    //处理订单
                    $res = $orderModel->successOrder($orderNum, $channelOrderNum);
                    if($res){
                        $returnXml = '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
                        echo $returnXml;exit;
                    }
                }
            }
        }
        $returnXml = '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
        echo $returnXml;exit;
    }

    /**
     * 支付宝支付回调处理
     */
    public function aliPayNotify(){
        $notifyParam = $_POST;
//        $str = '{"gmt_create":"2018-12-07 16:43:53","charset":"UTF-8","seller_email":"chenqian@7k7k.com","subject":"\u597d\u6599\u7cbe\u9009-\u54a8\u8be2\u670d\u52a1","sign":"HtGC1dP9aY\/p\/4kyZkMPRwbVY+xcoWAVfF0i\/Je2v0ta2BVcLWItVeAOYB3gyOWMLDtY8QXFicR\/zhviIs3EpHTDhRayI7E5dTswsn7ylyRxgZ0E2fg8bvQkA7+GVxto9QblLWIhAh06GfzItGo7DR9BeT35SrGRctyyo685zFWGu8NSnq12tPkwO0Ak469VX4eJ0\/Ru\/X7ng6es5hlRe0IId6crIrCsmsfiufj313B3Hstj2BKAixv9yC6Kg\/\/U\/iPYV3F7WSOp1OrIJQCodvLwtwueONCpf9tHkKC2CqB\/MzQvpncuvLlURAo8ROo+PZhMcQ+qL8eyfpJCKfVLSw==","body":"\u597d\u6599\u7cbe\u9009-\u54a8\u8be2\u670d\u52a1","buyer_id":"2088702349390344","invoice_amount":"10.00","notify_id":"2018120700222164354090341031910299","fund_bill_list":"[{\"amount\":\"10.00\",\"fundChannel\":\"ALIPAYACCOUNT\"}]","notify_type":"trade_status_sync","trade_status":"TRADE_SUCCESS","receipt_amount":"10.00","buyer_pay_amount":"10.00","app_id":"2018112262311097","sign_type":"RSA2","seller_id":"2088331530883413","gmt_payment":"2018-12-07 16:43:54","notify_time":"2018-12-07 16:43:54","version":"1.0","out_trade_no":"201812071643425082605528","total_amount":"10.00","trade_no":"2018120722001490341009901854","auth_app_id":"2018112262311097","buyer_logon_id":"yan***@163.com","point_amount":"0.00"}';
//        $notifyParam = json_decode($str, true);

        if(empty($notifyParam)){
            echo "fail";
            exit;
        }

        //日志
//        file_put_contents("./alipaylog.txt", date("Y-m-d H:i:s") . "  " . json_encode($notifyParam) . "\r\n", FILE_APPEND);

        $orderNum = $notifyParam['out_trade_no'];

        $orderModel = new OrderModel();
        //获取订单详情
        $orderInfo = $orderModel->getOrderInfo($orderNum);
        //判断订单是否存在，是否未处理，支付方式是否为支付宝
        if(empty($orderInfo) || $orderInfo['order_status'] != 0 || $orderInfo['payment_method'] != 2){
            echo "fail";
            exit;
        }

        $paymentChannel = $orderInfo['payment_channel'];
        $payParams = new PayParams();
        //支付宝支付配置
        $payConfig = $payParams->getAliPayPayConfigByKey($paymentChannel);

//        file_put_contents("./alipaylog.txt", date("Y-m-d H:i:s") . "  " . intval($notifyParam['app_id']) . "\r\n", FILE_APPEND);

        if($notifyParam['app_id'] != $payConfig['appId']){
            echo "fail";
            exit;
        }

        //检测支付宝回调数据
        if ($orderInfo['trade_type'] == 6) {  // 如果支付方式为 app 支付
            $aop = new \AopClient;
            $aop->alipayrsaPublicKey = $payConfig['aliPayPublicKey'];
            $notifyResult = $aop->rsaCheckV1($notifyParam, NULL, "RSA2");
        } else {  // 默认为 web 支付
            $aliPayNotify = new AliPayNotify($payConfig['appId'], $payConfig['merchantPrivateKey'], $payConfig['merchantPublicKey'], $payConfig['aliPayPublicKey'], $payConfig['notifyUrl'], $payConfig['returnUrl']);
            $notifyResult = $aliPayNotify->checkNotify($notifyParam);
        }

//        file_put_contents("./alipaylog.txt", date("Y-m-d H:i:s") . "  " . intval($notifyResult) . "\r\n", FILE_APPEND);

        if(!$notifyResult){
            //校验未通过
            echo "fail";
            exit;
        }

        //支付宝交易号
        $channelOrderNum = $notifyParam['trade_no'];

        //交易状态
        $tradeStatus = $notifyParam['trade_status'];

        //订单总金额
        $totalAmount = $notifyParam['total_amount'];

        //判断付款金额是否正确
        if($totalAmount != $orderInfo['pay_amount']){
//            file_put_contents("./alipaylog.txt", date("Y-m-d H:i:s") . "  " . intval(12) . "\r\n", FILE_APPEND);
            echo "fail";
            exit;
        }

        if($tradeStatus == 'TRADE_SUCCESS'){
            //付款完成后，支付宝系统发送该交易状态通知
            //处理订单
            $res = $orderModel->successOrder($orderNum, $channelOrderNum);
        } else if($tradeStatus == 'TRADE_FINISHED'){
            //退款日期超过可退款期限后（如三个月可退款），支付宝系统发送该交易状态通知
        }
        if($res){
            echo "success";
            exit;
        }
        echo "fail";
        exit;
    }

}