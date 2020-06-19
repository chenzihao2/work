<?php
/**
 * 支付回调相关接口
 * User: YangChao
 * Date: 2018/11/13
 */

namespace QK\HaoLiao\Controllers\User\V2;

require_once APP_ROOT . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'baiduPay' . DIRECTORY_SEPARATOR . 'Autoloader.php';

use QK\HaoLiao\Common\PayParams;
use QK\HaoLiao\Controllers\User\Base\PayNotifyController as PayNotify;
use QK\HaoLiao\Model\OrderModel;
use QK\HaoLiao\Model\RefundModel;

class PayNotifyController extends PayNotify {

    /**
     * 百度支付回调处理
     */
    public function baiDuPayNotify(){
        $notifyParam = $_POST;

//        file_put_contents("./baidupaylog.txt", date("Y-m-d H:i:s") . "  " . $_SERVER['REMOTE_ADDR'] . "  "  . json_encode($notifyParam) . "\r\n", FILE_APPEND);

        $response = [
            'errno' => 0,
            'msg' => 'success',
            'data' => [
                'isConsumed' => 2
            ]
        ];

        if(empty($notifyParam)){
            $response['errno'] = 1;
            $response['msg'] = 'error';
            echo json_encode($response);exit;
        }

        //订单号
        $orderNum = $notifyParam['tpOrderId'];

        $orderModel = new OrderModel();
        //获取订单详情
        $orderInfo = $orderModel->getOrderInfo($orderNum);

        //判断订单是否存在，是否未处理，支付方式是否为百度支付
        if(empty($orderInfo) || $orderInfo['order_status'] != 0 || $orderInfo['payment_method'] != 3){
            $response['errno'] = 1;
            $response['msg'] = 'error';
            echo json_encode($response);exit;
        }

        $paymentChannel = $orderInfo['payment_channel'];
        $payParams = new PayParams();
        //百度支付配置
        $payConfig = $payParams->getBaiDuPayPayConfigByKey($paymentChannel);

        if($notifyParam['dealId'] != $payConfig['dealId']){
            $response['errno'] = 1;
            $response['msg'] = 'error';
            echo json_encode($response);exit;
        }

        // 签名验证
        $notifyParam['sign'] = $notifyParam['rsaSign'];
        unset($notifyParam['rsaSign']);
        $baiDuPublicKey =  $payConfig['baiDuPublicKey'];
        $baiDuPublicKey = \NuomiRsaSign::convertRSAKeyStr2Pem($baiDuPublicKey, 0);
        $checkSignRes = \NuomiRsaSign::checkSignWithRsa($notifyParam, $baiDuPublicKey);

        if(!$checkSignRes){
            $response['errno'] = 1;
            $response['msg'] = 'error';
            echo json_encode($response);exit;
        }

        //百度交易号
        $channelOrderNum = $notifyParam['orderId'];

        $baiDuUserId = $notifyParam['userId'];

        //交易状态
        $tradeStatus = $notifyParam['status'];

        //订单总金额
        $totalAmount = $notifyParam['totalMoney'];

        //判断付款金额是否正确
        if($totalAmount != $orderInfo['pay_amount'] * 100){
            $response['errno'] = 1;
            $response['msg'] = 'error';
            echo json_encode($response);exit;
        }

        // 1：未支付；2：已支付；-1：订单取消
        if($tradeStatus == 2){
            //处理订单
            $res = $orderModel->successOrder($orderNum, $channelOrderNum, $baiDuUserId);
            if($res){
                echo json_encode($response);exit;
            }
        }
        $response['errno'] = 1;
        $response['msg'] = 'error';
        echo json_encode($response);exit;
    }

