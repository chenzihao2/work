<?php
/**
 * 支付相关接口
 */

namespace QK\HaoLiao\Controllers\User\V2;

use QK\HaoLiao\Controllers\User\Base\PayController as Pay;
use QK\HaoLiao\Model\OrderModel;
use QK\HaoLiao\Model\VcModel;

class PayController extends Pay {

    /**
     * 账户余额不足，支付并购买虚拟币
     */
    public function  orderInsufBce() {
        $this->checkToken();
        $param = $this->checkApiParam(['user_id', 'order_type', 'payment_method', 'trade_type', 'order_param', 'vcbc_id'], ['pay_return_url' => '', 'coupon_id' => 0,'platform'=>0,'channel'=>0]);

        //用户ID
        $userId = intval($param['user_id']);
        //订单类型  1:订阅 2:买料 3:文章 4视频
        $orderType = intval($param['order_type']);
        //支付方式  1:微信支付   2:支付宝   3：百度支付   4: 苹果支付   100: 虚拟币支付
        $paymentMethod = intval($param['payment_method']);
        //交易类型  1:微信公众号支付、2:微信原生扫码支付、3:微信APP支付、4:支付宝PC支付、5:支付宝H5支付 6:支付宝APP支付 7:百度小程序
        $tradeType = intval($param['trade_type']);
        //购买参数 料ID/订阅专家ID
        $orderParam = intval($param['order_param']);
        // 充值信息ID
        $vcBuyConfigId = intval($param['vcbc_id']);
        //支付完成回调链接
        $payReturnUrl = $param['pay_return_url'];
        //优惠券id
        $coupon_id = $param['coupon_id'];
        //平台： Android、iOS
        $platform = $param['platform'];
        //渠道
        $channel = $param['channel'];

        $orderModel = new OrderModel();
        $orderRes = $orderModel->resourceOrder($userId, $orderType, 100, 0, $orderParam, '', $coupon_id, $platform, $channel);
        if ($orderRes['status_code'] != 200) {
            $this->responseJsonError($orderRes['status_code'], $orderRes['msg']);
        }

        $orderNum = $orderRes['data']['orderNum'];

        $vcOrderRes = $orderModel->vcOrder($userId, $paymentMethod, $tradeType, $vcBuyConfigId, $payReturnUrl, $orderNum, $platform, $channel);

        if ($vcOrderRes['status_code'] == 200) {
            $this->responseJson($vcOrderRes['data']);
        } else {
            $this->responseJsonError($vcOrderRes['status_code'], $vcOrderRes['msg']);
        }

    }

    public function vcOrder() {

        $this->checkToken();
        $param = $this->checkApiParam(['user_id', 'payment_method', 'trade_type', 'vcbc_id'], ['pay_return_url' => '','platform'=>0,'channel'=>0,'source'=>0]);

        //用户ID
        $userId = intval($param['user_id']);
        //支付方式  1:微信支付   2:支付宝   3：百度支付 4: apple pay
        $paymentMethod = intval($param['payment_method']);
        //交易类型  1:微信公众号支付、2:微信原生扫码支付、3:微信APP支付、4:支付宝PC支付、5:支付宝H5支付 6:支付宝APP支付 7:百度小程序
        $tradeType = intval($param['trade_type']);
        // 充值信息ID
        $vcBuyConfigId = intval($param['vcbc_id']);
        //支付完成回调链接
        $payReturnUrl = $param['pay_return_url'];

        //平台： Android、iOS
        $platform = $param['platform'];
        //渠道
        $channel = $param['channel'];

        //充值来源只做h5页面充值使用：source:1 h5页面支付，0：app
        $source = $param['source'];

        $orderModel = new OrderModel();
        $orderRes = $orderModel->vcOrder($userId, $paymentMethod, $tradeType, $vcBuyConfigId, $payReturnUrl, '',$platform, $channel,$source);

        if ($orderRes['status_code'] == 200) {
            $this->responseJson($orderRes['data']);
        } else {
            $this->responseJsonError($orderRes['status_code'], $orderRes['msg']);
        }

    }

    //充值金额列表列表
    public function amountList() {
        //$this->checkToken();
        $param = $this->checkApiParam(['user_id','token'], []);
        $userId = $param['user_id'];
        if($userId){
            $this->checkToken();
        } else {
            $this->responseJson([]);
        }
        $vcModel=new VcModel();
        $data = $vcModel->amountConfig(true);
        $this->responseJson($data);

    }
}
