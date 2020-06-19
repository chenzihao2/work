<?php
/**
 * 支付相关接口
 */

namespace QK\HaoLiao\Controllers\User\V2;

use QK\HaoLiao\Controllers\User\UserController;
use QK\HaoLiao\Model\OrderModel;
use QK\HaoLiao\Model\VcModel;

class VcController extends UserController {

    public function getBuyConfig() {

        $params = $this->checkApiParam([], ['min_vc' => 0]);

        $minVc = $params['min_vc'];

        $vcModel = new VcModel();
        $buyConfigList = $vcModel->getVcBuyConfigList();

        foreach ($buyConfigList as $key => $value) {
            if (($value['vc'] + $value['gift_vc']) < $minVc) {
                unset($buyConfigList[$key]);
            }
        }
        $this->responseJson(array_values($buyConfigList));
    }
}