    /**
     * 百度退款审核
     */
    public function baiDuRefundCheckNotify(){
        $notifyParam = $_POST;

//        file_put_contents("./baidupaylog.txt", date("Y-m-d H:i:s") . "  " . $_SERVER['REMOTE_ADDR'] . "  "  . json_encode($notifyParam) . "\r\n", FILE_APPEND);

        $response = [
            'errno' => 0,
            'msg' => 'success',
            'data' => [
                'auditStatus' => 0,
                'calculateRes' => [
                    'refundPayMoney' => 0
                ]
            ]
        ];

        if(empty($notifyParam)){
            $response['errno'] = 1;
            $response['msg'] = 'error';
            echo json_encode($response);exit;
        }

        // 签名验证
        $notifyParam['sign'] = $notifyParam['rsaSign'];
        unset($notifyParam['rsaSign']);
        $baiDuPublicKey =  'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDf1k9rAfSOc8eUn8iDe5vbIMz3ad0EH2TwioU+2JFdIL5uTOMsPB4gh4xgcnM44PEUTrZP5B4E1Lke6gbUQA3exK2WitdH3hyZdm+N3Y3lpH3estr9xtLA8QK1tCsizqdRuaSbD3dh/gaEmId+rzmcz9iVOc/0hem59R1+7PEFtwIDAQAB';
        $baiDuPublicKey = \NuomiRsaSign::convertRSAKeyStr2Pem($baiDuPublicKey, 0);
        $checkSignRes = \NuomiRsaSign::checkSignWithRsa($notifyParam, $baiDuPublicKey);

        if(!$checkSignRes){
            $response['errno'] = 1;
            $response['msg'] = 'error';
            echo json_encode($response);exit;
        }

        $orderNum = $notifyParam['tpOrderId'];

        $refundModel = new RefundModel();
        $refundOrderInfo = $refundModel->checkRefundExist($orderNum);
        if(empty($refundOrderInfo)){
            $response['errno'] = 1;
            $response['msg'] = 'error';
            echo json_encode($response);exit;
        }


        $calculateRes = $refundOrderInfo['refund_amount'];
        $response = [
            'errno' => 0,
            'msg' => 'success',
            'data' => [
                'auditStatus' => 1,
                'calculateRes' => [
                    'refundPayMoney' => $calculateRes
                ]
            ]
        ];

        echo json_encode($response);exit;

    }

    public function baiDuRefundNotify(){
        $response = [
            'errno' => 0,
            'msg' => 'success',
            'data' => [
            ]
        ];

        echo json_encode($response);exit;
    }

    public function applePayVerify() {
      //$params = file_get_contents("php://input");
      //$params = json_decode($params, true);
        $params = $_POST;
        //$params = $this->checkApiParam(['receipt', 'transactionIdentifier']);
        // $productId = $params['productIdentifier'];
        // $state = $params['state'];
        $receipt = $params['receipt'];
        $transactionId = $params['transactionIdentifier'];

        $orderModel = new OrderModel();
        $verifyRes = $orderModel->applePayVerify($receipt, $transactionId);
        if ($verifyRes['status_code'] != 200) {
            $this->responseJsonError($verifyRes['status_code'], $verifyRes['msg']);
        }

        $res = $orderModel->successOrder($transactionId);
        if(!$res){
            $this->responseJsonError(-1, '订单处理失败');
        }
        $this->responseJson();
    }

//    public function testRefund(){
//        $orderNum = '201903011623336471644430';
//
//        //获取订单信息
//        $refundModel = new RefundModel();
//        $data = $refundModel->getOrderInfo($orderNum);
//        $payParams = new PayParams();
//        //百度支付退款
//        //获取百度支付配置
//        $baiDuPayConfig = $payParams->getBaiDuPayPayConfigByKey($data['payment_channel']);
//
//        $requestParams = [];
//        $requestParams['method'] = 'nuomi.cashier.applyorderrefund';
//        $requestParams['orderId'] = $data['channel_order_num'];
//        $requestParams['userId'] = $data['channel_user_id'];
//        $requestParams['refundType'] = 2;
//        $requestParams['refundReason'] = 'black';
//        $requestParams['tpOrderId'] = $data['order_num'];
//        $requestParams['appKey'] = $baiDuPayConfig['appKey'];
//        $requestParams['applyRefundMoney'] = $data['pay_amount'];
//        $requestParams['bizRefundBatchId'] = time();
//
//        //退款操作
//        $rsaPriviateKeyFilePath = $baiDuPayConfig['privateKeyPath'];
//        if( !file_exists($rsaPriviateKeyFilePath) || !is_readable($rsaPriviateKeyFilePath)){
//            return false;
//        }
//        $rsaPrivateKey = file_get_contents($rsaPriviateKeyFilePath);
//
//        $rsaSign = \NuomiRsaSign::genSignWithRsa($requestParams ,$rsaPrivateKey);
//        $requestParams['rsaSign'] = $rsaSign;
//
//        $resStr = CommonHandler::newInstance()->httpPost('https://nop.nuomi.com/nop/server/rest', $requestParams);
//        $res = json_decode($resStr, true);
//        print_r($res);exit;
//    }

}
