<?php
/**
 * 提现模块
 * User: WangHui
 * Date: 2018/10/15
 * Time: 上午10:45
 */

namespace QK\HaoLiao\Controllers\Expert\Base;


use QK\HaoLiao\Controllers\Expert\ExpertController;
use QK\HaoLiao\Model\ExpertWithDrawModel;
use QK\HaoLiao\Model\OrderModel;
use QK\HaoLiao\Model\WithDrawModel;

class WithDrawController extends ExpertController {
    /**
     * 专家订单数据
     */
    public function expertOrderList() {
        $params = $this->checkApiParam(['expert_id', 'order_type'], ['page' => 1, 'pagesize' => 10]);
        $expertId = $params['expert_id'];
        $orderType = intval($params['order_type']);
        $page = intval($params['page']);
        $pageSize = intval($params['pagesize']);
        $orderModel = new OrderModel();
        $orderList = $orderModel->getExpertOrder($expertId, $orderType, $page, $pageSize);
        $this->responseJson($orderList);
    }

    /**
     * 用户提现列表
     */
    public function getBalanceList() {
        $params = $this->checkApiParam(['expert_id'], ['page' => 1, 'pagesize' => 10]);
        $expertId = $params['expert_id'];
        $page = intval($params['page']);
        $pageSize = intval($params['pagesize']);
        $expertWithDrawModel = new ExpertWithDrawModel();
        $withDrawInfo = $expertWithDrawModel->getWithDrawing($expertId);
        //检查是否有提现中的记录
        if (empty($withDrawInfo)) {
            $withDrawModel = new WithDrawModel();
            $list = $withDrawModel->getMoneyChangeList($expertId, $page, $pageSize);
            $this->responseJson($list);
        } else {
            $this->responseJsonError(4001);
        }
    }

    /**
     * 用户提现记录
     */
    public function withDrawList() {
        $params = $this->checkApiParam(['expert_id'], ['page' => 1, 'pagesize' => 10]);
        $expertId = $params['expert_id'];
        $page = intval($params['page']);
        $pageSize = intval($params['pagesize']);
        $withDrawModel = new WithDrawModel();
        $list = $withDrawModel->getExpertWithDrawList($expertId, $page, $pageSize);
        $this->responseJson($list);
    }

    /**
     * 用户提现结算详细信息
     */
    public function getWithDrawDetailList() {
        $params = $this->checkApiParam(['expert_id', 'withdraw_id'], ['page' => 1, 'pagesize' => 10]);
        $expertId = $params['expert_id'];
        $withdrawId = $params['withdraw_id'];
        $page = intval($params['page']);
        $pageSize = intval($params['pagesize']);
        $withDrawModel = new WithDrawModel();
        $list = $withDrawModel->getMoneyChangeList($expertId, $page, $pageSize, $withdrawId);
        $this->responseJson($list);
    }

    /**
     * 提现接口
     */
    public function withDraw() {
        $params = $this->checkApiParam(['expert_id', 'user_id']);
        $subAccount = $params['user_id'];
        $expertId = $params['expert_id'];
        $expertWithDrawModel = new ExpertWithDrawModel();
        $withDrawInfo = $expertWithDrawModel->getWithDrawing($expertId);
        //检查是否有提现中的记录
        if (empty($withDrawInfo)) {
            $withDrawModel = new WithDrawModel();
            $withDrawModel->withDraw($expertId, $subAccount);
            $this->responseJson([]);
        } else {
            $this->responseJsonError(4001);
        }
    }

}