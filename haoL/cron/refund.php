<?php
/**
 * 不中退款脚本
 * User: WangHui
 * Date: 2018/11/21
 * Time: 下午6:00
 */
require(__DIR__ . "/cron.php");

ini_set('display_errors', 'On');
error_reporting(E_ALL | E_STRICT);

require_once APP_ROOT . '/library/baiduPay/Autoloader.php';

use QK\HaoLiao\Model\UserModel;
use QK\HaoLiao\Model\OrderModel;
use QK\HaoLiao\Model\ResourceModel;
use QK\HaoLiao\Model\RefundModel;
use QK\HaoLiao\Model\RedisModel;
use QK\AliPay\Pay\AliPayRefund;
use QK\WSF\Settings\AppSetting;
use QK\WeChat\Pay\WeChatPayRefund;
use QK\WeChat\WeChatSendMessage;
use QK\HaoLiao\Common\PayParams;
use QK\HaoLiao\Common\WeChatParams;
use QK\WeChat\WeChatToken;

$resourceModel = new ResourceModel();

$refundModel = new RefundModel();
//获取未退款料
$resourceRow = $resourceModel->getRefundResourceId();

$orderModel = new OrderModel();
//$resourceRow = ['resource_id' => 11123, 'refund_type' => 2];
while ($resourceRow) {
    //更新料状态为退款中
    $update['status'] = 1;
    $resourceModel->updateRefundResource($resourceRow['resource_id'], $update);
    $resourceExtraInfo = $resourceModel->getResourceExtraInfo($resourceRow['resource_id']);
    //获取料订单
    switch ($resourceRow['refund_type']) {
        case '3':       // 合买失败
            $orderList = $orderModel->getResourceGroupOrder($resourceRow['resource_id']);
            break;
        default:
            $orderList = $orderModel->getResourceOrder($resourceRow['resource_id']);
    }
        //$orders = [];
        //foreach ($orderList as $k => $v) {
        //      if ($v['order_id'] == 8122) {
        //              $orders[] = $v;
        //      }
        //}
        //$orderList = $orders;
        //var_dump($orderList);die;
    if (!empty($orderList)) {
        refund_tmp($orderList, $resourceRow['refund_type'], $resourceExtraInfo['bet_status']);
    }
    $resourceRow = $resourceModel->getRefundResourceId();
}

//获取可以退款的临时表记录
$refundList = $refundModel->getRefundList();

if (!empty($refundList)) {
    foreach ($refundList AS $ke => $va) {

        //检查退款表是否有记录
        $refundOrder = $refundModel->checkRefundExist($va['order_num']);
        if (!empty($refundOrder)) {
            //已退款,跳过
        } else {
            $trans = $orderModel->initTrans();
            $trans->beginTrans();

            //发起退款请求
            $result = refund($va['order_num'], $va['refund_type']);
            if ($result) {
                //更新订单为已退款
                $orderUpdate['order_status'] = 3;
                $orderUpdate['refund_time'] = $va['refund_time'];
                $orderModel->updateOrder($va['order_num'], $orderUpdate);
            }

            $trans->commit();
        }
    }
}


