<?php

/**
 * 虚拟货币管理
 */

namespace QK\HaoLiao\Controllers\Console\Base;

use QK\HaoLiao\Common\StringHandler;
use QK\HaoLiao\Controllers\Console\ConsoleController;
use QK\HaoLiao\Model\VcModel;

class VcController extends ConsoleController {

    public function editVcBuyConfig() {

        $params = $this->checkApiParam(['money', 'gift_vc', 'apple_product_id'], ['id' => '', 'tags' => '']);
        $id = $params['id'];
        unset($params['id']);
        $params['money'] = intval($params['money']);
        $params['giftVc'] = intval($params['gift_vc']);
        $params['tags'] = StringHandler::newInstance()->stringExecute($params['tags']);
        $params['apple_product_id'] = StringHandler::newInstance()->stringExecute($params['apple_product_id']);

        $vcModel = new VcModel();
        $res = $vcModel->editVcBuyConfig($id, $params);

        if ($res) {
            $this->responseJson();
        } else {
            $this->responseJsonError(-1, '提交失败');
        }
    }

    public function vcBuyConfigList() {

        $params = $this->checkApiParam([], ['page' => 1, 'pagesize' => 10]);
        $page = intval($params['page']);
        $perpage = intval($params['pagesize']);
        $vcModel = new VcModel();
        $startItem = ($page - 1) * $perpage;
        $list = $vcModel->getVcBuyConfigList($startItem, $perpage);

        return $this->responseJson(empty($list) ? [] : $list);
    }

    public function vcBuyConfigDetail() {
        $params = $this->checkApiParam(['id']);
        $id = intval($params['id']);

        $vcModel = new VcModel();
        $detail = $vcModel->vcBuyConfigDetailById($id);

        if ($detail) {
            $this->responseJson($detail);
        } else {
            $this->responseJsonError(-1, '获取失败');
        }
    }

    public function vcBuyConfigEnable() {

        $params = $this->checkApiParam(['id']);
        $id = intval($params['id']);

        $vcModel = new VcModel();
        $vcModel->vcBuyConfigEnable($id);

        return $this->responseJson();
    }

    public function vcBuyConfigDel() {

        $params = $this->checkApiParam(['id']);
        $id = intval($params['id']);

        $vcModel = new VcModel();
        $vcModel->vcBuyConfigDelById($id);

        return $this->responseJson();
    }
}
