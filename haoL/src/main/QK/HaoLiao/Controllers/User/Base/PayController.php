<?php
/**
 * 支付相关接口
 * User: YangChao
 * Date: 2018/10/23
 */

namespace QK\HaoLiao\Controllers\User\Base;

require_once APP_ROOT . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'baiduPay' . DIRECTORY_SEPARATOR . 'Autoloader.php';

use QK\HaoLiao\Common\AliPayParams;
use QK\HaoLiao\Controllers\User\UserController;
use QK\HaoLiao\Model\OrderModel;

class PayController extends UserController {

    /**
     * 统一支付下单
     */
    public function unifiedOrder(){
        $this->checkToken();
        $param = $this->checkApiParam(['user_id', 'order_type', 'payment_method', 'trade_type', 'order_param'], ['pay_return_url' => '', 'coupon_id' => 0,'platform'=>0,'channel'=>0]);
//        $param = ['user_id' => 2, 'order_type' => 1, 'payment_method' => 2, 'trade_type' => 5, 'order_param' => 4];
//        $param = ['user_id' => 2, 'order_type' => 2, 'payment_method' => 2, 'trade_type' => 5, 'order_param' => 17];
        //用户ID
        $userId = intval($param['user_id']);
        //订单类型  1:订阅 2:买料
        $orderType = intval($param['order_type']);
        //支付方式  1:微信支付   2:支付宝   3：百度支付 4: apple pay  100: 虚拟币支付
        $paymentMethod = intval($param['payment_method']);
        //交易类型  1:微信公众号支付、2:微信原生扫码支付、3:微信APP支付、4:支付宝PC支付、5:支付宝H5支付 6:支付宝APP支付 7:百度小程序
        $tradeType = intval($param['trade_type']);
        //购买参数 料ID/订阅专家ID
        $orderParam = intval($param['order_param']);
        //支付完成回调链接
        $payReturnUrl = $param['pay_return_url'];
        //用户优惠券
        $coupon_id = $param['coupon_id'];
        //平台： Android、iOS
        $platform = $param['platform'];
        //渠道
        $channel = $param['channel'];

        $orderModel = new OrderModel();
        $orderRes = $orderModel->resourceOrder($userId, $orderType, $paymentMethod, $tradeType, $orderParam, $payReturnUrl, $coupon_id, $platform, $channel);

        if ($orderRes['status_code'] == 200) {
            $this->responseJson($orderRes['data']);
        } else {
            $this->responseJsonError($orderRes['status_code'], $orderRes['msg']);
        }

    }

    /**
     * 检测订单状态
     */
    public function checkOrder(){
        $this->checkToken();
        $params = $this->checkApiParam(['user_id', 'order_num']);
        $userId = intval($params['user_id']);
        $orderNum = trim($params['order_num']);

        // 获取订单信息
        $orderModel = new OrderModel();
        $orderInfo = $orderModel->getOrderInfo($orderNum);

        if(empty($orderInfo)){
            $this->responseJsonError(3008);
        }

        if($orderInfo['user_id'] != $userId){
            $this->responseJsonError(3008);
        }
        $res = [];
        $res['order_num'] = $orderInfo['order_num'];
        $res['order_type'] = $orderInfo['order_type'];
        $res['user_id'] = $orderInfo['user_id'];
        $res['expert_id'] = $orderInfo['expert_id'];
        $res['order_param'] = $orderInfo['order_param'];
        $res['order_status'] = $orderInfo['order_status'];

        $this->responseJson($res);
    }

}
