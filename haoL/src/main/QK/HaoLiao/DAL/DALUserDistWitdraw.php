<?php
/**
 * 分销商提现表sql处理
 * User: YangChao
 * Date: 2018/12/05
 */

namespace QK\HaoLiao\DAL;


use QK\HaoLiao\Common\StringHandler;

class DALUserDistWitdraw extends BaseDAL {
    private $_table = 'hl_user_dist_withdraw';

    /**
     * 新增提现申请
     * @param $params
     * @return int
     */
    public function newWithDraw($params) {
        $this->insertData($params, $this->_table);
        return $this->getInsertId();
    }

    /**
     * 获取分销商提现列表总数
     * @param $where
     * @return array|bool
     */
    public function getDistWithDrawListTotal($where){
        $sql = "SELECT COUNT(`withdraw_id`)  AS `total` FROM `$this->_table` WHERE 1";
        if(!empty($where)){
            foreach($where as $key => $val){
                if(!empty($val) && $key == 'withdraw_time_start'){
                    $sql .= " AND withdraw_time >= $val";
                } elseif(!empty($val) && $key == 'withdraw_time_end'){
                    $sql .= " AND withdraw_time < $val";
                } elseif(!empty($val)) {
                    $sql .= " AND $key=$val";
                }
            }
        }
        return $this->getDB($sql)->executeValue($sql);
    }

    /**
     * 获取分销商提现列表
     * @param $where
     * @param $start
     * @param $pageSize
     * @return array|bool
     */
    public function getDistWithDrawList($where, $start, $pageSize){
        $sql = "SELECT `withdraw_id`, `dist_id`, `withdraw_money`, `is_manual`, `withdraw_status`, `withdraw_time`, `check_time`, `complete_time` FROM `$this->_table` WHERE 1";
        if(!empty($where)){
            foreach($where as $key => $val){
                if(!empty($val) && $key == 'withdraw_time_start'){
                    $sql .= " AND withdraw_time >= $val";
                } elseif(!empty($val) && $key == 'withdraw_time_end'){
                    $sql .= " AND withdraw_time < $val";
                } elseif(!empty($val)) {
                    $sql .= " AND $key=$val";
                }
            }
        }
        $sql .= " ORDER BY `withdraw_status` ASC, `withdraw_time` ASC LIMIT $start, $pageSize";
        return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 修改分销商提现状态
     * @param $withdrawId
     * @param $data
     * @return int
     */
    public function setDistWithDraw($withdrawId, $data){
        $updateString = StringHandler::newInstance()->getDBUpdateString($data);
        $sql = "UPDATE `$this->_table` SET " . $updateString . " WHERE `withdraw_id`=$withdrawId";
        return $this->getDB($sql)->executeNoResult($sql);
    }

    /**
     * 获取分销商正在提现数据
     * @param $distId
     * @return array|bool
     */
    public function getDistWithDraw($distId){
        $sql = "SELECT `withdraw_id`, `dist_id`, `withdraw_money`, `is_manual`, `withdraw_status`, `withdraw_time`, `check_time`, `complete_time` FROM `$this->_table` WHERE `dist_id` = $distId AND (`withdraw_status` = 1 OR `withdraw_status` = 2) ORDER BY `withdraw_id` DESC LIMIT 1";
        return $this->getDB($sql)->executeRow($sql);
    }

}