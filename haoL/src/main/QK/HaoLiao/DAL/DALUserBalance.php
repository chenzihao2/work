<?php
/**
 * 用户扩展信息表数据处理类
 * User: YangChao
 * Date: 2018/10/30
 */

namespace QK\HaoLiao\DAL;

use QK\WSF\Settings\AppSetting;

class DALUserBalance extends BaseDAL {
    protected $_table = "hl_user_balance";

    public function __construct(AppSetting $appSetting){
        parent::__construct($appSetting);
    }

    /**
     * 获取用户信息扩展
     * @param $userId
     * @return mixed
     */
    public function getUserBalanceInfo($userId){
        $res = $this->get($this->_table, ['user_id' => $userId], ['user_id', 'vc_balance']);
        return $res;
    }

    public function createUserBalance($data) {
        return $this->insertData($data, $this->_table);
    }

    public function updateUserBalance($condition, $data) {
        return $this->updateByCondition($condition, $data, $this->_table);
    }

}
