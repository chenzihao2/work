<?php
/**
 * 专家提现账户数据处理类
 * User: WangHui
 * Date: 2018/10/11
 */

namespace QK\HaoLiao\DAL;


use QK\HaoLiao\Common\StringHandler;

class DALUserExpertPresentAccount extends BaseDAL {
    private $_table = "hl_user_expert_present_account";

    /**
     * 获取专家提现账户列表
     * @param $expertId
     * @return array|bool
     */
    public function getExpertPresentAccountList($expertId) {
        $sql = "SELECT `account_id`, `expert_id`, `type`, `account`, `bank`, `is_default`, `account_status`, `create_time` FROM `$this->_table` WHERE `expert_id` = $expertId AND `account_status` = 1";
        return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 获取专家提现账户（后台提现）
     * @param $expertId
     * @param $type 1支付宝，2银行卡
     * @return mixed
     */
    public function getExpertPresentAccount($expertId, $type) {
        $sql = "SELECT `account_id`, `expert_id`, `type`, `account`, `bank`, `is_default`, `account_status`, `create_time` FROM `$this->_table` WHERE `expert_id` = $expertId AND `account_status` = 1 AND `type`=$type";
        return $this->getDB($sql)->executeRow($sql);
    }

    /**
     * 新建用户提现账户
     * @param $params
     */
    public function newExpertPresentAccount($params) {
        $this->insertData($params, $this->_table);
    }

    /**
     * 更新专家提现信息(后台)
     * @param $expert
     * @param $type
     * @param $params
     * @return int
     */
    public function updateExpertPresentAccount($expert, $type, $params) {
        $updateString = StringHandler::newInstance()->getDBUpdateString($params);
        $sql = "UPDATE `$this->_table` SET  " . $updateString . " WHERE `expert_id`=$expert AND `type`=$type";
        return $this->getDB($sql)->executeNoResult($sql);
    }
}