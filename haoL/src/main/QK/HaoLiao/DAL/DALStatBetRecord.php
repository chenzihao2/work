<?php
/**
 * 战绩数据处理
 * User: YangChao
 * Date: 2018/10/17
 */

namespace QK\HaoLiao\DAL;


use QK\HaoLiao\Common\StringHandler;

class DALStatBetRecord extends BaseDAL {
    private $_table = 'hl_stat_bet_record';

    /**
     * 根据日期获取红黑单数据统计
     * @param $date
     * @return array|bool
     */
    public function getBetRecordStatByDate($date) {
        $sql = "SELECT `date`, `match_type`, SUM(`red`) AS `red`, SUM(`go`) AS `go`, SUM(`black`) AS `black` FROM `$this->_table` WHERE `date` = '$date' GROUP BY `match_type`";
        return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 根据日期获取专家的红黑单数据
     * @param $date
     * @return array|bool
     */
    public function getExpertBetRecordStatByDate($date) {
        $sql = "SELECT `date`, `expert_id`, SUM(`red`) AS `red`, SUM(`go`) AS `go`, SUM(`black`) AS `black` FROM `$this->_table` WHERE `date` = '$date' GROUP BY `expert_id`";
        return $this->getDB($sql)->executeRows($sql);
    }

    public function getBetRecordStatByExpertId($expertId, $date) {
        $sql = "SELECT `id`,`date`, `expert_id`, `match_type`, `red`, `go`, `black` FROM `$this->_table` WHERE `expert_id` = $expertId AND `date` = '$date'";
        return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 获取每天专家统计数
     * @param $date
     * @return mixed
     */
    public function getBetRecordTotalByDate($date) {
        $sql = "SELECT COUNT('id') AS `total` FROM `$this->_table` WHERE `date` = '$date'";
        return $this->getDB($sql)->executeValue($sql);
    }


    /**
     * 检查统计数据是否存在
     * @param $expertId
     * @param $date
     * @param $type
     * @return mixed
     */
    public function checkBetRecordExist($expertId, $date, $type) {
        $sql = "SELECT count(*) from `$this->_table` WHERE `expert_id` = $expertId AND `date` = '$date' AND `match_type`= '$type'";
        return $this->getDB($sql)->executeValue($sql);
    }

    /**
     * 统计数据修改
     * @param $expertId
     * @param $time
     * @param $matchType
     * @param $params
     * @return int
     */
    public function setStatIncOrDec($expertId, $time, $matchType, $params) {
        $updateString = StringHandler::newInstance()->getDBIncOrDecString($params);
        $sql = "UPDATE `$this->_table` SET $updateString WHERE `expert_id` = " . $expertId . " AND `date`='$time' AND `match_type`=$matchType";
        return $this->getDB($sql)->executeNoResult($sql);
    }

    /**
     * 新建统计数据
     * @param $params
     * @return int
     */
    public function newStat($params) {
        return $this->insertData($params,$this->_table);
        
    }

    public function updateStat($id, $data)
    {
        return $this->updateData($id, $data, $this->_table);
    }
}