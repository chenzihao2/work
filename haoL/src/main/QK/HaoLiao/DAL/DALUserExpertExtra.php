<?php
/**
 * 专家扩展数据处理类
 * User: WangHui
 * Date: 2018/10/10
 */

namespace QK\HaoLiao\DAL;


use QK\HaoLiao\Common\StringHandler;

class DALUserExpertExtra extends BaseDAL {
    private $_table = "hl_user_expert_extra";

    /**
     * 获取专家扩展数据
     * @param $expertId
     * @return mixed
     */
    public function getExpertExtraInfo($expertId) {
        $sql = "SELECT `expert_id`, `desc`, `income`, `balance`, `withdrawed`, `freezing`, `service_fee`, `discount_service_fee`, `publish_resource_num`, `sold_resource_num`, `subscribe_num`, `follow_num`, `red_num`, `max_red_num`, `max_bet_record`, `max_bet_record_v2`, `profit_rate`, `profit_all`, `profit_resource_num`,`recent_red`, `recent_record` FROM `$this->_table` WHERE `expert_id` = $expertId";
        return $this->getDB($sql)->executeRow($sql);
    }

    /**
     * 新建一个用户附属信息
     * @param $params
     */
    public function newExpertExtra($params) {
        $this->insertData($params, $this->_table);
    }

    /**
     * 设置自增或者自减专家扩展信息
     * @param $expertId
     * @param $params
     * @return int
     */
    public function setExpertExtraIncOrDec($expertId, $params){
        $updateString = StringHandler::newInstance()->getDBIncOrDecString($params);
        $sql = "UPDATE `$this->_table` SET $updateString WHERE expert_id = " . $expertId;
        return $this->getDB($sql)->executeNoResult($sql);
    }

    /**
     * 提现更新余额
     * @param $expertId
     * @param $balance
     */
    public function updateBalance($expertId, $balance) {
        $sql = "UPDATE `$this->_table` set `balance` = `balance` - $balance WHERE `expert_id`=$expertId";
        $this->getDB($sql)->executeNoResult($sql);
    }

    /**
     * 提现后修改已提现金额，以及手续费
     * @param $expertId
     * @param $withdraw
     * @param $service
     * @return int
     */
    public function updateWithdrawInfo($expertId, $withdraw,$service) {
        $sql = "UPDATE `$this->_table` set `withdrawed` = `withdrawed` + $withdraw,`service_fee` = `service_fee` + $service WHERE `expert_id`=$expertId";
        return $this->getDB($sql)->executeNoResult($sql);
    }
    /**
     * 专家扩展信息普通修改
     * @param $expertId
     * @param $params
     * @return int
     */
    public function updateExtra($expertId,$params) {
        $updateString = StringHandler::newInstance()->getDBUpdateString($params);
        $sql = 'UPDATE `'.$this->_table.'` SET '.$updateString.'WHERE `expert_id`='.$expertId;
        return $this->getDB($sql)->executeNoResult($sql);
    }

    /**
     * 专家扩展信息列表获取
     * @param string $condition
     * @param string $fields
     * @param string $order
     * @param int $start
     * @param int $limit
     * @return array|bool
     */
    public function listsExtra($condition = '', $fields = '', $order = '', $start = 0, $limit = 0) {
        $sql = 'SELECT ' . (empty($fields) ? '*' : $fields) .
            ' FROM ' . $this->_table;
        if (!empty($condition)) {
            $sql .= ' WHERE ' . $condition;
        }
        if (!empty($order)) {
            $sql .= ' ORDER BY ' . $order;
        }
        if ($limit != 0) {
            $sql .= ' LIMIT ' . $start . ', ' . $limit;
        }
        return $this->getDB($sql)->executeRows($sql);
    }
}
