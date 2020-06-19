<?php
/**
 * User: WangHui
 * Date: 2018/10/15
 * Time: 下午3:08
 */

namespace QK\HaoLiao\DAL;


class DALUserExpertMoneyChange extends BaseDAL {

    private $_table = 'hl_user_expert_money_change';

    /**
     * 新建金额变更记录
     * @param $params
     * @return int
     */
    public function newChange($params) {
        return $this->insertData($params, $this->_table);
    }

    /**
     * 用户变更记录
     * @param $expertId
     * @param $page
     * @param $size
     * @param int $withDrawId
     * @return array|bool
     */
    public function getUserMoneyChangeListByWithDrawId($expertId, $page, $size, $withDrawId = 0) {
        $start = ($page - 1) * $size;
        $sql = "SELECT `id` FROM `$this->_table` WHERE `expert_id`=$expertId AND `change_type`=1 AND `withdraw_id` = '$withDrawId' LIMIT $start,$size";
        return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 用户变更记录数（后台）
     * @param $expertId
     * @param $withDrawId
     * @return mixed
     */
    public function getUserMoneyChangeCount($expertId, $withDrawId) {
        $sql = "SELECT count(*) FROM `$this->_table` WHERE `expert_id`=$expertId AND `change_type`=1 AND `withdraw_id` = '$withDrawId'";
        return $this->getDB($sql)->executeValue($sql);
    }

    /**
     * 获取金额变更记录信息
     * @param $id
     * @return mixed
     */
    public function getExpertMoneyChangeInfoById($id) {
        $sql = "SELECT `id`,`user_id`, `source`,`change_type`, `pay_amount`, `separate_amount`, `settle_amount`, `change_time` FROM `$this->_table` WHERE `id`=$id";
        return $this->getDB($sql)->executeRow($sql);
    }

    /**
     * 获取提现金额与手续费
     * @param $expertId
     * @return mixed
     */
    public function getWithDrawMoney($expertId) {
        $sql = "SELECT MAX(`id`) AS `id`,SUM(`settle_amount`) AS `amount`,SUM(`separate_amount`) AS `service_amount` FROM `$this->_table` WHERE `expert_id` = '$expertId' AND  `withdraw_id`=0 AND `change_type`=1";
        return $this->getDB($sql)->executeRow($sql);
    }

    /**
     * 写入提现id
     * @param $expertId
     * @param $maxId
     * @param $withDrawId
     * @return int
     */
    public function updateWithDrawId($expertId, $maxId, $withDrawId) {
        $sql = "UPDATE `$this->_table` SET `withdraw_id` = $withDrawId WHERE id<=$maxId AND `change_type`=1 AND `expert_id`=$expertId";
        return $this->getDB($sql)->executeNoResult($sql);
    }

    /**
     * 获取金额变更的最后一条记录
     * @param $expertId
     * @return mixed
     */
    public function getExpertLastChange($expertId){
        $sql = "SELECT `id`, `user_id`, `expert_id`, `change_type`, `source`, `pay_amount`, `separate_amount`, `settle_amount`, `withdraw_id`, `change_time` FROM `$this->_table` WHERE `expert_id` = $expertId ORDER BY `change_time` DESC LIMIT 1";
        return $this->getDB($sql)->executeRow($sql);
    }
}