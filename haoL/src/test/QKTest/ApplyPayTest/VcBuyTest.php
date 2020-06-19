<?php
namespace QKTest\HaoLiaoTest\ApplyPayTest;

define('AppRoot', __DIR__ . '/../../../..');
define('APP_ROOT', __DIR__ . '/../../../..');
include __DIR__ . '/../../../../rediskey.php';

use QK\WSF\Settings\AppSetting;
use PHPUnit\Framework\TestCase;
use QK\HaoLiao\DAL\DALOrder;
use QK\HaoLiao\Model\OrderModel;

class VcBuyTest extends TestCase {

    public function VcBuy() {
        $orderModel = new OrderModel();

        // 模拟微信判断
        $_SERVER['HTTP_USER_AGENT'] = 'MicroMessenger';
        $GLOBALS['weChatId'] = 1;

        $res = $orderModel->vcOrder(2296, 4, 0, 1, '');
        $result = $res;

        if (empty($result)) {
            $this->assertEquals(200, '', $res);
        } else {
            $this->assertEquals(200, (isset($result['status_code']) ? $result['status_code'] : ''), isset($result['msg']) ? $result['msg'] : json_encode($result));
        }
    }

    public function VcBuyNotify() {
        $appSetting = AppSetting::newInstance(AppRoot);
        $dalOrder = new DALOrder($appSetting);
        $order = $dalOrder->select('hl_order', ['user_id' => 2296], [], 0, 1, ['order_id' => 'desc']);

        // 参数
        $productId = '123456';
        $state = 'Purchased';
        $receipt = '';
        $transactionId = $order[0]['order_num'];

        $orderModel = new OrderModel();
        $verifyRes = $orderModel->applePayVerify($productId, $state, $receipt, $transactionId);

        $result = $verifyRes;
        if (empty($result)) {
            $this->assertEquals(200, '', $verifyRes);
        } else {
            $this->assertEquals(200, (isset($result['status_code']) ? $result['status_code'] : ''), isset($result['msg']) ? $result['msg'] : json_encode($result));
        }

        $res = $orderModel->successOrder($transactionId);
        $this->assertTrue($res);
    }

}
