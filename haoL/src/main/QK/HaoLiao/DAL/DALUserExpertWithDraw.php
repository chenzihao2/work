<?php
/**
 * 专家提现记录管理
 * User: WangHui
 * Date: 2018/10/11
 * Time: 17:49
 */

namespace QK\HaoLiao\DAL;


use QK\HaoLiao\Common\StringHandler;

class DALUserExpertWithDraw extends BaseDAL {
    private $_table = 'hl_user_expert_withdraw';

    /**
     * 新建提款请求
     * @param $params
     */
    public function newWithDraw($params) {
        $this->insertData($params, $this->_table);
    }

    /**
     * 获取提现中的记录
     * @param $expertId
     * @return mixed
     */
    public function getWithDrawing($expertId) {
        $sql = "SELECT `withdraw_id`, `expert_id`, `service_fee`, `tax_fee`, `withdraw_money`, `withdraw_time` From `$this->_table` WHERE `expert_id`='$expertId' AND `withdraw_status`<=2";
        return $this->getDB($sql)->executeRow($sql);
    }

    /**
     * 提现记录
     * @param $expertId
     * @param $page
     * @param $size
     * @return mixed
     */
    public function getExpertWithDrawLists($expertId, $page, $size) {
        $start = ($page - 1) * $size;
        $sql = "SELECT `withdraw_id` From `$this->_table` WHERE `expert_id`='$expertId' limit $start,$size";
        return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 获取提现详细信息
     * @param $withDrawId
     * @return mixed
     */
    public function getWithDrawInfo($withDrawId) {
        $sql = "SELECT `withdraw_id`, `expert_id`, `service_fee`, `tax_fee`, `withdraw_money`, `withdraw_time` From `$this->_table` WHERE `withdraw_id`='$withDrawId'";
        return $this->getDB($sql)->executeRow($sql);
    }

    /**
     * 获取提现列表（后台）
     * @param $where
     * @param $page
     * @param $size
     * @return mixed
     */
    public function getWithDrawList($where, $page, $size) {
        $start = ($page - 1) * $size;
        $sql = "select   `withdraw_id`,  `expert_id`,  `subaccount_id`,  `service_fee`,  `tax_fee`,  `withdraw_money`,  `is_manual`,  `account_id`,  `account_type`,  `withdraw_status`,  `withdraw_time`,  `check_time`,  `complete_time` from `$this->_table`WHERE 1";
        if (!empty($where)) {
            foreach ($where as $key => $val) {
                if($key== "start_time"){
                    $sql .= " AND `withdraw_time` >= $val";
                }elseif ($key=="end_time"){
                    $sql .= " AND `withdraw_time` <= $val";
                }else{
                    $sql .= " AND $key = $val";
                }
            }
        }
        $sql .= " LIMIT $start,$size";
        return $this->getDB($sql)->executeRow($sql);
    }

    /**
     * 获取提现列表记录总数
     * @param $where
     * @return mixed
     */
    public function getWithDrawCount($where) {
        $sql = "SELECT COUNT(*) FROM `$this->_table`WHERE 1";
        if (!empty($where)) {
            foreach ($where as $key => $val) {
                if($key== "start_time"){
                    $sql .= " AND `withdraw_time` >= $val";
                }elseif ($key=="end_time"){
                    $sql .= " AND `withdraw_time` <= $val";
                }else{
                    $sql .= " AND $key = $val";
                }
            }
        }
        return intval($this->getDB($sql)->executeValue($sql));
    }

    /**
     * 获取提现状态(后台)
     * @param $withdrawId
     * @return mixed
     */
    public function getWithDrawStatus($withdrawId) {
        $sql = "SELECT `withdraw_status` FROM `$this->_table`WHERE `withdraw_id`=$withdrawId";
        return $this->getDB($sql)->executeValue($sql);
    }

    /**
     * 更新提现记录
     * @param $withdrawId
     * @param $params
     * @return int
     */
    public function updateWithDraw($withdrawId,$params) {
        $updateString = StringHandler::newInstance()->getDBUpdateString($params);
        $sql = "UPDATE `$this->_table` SET " . $updateString . " WHERE `withdraw_id`=$withdrawId";
        return $this->getDB($sql)->executeNoResult($sql);
    }
}