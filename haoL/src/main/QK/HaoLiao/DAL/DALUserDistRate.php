<?php
/**
 * 分销商费率表sql处理
 * User: YangChao
 * Date: 2018/12/05
 */

namespace QK\HaoLiao\DAL;

class DALUserDistRate extends BaseDAL {
    private $_table = 'hl_user_dist_rate';

    /**
     * 新建分销商费率
     * @param $params
     */
    public function newDistRate($params) {
        $this->insertData($params, $this->_table);
    }

    /**
     * 获取分销商费率
     * @param $distId
     * @return mixed
     */
    public function getDistRateInfo($distId) {
        $timeNow = time();
        $sql = "SELECT `rate_id`, `dist_id`, `rate`, `effect_time`, `create_time` FROM `$this->_table` WHERE `dist_id` = $distId AND `effect_time` < $timeNow ORDER BY `effect_time` DESC LIMIT 1";
        return $this->getDB($sql)->executeRow($sql);
    }

}