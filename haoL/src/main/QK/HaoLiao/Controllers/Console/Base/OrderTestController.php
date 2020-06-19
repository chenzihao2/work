<?php
/**
 * 订单状态修改，只在测试服可以使用
 * User: YangChao
 * Date: 2018/11/5
 */

namespace QK\HaoLiao\Controllers\Console\Base;

use QK\HaoLiao\Controllers\BaseController;
use QK\HaoLiao\Model\OrderModel;
use QK\HaoLiao\Common\StringHandler;

class OrderTestController extends BaseController {

    public function baiduOrderPayNotify() {
        $domain = $this->_appSetting->getConstantSetting('DOMAIN_API');
        if ($domain !== 'https://d-api.feiyun.tv/') {
            $this->responseJsonError(-1, '仅供测试使用');
        }

        if (empty($_GET['order_num'])) {
            $html = <<<HTML
<!DOCTYPE>
<html>
<head>
<title>百度订单手动完成</title>
</head>
<body>
<form action="" onsubmit="if(!confirm('确认提交?')) return false;">
<input type="hidden" name="p" value="console">
<input type="hidden" name="c" value="ordertest">
<input type="hidden" name="v" value="1">
<input type="hidden" name="do" value="baiduOrderPayNotify">
<input type="text" name="order_num" placeholder="订单编号">
<input type="submit" value="提交">
</form>
</body>
</html>
HTML;
            echo $html;
            exit;

        }
        $params = $this->checkApiParam(['order_num']);
        $orderNum = StringHandler::newInstance()->stringExecute($params['order_num']);

        $orderModel = new OrderModel();
        //获取订单详情
        $orderInfo = $orderModel->getOrderInfo($orderNum);


        $notifyParam = [
            "unitPrice" => "1",
            "orderId" => $orderNum . 'test',
            "payTime" => time(),
            "dealId" => "2416334941",
            "tpOrderId" => $orderNum,
            "count" => "1",
            "totalMoney" => $orderInfo['order_amount'] * 100,
            "hbBalanceMoney" => "0",
            "userId" => "0",
            "hbMoney" => "0",
            "giftCardMoney" => "0",
            "payMoney" => $orderInfo['pay_amount'] * 100,
            "payType" => "0",
            "partnerId" => "0",
            "rsaSign" => "",
            "status" => "2"
        ];

        if(empty($notifyParam)){
            $this->showResult('error');
        }


        //判断订单是否存在，是否未处理，支付方式是否为百度支付
        if(empty($orderInfo)){
            $this->showResult('订单不存在');
        }
        if($orderInfo['order_status'] != 0){
            $this->showResult('订单已支付');
        }
        if($orderInfo['payment_method'] != 3){
            $this->showResult('订单非百度支付');
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
            $this->showResult('支付金额错误');
        }

        // 1：未支付；2：已支付；-1：订单取消
        if($tradeStatus == 2){
            //处理订单
            $res = $orderModel->successOrder($orderNum, $channelOrderNum, $baiDuUserId);
            if ($res) {
                $this->showResult('操作成功');
            }
        }
        $this->showResult('error');
    }

    public function showResult($msg = 'error') {
        $html = <<<HTML
<!DOCTYPE>
<html>
<head>
<title>百度订单手动完成</title>
</head>
<body>
$msg
<a href="/index.php?p=console&c=ordertest&v=1&do=baiduOrderPayNotify">返回</a>
</form>
</body>
</html>
HTML;
       echo $html;
       exit;
    }
}