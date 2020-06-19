<?php
/**
 * 退款订单临时表
 * User: WangHui
 * Date: 2018/11/23
 * Time: 上午10:24
 */

namespace QK\HaoLiao\DAL;


use QK\HaoLiao\Common\StringHandler;

class DALOrderRefundTemp extends BaseDAL {
    protected $_table = 'hl_order_refund_temp';

    /**
     * 新建退款临时记录
     * @param $params
     * @return int
     */
    public function newRefund($params) {
        return $this->insertData($params, $this->_table);
    }

    /**
     * 更新退款临时表记录
     * @param $id
     * @param $params
     * @return int
     */
    public function updateRefundTemp($id,$params) {
        $updateString = StringHandler::newInstance()->getDBUpdateString($params);
        $sql = 'UPDATE `'.$this->_table.'` SET '.$updateString."WHERE `order_num`='$id'";
        return $this->getDB($sql)->executeNoResult($sql);
    }
    /**
     * 检查退款订单是否存在
     * @param $order
     * @return mixed
     */
    public function checkRefundTempExist($order) {
        $sql = "select  `refund_id`, `order_num`, `refund_status`, `create_time`,  `refund_time` from `hl_order_refund_temp` WHERE `order_num`='$order'";
        return $this->getDB($sql)->executeRow($sql);
    }

    /**
     * 获取当前时间可以退款的订单
     * @return array|bool
     */
    public function getRefundList() {
        $time = time();
        $sql = "select `refund_id`,  `order_num`,  `refund_status`,  `create_time`,  `refund_time`, `refund_type` from `hl_order_refund_temp` WHERE `refund_status`=0 AND `refund_time`<=$time";
        return $this->getDB($sql)->executeRows($sql);
    }

}