function refund_tmp($data, $refundType = 2, $betStatus = null) {
    //获取分配时间数组
    $refund_time = getRefundTime(count($data));
    $refundModel = new RefundModel();
    $userModel = new UserModel();
    $appSetting = AppSetting::newInstance(APP_ROOT);
    $weChatId = $GLOBALS['weChatId'] = $appSetting->getConstantSetting('DEFAULT_WECHATID');
    $weChatParams = new WeChatParams();
    $accessToken = weChatToken($weChatId);
    $weChatConfig = $weChatParams->getNewWeChatParams('', $weChatId);
    $appId = $weChatConfig['id'];
    $appKey = $weChatConfig['appKey'];
    $weChatSendMessage = new WeChatSendMessage($appId, $appKey, $accessToken);
    foreach ($data AS $key => $val) {
        //检查退款临时订单是否存在
        $refund_order_tmp = $refundModel->checkRefundTempExist($val['order_num']);
        if (!empty($refund_order_tmp) || $val['pay_amount'] <= 0) {
            continue;
        }
        //退款单号
        $refund = [];
        $refund['order_num'] = $val['order_num'];
        $refund['refund_status'] = 0;
        $refund['create_time'] = time();
        $refund['refund_time'] = isset($refund_time[$key]) ? $refund_time[$key] : time();
        $refund['refund_type'] = $refundType;
        //新建临时退款
        $refundModel->newRefundTemp($refund);
        //退款通知
        switch ($refundType) {
            case '3':
                $subTitle = '方案合买失败';
                $refundReason = '方案合买失败';
                break;
            default:
                $betStatusText = '黑单';
                if ($betStatus == 2) {
                    $betStatusText = '走单';
                }
                $subTitle = '购买的方案被判定为' . $betStatusText;
                $refundReason = '不中退款方案被判定为' . $betStatusText;
        }
        // 模版内容
        $messageData = array();
        $messageData['first'] = [
            'value' => '您' . $subTitle . '，金额退款中，请耐心等待。',
        ];
        //退款金额
        $messageData['keyword1'] = [
            'value' => round($val['pay_amount'] / 100,2) . "元", 'color' => '#ff0000'
        ];
        //退款原因
        $messageData['keyword2'] = [
            'value' => $refundReason, 'color' => '#008fff'
        ];
        //退款时间
        $messageData['keyword3'] = [
            'value' => date("m", time()) . "月" . date("d", time()) . "日 " . date("H:i", time()),
        ];
        //退款方式
        if ($val['payment_method'] == 1) {
            $messageData['keyword4'] = [
                'value' => "微信",
            ];
        } elseif ($val['payment_method'] == 2) {
            $messageData['keyword4'] = [
                'value' => "支付宝",
            ];
        }
        //备注
        $messageData['remark'] = [
            'value' => "退款中，请稍等"
        ];

        // 生产环境模版ID
        $templateId = "XGxq0lLDAll8My-Q8Q_ldWjoF09QIaAwympnuOJHE7k";

        // 开发环境模版ID
        // $templateId = "rErRbIyZXKf5D-yW3Ke2_xNfkvBRZugfekYdW9P4MlE";

        $userId = $val['user_id'];
        // 获取用户微信信息
        $userWeChatInfo = $userModel->getUserWeChatInfo($userId);
        $userOpenId = $userWeChatInfo['openid'];
        if (!empty($userOpenId)) {
            $weChatSendMessage->sendMessage($userOpenId, $templateId, $messageData);
        }
    }
}

/**
 * @param $refundTotal
 * @return array
 */
function getRefundTime($refundTotal) {
    //早8点到晚10点期间退款按正常时间累加,晚10点之后的在第二天早8点开始
    $refund_startTime = time();
    if(date("H", $refund_startTime) < 8 || date("H", $refund_startTime) >= 22) {
      $refund_startTime = strtotime(date("Y-m-d",strtotime("+1 day"))) + 8 * 3600;
    }

    $refund_startTime = $refund_startTime + rand(0, 300) + 30 * 60;   //30分钟留余时间，方便判单错误回滚

    $refundTime = 60;     //每隔1分钟退款
    $refund_time = [];
    for($i = 0; $i < $refundTotal; $i++) {
      $refund_time[] = $refund_startTime + $refundTime * $i;
    }
    return $refund_time;
}
/*function getRefundTime($refundTotal) {
    //当前时间
    $nowTime = time();
    //退款总耗小时数，最大10小时
    $hours = ceil($refundTotal / 10) <= 10 ? ceil($refundTotal / 10) : 10;
    $totalTimes = $hours * 3600;

//    $refundTime = intval($totalTimes / $refundTotal);
    $refundTime = 60;
    $refund_time = [];
    $lastRefundTime = $nowTime + $totalTimes + 10 * 3600;
    for ($i = $nowTime; $i <= $lastRefundTime; $i = $i + $refundTime) {
        if (date("H", $i) >= 8 && date("H", $i) < 22) {
            $refund_time[] = $i;
            if (count($refund_time) == $refundTotal) {
                break;
            }
        } else {
            $i = $i + 10 * 3600;
        }
    }
    return $refund_time;
}*/


/**
 * 获取退款订单号
 * @return string
 */
function refundOrder() {
    return md5(time() . rand(0, 10000));
}

/**
 * 退款操作
 * @param $order
 * @return bool
 * @throws WxPayException
 */
