<?php
/**
 * 订单料扩展表
 * User: WangHui
 * Date: 2018/10/12
 * Time: 下午4:59
 */

namespace QK\HaoLiao\DAL;


class DALOrderVcExtra extends BaseDAL {
    private $_table = 'hl_order_vc_extra';


    /**
     * 设置料订单扩展信息
     * @param $orderExtraData
     * @return int
     */
    public function unifiedOrderVcExtra($orderExtraData){
        return $this->insertData($orderExtraData, $this->_table);
    }

    /**
     * 获取料订单扩展数据
     * @param $orderNum
     * @return mixed
     */
    public function getOrderVcExtraInfo($orderNum){
        $sql = "SELECT `order_id`, `order_num`, `buy_amount`, `gift_amount`, `related_order_num` FROM `$this->_table` WHERE `order_num`=$orderNum ";
        return $this->getDB($sql)->executeRow($sql);
    }
}