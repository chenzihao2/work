<?php
/**
 * 战绩推荐数据处理
 * User: YangChao
 * Date: 2018/10/17
 */

namespace QK\HaoLiao\DAL;


use QK\HaoLiao\Common\StringHandler;

class DALStatBetRecordDesc extends BaseDAL {
    private $_table = 'hl_stat_bet_record_desc';

    /**
     * 根据日期获取战绩简介
     * @param $date
     * @return array|bool
     */
    public function getBetRecordDescByDate($date){
        $sql = "SELECT `date`, `desc` FROM `$this->_table` WHERE `date` = '$date'";
        return $this->getDB($sql)->executeRow($sql);
    }


    /**
     * 设置战绩日期推荐简介
     * @param $date
     * @param $desc
     * @return int
     */
    public function setBetRecordDescByDate($date, $desc){
        $params['date'] = $date;
        $params['desc'] = $desc;
        $insertString = StringHandler::newInstance()->getDBInsertString($params);
        $insertKeySql = $insertString['insert'];
        $insertValueSql = $insertString['value'];
        $updateString = StringHandler::newInstance()->getDBUpdateString($params);
        $sql = "INSERT INTO `$this->_table` ($insertKeySql) VALUES ($insertValueSql) ON DUPLICATE KEY UPDATE " . $updateString;
        return $this->getDB($sql)->executeNoResult($sql);
    }
}