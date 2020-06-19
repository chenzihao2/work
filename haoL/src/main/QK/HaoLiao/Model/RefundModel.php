<?php
/**
 * 订单退款模块
 * User: WangHui
 * Date: 2018/11/23
 * Time: 上午10:37
 */

namespace QK\HaoLiao\Model;


use QK\HaoLiao\DAL\DALOrder;
use QK\HaoLiao\DAL\DALOrderRefund;
use QK\HaoLiao\DAL\DALOrderRefundTemp;

class RefundModel extends BaseModel {


    /**
     * 新建退款记录
     * @param $params
     * @return int
     */
    public function newRefund($params) {
        $dalRefundTemp = new DALOrderRefund($this->_appSetting);
        return $dalRefundTemp->newRefund($params);
    }

    /**
     * 新建退款临时记录
     * @param $params
     * @return int
     */
    public function newRefundTemp($params) {
        $dalRefundTemp = new DALOrderRefundTemp($this->_appSetting);
        return $dalRefundTemp->newRefund($params);
    }

    /**
     * 更新退款信息
     * @param $id
     * @param $params
     * @return int
     */
    public function updateRefund($id, $params) {
        $dalRefundTemp = new DALOrderRefund($this->_appSetting);
        return $dalRefundTemp->updateRefund($id, $params);
    }

    /**
     * 更新退款临时信息
     * @param $id
     * @param $params
     * @return int
     */
    public function updateTempRefund($id, $params) {
        $dalRefundTemp = new DALOrderRefundTemp($this->_appSetting);
        return $dalRefundTemp->updateRefundTemp($id, $params);
    }

    /**
     * 检查退款订单是否存在
     * @param $order
     * @return mixed
     */
    public function checkRefundExist($order) {
        $dalRefundTemp = new DALOrderRefund($this->_appSetting);
        return $dalRefundTemp->checkRefundExist($order);
    }

    /**
     * 检查退款订单是否存在
     * @param $order
     * @return mixed
     */
    public function checkRefundTempExist($order) {
        $dalRefundTemp = new DALOrderRefundTemp($this->_appSetting);
        return $dalRefundTemp->checkRefundTempExist($order);
    }

    /**
     * 获取当前时间可以退款的订单
     * @return array|bool
     */
    public function getRefundList() {
        $dalRefundTemp = new DALOrderRefundTemp($this->_appSetting);
        return $dalRefundTemp->getRefundList();
    }

    /**
     * 获取订单信息
     * @param $orderNum
     * @return mixed
     */
    public function getOrderInfo($orderNum) {
        $dalOrder = new DALOrder($this->_appSetting);
        return $dalOrder->getOrderInfo($orderNum);
    }


    /**
     * 获取一个未退款成功的订单
     * @param $start
     * @return mixed
     */
    public function getNotOverRefundOrder($start) {
        $dalRefundTemp = new DALOrderRefund($this->_appSetting);
        return $dalRefundTemp->getNotOverRefundOrder($start);
        
    }
}