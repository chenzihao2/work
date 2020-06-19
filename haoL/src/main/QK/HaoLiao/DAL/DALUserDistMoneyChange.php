<?php
/**
 * 分销商金额变更表sql处理
 * User: YangChao
 * Date: 2018/12/05
 */

namespace QK\HaoLiao\DAL;

class DALUserDistMoneyChange extends BaseDAL {
    private $_table = 'hl_user_dist_money_change';

    /**
     * 新建金额变更记录
     * @param $params
     * @return mixed
     */
    public function newChange($params){
        $this->insertData($params, $this->_table);
        return $this->getInsertId();
    }

    /**
     * 获取分销商信息
     * @param $distId
     * @return mixed
     */
    public function getDistMoneyChangeList($distId, $where = [], $start = 0, $pageSize = 10) {
        $sql = "SELECT `id`, `user_id`, `dist_id`, `change_type`, `source`, `pay_amount`, `separate_amount`, `settle_amount`, `withdraw_id`, `change_time` FROM `$this->_table` WHERE `dist_id` = $distId";

        if(!empty($where)){
            foreach($where as $key => $val){
                if($val){
                    $sql .= ' AND ' . $key . '=' . $val;
                }
            }
        }

        $sql .= " ORDER BY `change_time` DESC LIMIT $start, $pageSize";
        return $this->getDB($sql)->executeRows($sql);
    }

}