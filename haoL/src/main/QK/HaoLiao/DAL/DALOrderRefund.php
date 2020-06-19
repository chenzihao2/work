<?php
/**
 * 退款订单表
 * User: WangHui
 * Date: 2018/11/23
 * Time: 上午10:24
 */

namespace QK\HaoLiao\DAL;


use QK\HaoLiao\Common\StringHandler;

class DALOrderRefund extends BaseDAL {
    protected $_table = 'hl_order_refund';

    /**
     * 新建退款记录
     * @param $params
     * @return int
     */
    public function newRefund($params) {
        return $this->insertData($params, $this->_table);
    }

    /**
     * 更新退款记录
     * @param $id
     * @param $params
     * @return int
     */
    public function updateRefund($id, $params) {
        $updateString = StringHandler::newInstance()->getDBUpdateString($params);
        $sql = 'UPDATE `' . $this->_table . '` SET ' . $updateString . "WHERE `order_num`='$id'";
        return $this->getDB($sql)->executeNoResult($sql);
    }

    /**
     * 检查退款订单是否存在
     * @param $order
     * @return mixed
     */
    public function checkRefundExist($order) {
        $sql = "select  * from `hl_order_refund` WHERE `order_num`='$order'";
        return $this->getDB($sql)->executeRow($sql);
    }

    /**
     * 获取为退款成功的订单号
     * @param $start
     * @return mixed
     */
    public function getNotOverRefundOrder($start) {
        $sql = "SELECT  `order_num`,`refund_num` FROM `hl_order_refund` WHERE `refund_status`='0' LIMIT $start,1";
        return $this->getDB($sql)->executeRow($sql);
    }
}