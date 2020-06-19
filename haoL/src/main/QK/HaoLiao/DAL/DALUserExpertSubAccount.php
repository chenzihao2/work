<?php
/**
 * 子账户管理信息处理类
 * User: YangChao
 * Date: 2018/10/10
 */

namespace QK\HaoLiao\DAL;


class DALUserExpertSubAccount extends BaseDAL {
    private $_table = "hl_user_expert_subaccount";

    /**
     * 获取绑定子账户列表
     * @param $expertId
     * @param $start
     * @param $pagesize(0时为取全部数据)
     * @return array|bool
     */
    public function getSubaccountList($expertId, $start, $pagesize) {
        $sql = "SELECT `user_id` FROM `$this->_table` where `expert_id`=$expertId  and `subaccount_status`=1";
        if ($pagesize != 0) {
            $sql .= " limit {$start},{$pagesize}";
        }
        return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 获取绑定子账户总数
     * @param $expertId
     * @return mixed
     */
    public function getSubaccountTotal($expertId) {
        $sql = "select count(`user_id`) as total from `$this->_table` WHERE `expert_id`=$expertId and `subaccount_status`=1";
        return (int)$this->getDB($sql)->executeValue($sql);
    }

    /**
     * 获取绑定详情
     * @param $userId
     * @return mixed
     */
    public function getBindInfo($userId) {
        $sql = "select `user_id`, `expert_id`, `subaccount_status` from `$this->_table` WHERE `user_id`=$userId and `subaccount_status`=1";
        return $this->getDB($sql)->executeRow($sql);
    }

    /**
     * 操作子账户绑定状态
     * @param $userId
     * @param $expertId
     * @param $status
     * @return int
     */
    public function operationBind($userId, $expertId, $status) {
        $sql = 'UPDATE `' . $this->_table . '` SET subaccount_status= ' . $status . ' WHERE user_id=' . $userId . ' and expert_id=' . $expertId;
        return $this->getDB($sql)->executeNoResult($sql);
    }

    /**
     * 设置绑定
     * @param $data
     * @return int
     */
    public function setUserBindInfo($data) {
        return $this->insertData($data, $this->_table);
    }

}