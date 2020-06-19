<?php
/**
 * 退款状态检查
 * User: WangHui
 * Date: 2018/11/24
 * Time: 下午3:06
 */
require(__DIR__ . "/cron.php");

require_once APP_ROOT . '/library/baiduPay/Autoloader.php';

ini_set('display_errors', 'On');
error_reporting(E_ALL | E_STRICT);

use QK\HaoLiao\Common\WeChatParams;
use QK\HaoLiao\Model\RefundModel;
use QK\HaoLiao\Model\UserModel;
use QK\WeChat\Pay\WeChatPayRefund;
use QK\HaoLiao\Common\PayParams;
use QK\AliPay\Pay\AliPayRefund;
use QK\HaoLiao\Model\OrderModel;

//获取为退款成功的订单
$refundModel = new RefundModel();
$payParams = new PayParams();
$orderModel = new OrderModel();
$start= 0;
$refundOrderInfo = $refundModel->getNotOverRefundOrder($start);
while($refundOrderInfo){
    $refundModel = new RefundModel();
    $order = $refundOrderInfo['order_num'];
    $orderInfo = $refundModel->getOrderInfo($order);
    //申请退款
    if ($orderInfo['payment_method'] == 1) {
        $weChatPayConfig = $payParams->getNewWeChatPayConfigByParam($orderInfo['payment_channel'], $orderInfo['wechat_id']);
        $weChatParams = new WeChatParams();
        $weChatConfig = $weChatParams->getNewWeChatParamsByAppId($orderInfo['appid'], $orderInfo['wechat_id']);
        $weChatRefund = new WeChatPayRefund($weChatConfig['id'],$weChatConfig['appKey'],$weChatPayConfig['mchId'],$weChatPayConfig['mchSecretKey'],$weChatPayConfig['certPath'],$weChatPayConfig['keyPath']);
        //微信支付退款
        $result = $weChatRefund->check($order);
        if($result){
            //退款成功，更新退款状态
            $update['refund_status'] = 1;
            $update['refund_time'] = time();
            $refundModel->updateRefund($order, $update);

            //更新订单为已退款
            $orderModel->updateOrder($order, ['order_status' => 3, 'refund_time' => time()]);
        }else{
            //退款失败。检查下一笔订单状态。
            //重新退款
            //微信支付退款
            $refundOrder = $refundOrderInfo['refund_num'];
            $weChatRefund->refund($order, $refundOrder, $orderInfo['pay_amount']);
        }
    } elseif ($orderInfo['payment_method'] == 2) {
//        //支付宝直接检查下一笔订单状态。

        //支付宝支付退款
        //获取支付宝支付配置
        //支付宝重新走退款
        $aliPayConfig = $payParams->getAliPayPayConfigByKey($orderInfo['payment_channel']);

        $aliPayRefund = new AliPayRefund($aliPayConfig['appId'],$aliPayConfig['merchantPrivateKey'],$aliPayConfig['merchantPublicKey'],$aliPayConfig['aliPayPublicKey'],$aliPayConfig['notifyUrl'],$aliPayConfig['returnUrl']);

        $result = $aliPayRefund->refund($orderInfo['order_num'],$orderInfo['pay_amount']/100,"判定黑单");

        if ($result) {
            $update['refund_status'] = 1;
            $update['refund_time'] = time();
            $refundModel->updateRefund($orderInfo['order_num'], $update);

            //更新订单为已退款
            $orderModel->updateOrder($order, ['order_status' => 3, 'refund_time' => time()]);
        }
    } elseif ($orderInfo['payment_method'] == 3) {
        //百度支付退款
        //获取百度支付配置
        $baiDuPayConfig = $payParams->getBaiDuPayPayConfigByKey($orderInfo['payment_channel']);

        $requestParams = [];
        $requestParams['method'] = 'nuomi.cashier.applyorderrefund';
        $requestParams['orderId'] = $orderInfo['channel_order_num'];
        $requestParams['userId'] = $orderInfo['channel_user_id'];
        $requestParams['refundType'] = 2;
        $requestParams['refundReason'] = 'black';
        $requestParams['tpOrderId'] = $orderInfo['order_num'];
        $requestParams['appKey'] = $baiDuPayConfig['appKey'];
        $requestParams['applyRefundMoney'] = $orderInfo['pay_amount'];
        $requestParams['bizRefundBatchId'] = $refundOrderInfo['refund_num'];

        //退款操作
        $rsaPriviateKeyFilePath = $baiDuPayConfig['privateKeyPath'];
        if( !file_exists($rsaPriviateKeyFilePath) || !is_readable($rsaPriviateKeyFilePath)){
            return false;
        }
        $rsaPrivateKey = file_get_contents($rsaPriviateKeyFilePath);

        $rsaSign = NuomiRsaSign::genSignWithRsa($requestParams ,$rsaPrivateKey);
        $requestParams['rsaSign'] = $rsaSign;

        $resStr = \QK\HaoLiao\Common\CommonHandler::newInstance()->httpPost('https://nop.nuomi.com/nop/server/rest', $requestParams);
        $res = json_decode($resStr, true);

        if($res['errno'] === 0){
            //退款操作
            $update['refund_status'] = 1;
            $update['refund_time'] = time();
            $update['channel_refund_num'] = $res['data']['refundBatchId'];
            $refundModel->updateRefund($orderInfo['order_num'], $update);

            //更新订单为已退款
            $orderModel->updateOrder($order, ['order_status' => 3, 'refund_time' => time()]);
        }
    } elseif ($orderInfo['payment_method'] == 100) {
        // 虚拟币支付
        $orderModel = new OrderModel();
        $result = $orderModel->orderByVcRefund($orderInfo);
        if ($result !== false) {
            $update['refund_status'] = 1;
            $update['refund_time'] = time();
            $refundModel->updateRefund($orderInfo['order_num'], $update);

            //更新订单为已退款
            $orderModel->updateOrder($order, ['order_status' => 3, 'refund_time' => time()]);
        }
    }

    $start++;
    $refundOrderInfo = $refundModel->getNotOverRefundOrder($start);
}

