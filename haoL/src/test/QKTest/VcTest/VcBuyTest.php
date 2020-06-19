<?php
namespace QKTest\HaoLiaoTest\VcTest;

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

        $res = $orderModel->vcOrder(229, 1, 1, 1, '');
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
        $orderNum = $order[0]['order_num'];

        $orderModel = new OrderModel();
        $res = $orderModel->successOrder($orderNum);

        $this->assertTrue($res);
    }

}
