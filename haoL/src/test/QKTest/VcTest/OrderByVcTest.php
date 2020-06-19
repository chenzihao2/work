<?php
namespace QKTest\HaoLiaoTest\VcTest;

define('AppRoot', __DIR__ . '/../../../..');
define('APP_ROOT', __DIR__ . '/../../../..');
include __DIR__ . '/../../../../rediskey.php';

use PHPUnit\Framework\TestCase;
use QK\HaoLiao\Model\OrderModel;

class OrderByVcTest extends TestCase {

    public function OrderByVc() {
        $orderModel = new OrderModel();

        // 模拟微信判断
        $_SERVER['HTTP_USER_AGENT'] = 'MicroMessenger';
        $GLOBALS['weChatId'] = 1;

        $res = $orderModel->resourceOrder(2296, 2, 100, 0, 103, '');
        $result = $res;

        if (empty($result)) {
            $this->assertEquals(200, '', $res);
        } else {
            $this->assertEquals(200, (isset($result['status_code']) ? $result['status_code'] : ''), isset($result['msg']) ? $result['msg'] : json_encode($result));
        }
    }

}
