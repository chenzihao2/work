<?php
/**
 * 订阅订单扩展表
 * User: YangChao
 * Date: 2018/10/16
 */

namespace QK\HaoLiao\DAL;


class DALOrderSubscribeExtra extends BaseDAL {
    private $_table = 'hl_order_subscribe_extra';

    /**
     * 获取订阅订单扩展数据
     * @param $orderNum
     * @return mixed
     */
    public function getOrderSubscribeExtraInfo($orderNum){
        $sql = "SELECT `order_id`, `order_num`, `expert_id`, `start_time`, `end_time` FROM `$this->_table` WHERE `order_num`=$orderNum ";
        return $this->getDB($sql)->executeRow($sql);
    }

    /**
     * 设置订阅订单扩展信息
     * @param $orderExtraData
     * @return int
     */
    public function unifiedOrderSubscribeExtra($orderExtraData){
        return $this->insertData($orderExtraData, $this->_table);
    }

}