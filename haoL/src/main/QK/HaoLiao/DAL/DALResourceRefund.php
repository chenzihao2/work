<?php
/**
 * 料退款表管理
 * User: WangHui
 * Date: 2018/11/22
 * Time: 下午3:03
 */

namespace QK\HaoLiao\DAL;


use QK\HaoLiao\Common\StringHandler;

class DALResourceRefund extends BaseDAL {

    protected $_table = "hl_resource_refund";

    /**
     * 新退款料
     * @param $params
     */
    public function newRefund($params) {
        $this->insertData($params,$this->_table);
    }

    /**
     * 获取一个未退款的料id
     * @return mixed
     */
    public function getRefundResourceId() {
        $sql = "SELECT `resource_id`, `refund_type` FROM `$this->_table` WHERE `status`=0 LIMIT 0,1";
        return $this->getDB($sql)->executeRow($sql);
    }

    /**
     * 更新退款料信息
     * @param $resourceId
     * @param $params
     * @return int
     */
    public function updateRefund($resourceId, $params) {
        $updateString = StringHandler::newInstance()->getDBUpdateString($params);
        $sql = 'UPDATE `' . $this->_table . '` SET ' . $updateString . 'WHERE `resource_id`=' . $resourceId;
        return $this->getDB($sql)->executeNoResult($sql);
    }

}