function refund($order, $refundType = 2) {
    //获取订单信息
    $refundModel = new RefundModel();
    $data = $refundModel->getOrderInfo($order);
    $payParams = new PayParams();
    //写入退款信息表
    $refundOrder = refundOrder();
    $refund = array();
    $refund['resource_id'] = $data['order_param'];
    $refund['user_id'] = $data['user_id'];
    $refund['expert_id'] = $data['expert_id'];
    $refund['order_num'] = $data['order_num'];
    $refund['refund_num'] = $refundOrder;
    $refund['refund_amount'] = $data['pay_amount'];
    $refund['refund_type'] = $refundType;
    $refund['refund_status'] = 0;
    $refund['is_manual'] = 0;
    $refund['create_time'] = time();
    $refund['refund_time'] = time();
    $refundModel->newRefund($refund);
    $tempUpdate['refund_status'] = 1;
    $tempUpdate['refund_time'] = time();
    $refundModel->updateTempRefund($data['order_num'], $tempUpdate);
    //申请退款
    if ($data['payment_method'] == 1) {
        // $weChatPayConfig = $payParams->getWeChatPayConfigByParam($data['wechat_id'], $data['payment_channel']);
        $weChatPayConfig = $payParams->getNewWeChatPayConfigByParam($data['payment_channel'], $data['wechat_id']);
        $weChatParams = new WeChatParams();
        $weChatConfig = $weChatParams->getNewWeChatParamsByAppId($data['appid'], $data['wechat_id']);
        $weChatRefund = new WeChatPayRefund($weChatConfig['id'], $weChatConfig['appKey'], $weChatPayConfig['mchId'], $weChatPayConfig['mchSecretKey'], $weChatPayConfig['certPath'], $weChatPayConfig['keyPath']);
        //微信支付退款
        return $weChatRefund->refund($order, $refundOrder, $data['pay_amount']);
    } elseif ($data['payment_method'] == 2) {
        //支付宝支付退款
        //获取支付宝支付配置
        $aliPayConfig = $payParams->getAliPayPayConfigByKey($data['payment_channel']);
        $aliPayRefund = new AliPayRefund($aliPayConfig['appId'], $aliPayConfig['merchantPrivateKey'], $aliPayConfig['merchantPublicKey'], $aliPayConfig['aliPayPublicKey'], $aliPayConfig['notifyUrl'], $aliPayConfig['returnUrl']);
        $result = $aliPayRefund->refund($data['order_num'], (string)round($data['pay_amount'] / 100, 2), "判定黑单");
        if ($result) {
            $update['refund_status'] = 1;
            $update['refund_time'] = time();
            $refundModel->updateRefund($data['order_num'], $update);
        }
        return $result;
    } elseif ($data['payment_method'] == 3) {
        //百度支付退款
        //获取百度支付配置
        $baiDuPayConfig = $payParams->getBaiDuPayPayConfigByKey($data['payment_channel']);

        $requestParams = [];
        $requestParams['method'] = 'nuomi.cashier.applyorderrefund';
        $requestParams['orderId'] = $data['channel_order_num'];
        $requestParams['userId'] = $data['channel_user_id'];
        $requestParams['refundType'] = 1;
        $requestParams['refundReason'] = 'black';
        $requestParams['tpOrderId'] = $data['order_num'];
        $requestParams['appKey'] = $baiDuPayConfig['appKey'];
        $requestParams['applyRefundMoney'] = $data['pay_amount'];
        $requestParams['bizRefundBatchId'] = $refundOrder;

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

        $result = false;
        if($res['errno'] === 0){
            //退款操作
            $update['refund_status'] = 1;
            $update['refund_time'] = time();
            $update['channel_refund_num'] = $res['data']['refundBatchId'];
            $refundModel->updateRefund($data['order_num'], $update);
            $result = true;
        }
        return $result;
    } elseif ($data['payment_method'] == 100) {
        // 虚拟币支付
        $orderModel = new OrderModel();
        $result = $orderModel->orderByVcRefund($data);
        if ($result === false) {
            return false;
        }
        //退款操作
        $update['refund_status'] = 1;
        $update['refund_time'] = time();
        $refundModel->updateRefund($data['order_num'], $update);
        return true;
    }
}

/**
 * 微信AccessToken获取
 * @param $weChatId
 * @return bool|mixed|null|string
 */
function weChatToken($weChatId) {
    $accessTokenRedisKey = "Access_Token_" . $weChatId;
    $redisModel = new RedisModel('wechat');
    $accessToken = $redisModel->redisGet($accessTokenRedisKey);
    if (empty($accessToken)) {
        $weChatParam = new WeChatParams();
        $weChatParams = $weChatParam->getNewWeChatParams('', $weChatId);
        $appId = $weChatParams['id'];
        $appKey = $weChatParams['appKey'];
        $token = new WeChatToken($appId, $appKey);
        $tokenInfo = $token->getToken();
        if (array_key_exists('code', $tokenInfo)) {
            return false;
        } else {
            $redisModel->redisSet($accessTokenRedisKey, $tokenInfo['access_token'], 7150);
            $accessToken = $tokenInfo['access_token'];
        }
    }
    return $accessToken;
}
