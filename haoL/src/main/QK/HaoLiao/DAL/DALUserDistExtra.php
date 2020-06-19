<?php
/**
 * 分销商扩展表sql处理
 * User: YangChao
 * Date: 2018/12/05
 */

namespace QK\HaoLiao\DAL;

use QK\HaoLiao\Common\StringHandler;

class DALUserDistExtra extends BaseDAL {
    private $_table = 'hl_user_dist_extra';

    /**
     * 新建分销商扩展
     * @param $params
     */
    public function newDistExtra($params) {
        $this->insertData($params, $this->_table);
    }

    /**
     * 获取分销商扩展信息
     * @param $distId
     * @return mixed
     */
    public function getDistExtraInfo($distId) {
        $sql = "SELECT `dist_id`, `income`, `balance`, `withdrawed`, `gain_user` FROM `$this->_table` WHERE `dist_id` = $distId";
        return $this->getDB($sql)->executeRow($sql);
    }

    /**
     * 修改用户可提现金额，已提现金额
     * @param $distId
     * @param $withdrawMoney
     * @return int
     */
    public function updateWithdrawInfo($distId, $withdrawMoney){
        $sql = "UPDATE `$this->_table` set `withdrawed` = `withdrawed` + $withdrawMoney,`balance` = `balance` - $withdrawMoney WHERE `dist_id`=$distId";
        return $this->getDB($sql)->executeNoResult($sql);
    }

    /**
     * 设置自增或者自减分销商扩展信息
     * @param $distId
     * @param $params
     * @return int
     */
    public function setDistExtraIncOrDec($distId, $params){
        $updateString = StringHandler::newInstance()->getDBIncOrDecString($params);
        $sql = "UPDATE `$this->_table` SET $updateString WHERE dist_id = " . $distId;
        return $this->getDB($sql)->executeNoResult($sql);
    }
}