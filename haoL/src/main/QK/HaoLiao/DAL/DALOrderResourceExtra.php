<?php
/**
 * 订单料扩展表
 * User: WangHui
 * Date: 2018/10/12
 * Time: 下午4:59
 */

namespace QK\HaoLiao\DAL;


class DALOrderResourceExtra extends BaseDAL {
    private $_table = 'hl_order_resource_extra';


    /**
     * 设置料订单扩展信息
     * @param $orderExtraData
     * @return int
     */
    public function unifiedOrderResourceExtra($orderExtraData){
        return $this->insertData($orderExtraData, $this->_table);
    }

    /**
     * 获取订单扩展数据
     * @param $orderId
     * @return mixed
     */
    public function getOrderExtraInfo($orderId) {
        $sql = "select `order_id`, `order_num`, `resource_id`, `resource_type`, `bet_status`, `start_time`, `end_time` from `$this->_table` where `order_id`=$orderId ";
        return $this->getDB($sql)->executeRow($sql);
    }

    /**
     * 获取料订单扩展数据
     * @param $orderNum
     * @return mixed
     */
    public function getOrderResourceExtraInfo($orderNum){
        $sql = "SELECT `order_id`, `order_num`, `resource_id`, `resource_type`, `bet_status`, `start_time`, `end_time` FROM `$this->_table` WHERE `order_num`=$orderNum ";
        return $this->getDB($sql)->executeRow($sql);
    }

}