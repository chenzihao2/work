<?php
/**
 * 专家提现费率数据处理类
 * User: YangChao
 * Date: 2018/10/30
 */

namespace QK\HaoLiao\DAL;


class DALUserExpertRate extends BaseDAL {

    private $_table = 'hl_user_expert_rate';

    /**
     * 获取专家有效期内的提现费率
     * @param $expertId
     * @return mixed
     */
    public function getExpertRate($expertId) {
        $time = time();
        $sql = "SELECT * FROM `$this->_table` WHERE `expert_id` = $expertId AND `effect_time` < $time ORDER BY `effect_time` DESC LIMIT 1";
        return $this->getDB($sql)->executeRow($sql);
    }

    /**
     * 新建提现费率（后台）
     * @param $params
     * @return int
     */
    public function insertNewRate($params) {
        return $this->insertData($params,$this->_table);
    }
}