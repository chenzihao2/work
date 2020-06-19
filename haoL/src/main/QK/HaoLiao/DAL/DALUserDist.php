<?php
/**
 * 分销商表sql处理
 * User: YangChao
 * Date: 2018/12/05
 */

namespace QK\HaoLiao\DAL;


use QK\HaoLiao\Common\StringHandler;

class DALUserDist extends BaseDAL {
    private $_table = 'hl_user_dist';

    /**
     * 新建专家
     * @param $params
     */
    public function newDist($params) {
        $this->insertData($params, $this->_table);
    }

    /**
     * 修改专家信息
     * @param $expert
     * @param $data
     * @return int
     */
    public function updateDist($expert, $data) {
        $updateString = StringHandler::newInstance()->getDBUpdateString($data);
        $sql = "UPDATE `$this->_table` SET " . $updateString . " WHERE `expert_id`=$expert";
        return $this->getDB($sql)->executeNoResult($sql);
    }
    /**
     * 获取分销商信息
     * @param $distId
     * @return mixed
     */
    public function getDistInfo($distId) {
        $sql = "SELECT `dist_id`, `phone`, `dist_name`, `dist_address`, `poster`, `dist_status`, `create_time`, `check_time`, `modify_time` FROM `$this->_table` WHERE `dist_id` = $distId";
        return $this->getDB($sql)->executeRow($sql);
    }

    /**
     * 根据手机号获取分销商信息
     * @param $phone
     * @return mixed
     */
    public function getDistInfoByPhone($phone){
        $sql = "SELECT `dist_id`, `phone`, `dist_name`, `dist_address`, `poster`, `dist_status`, `create_time`, `check_time`, `modify_time` FROM `$this->_table` WHERE `phone` = $phone";
        return $this->getDB($sql)->executeRow($sql);
    }

    /**
     * 设置分销商信息
     * @param $distId
     * @param $data
     * @return int
     */
    public function setDistInfo($distId, $data) {
        $updateString = StringHandler::newInstance()->getDBUpdateString($data);
        $sql = "UPDATE `$this->_table` SET " . $updateString . " WHERE `dist_id`=$distId";
        return $this->getDB($sql)->executeNoResult($sql);
    }

    /**
     * 获取分销商列表总数
     * @param $where
     * @return array|bool
     */
    public function getDistListTotal($where){
        $sql = "SELECT COUNT(`dist_id`)  AS `total` FROM `$this->_table` WHERE 1";
        if(!empty($where)){
            foreach($where as $key => $val){
                if(!empty($val)){
                    $sql .= " AND $key=$val";
                }
            }
        }
        return $this->getDB($sql)->executeValue($sql);
    }

    /**
     * 获取分销商列表
     * @param $where
     * @param $start
     * @param $pageSize
     * @return array|bool
     */
    public function getDistList($where, $start, $pageSize){
        $sql = "SELECT `dist_id`, `phone`, `dist_name`, `dist_address`, `poster`, `dist_status`, `create_time`, `check_time`, `modify_time` FROM `$this->_table` WHERE 1";
        if(!empty($where)){
            foreach($where as $key => $val){
                if(!empty($val)){
                    $sql .= " AND $key=$val";
                }
            }
        }
        $sql .= " ORDER BY `create_time` DESC LIMIT $start, $pageSize";
        return $this->getDB($sql)->executeRows($sql);
    }

}