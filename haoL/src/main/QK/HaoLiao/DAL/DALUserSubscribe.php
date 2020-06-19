<?php
/**
 * 用户订阅数据处理
 * User: YangChao
 * Date: 2018/10/19
 */

namespace QK\HaoLiao\DAL;


use QK\WSF\Settings\AppSetting;

class DALUserSubscribe extends BaseDAL {
    protected $_table = "hl_user_subscribe";

    public function __construct(AppSetting $appSetting) {
        parent::__construct($appSetting);
    }

    /**
     * 设置用户订阅信息
     * @param $data
     * @return int
     */
    public function setUserSubscribe($data){
        return $this->insertData($data, $this->_table);
    }

    /**
     * 获取用户订阅信息
     * @param $userId
     * @param $expertId
     * @return mixed
     */
    public function getUserSubscribeByExpertId($userId, $expertId) {
        $sql = "SELECT `id`, `user_id`, `expert_id`, `start_time`, `end_time`, `pay_amount` FROM `$this->_table` WHERE `user_id` = $userId AND `expert_id` = $expertId LIMIT 1";
        return $this->getDB($sql)->executeRow($sql);
    }

    /**
     * 获取用户订阅列表
     * @param $userId
     * @param $page
     * @param $size
     * @return array|bool
     */
    public function getUserSubscribeList($userId, $page = 0, $size = 0) {
        $time = time();
        $sql = "SELECT `user_id`, `expert_id` FROM `$this->_table` WHERE `user_id` = $userId AND `end_time`> $time";
        if($page && $size){
            $start = ($page - 1) * $size;
            $sql .= "  LIMIT $start,$size";
        }
        return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 获取最近的订阅过期时间
     * @param $userId
     * @return mixed
     */
    public function getSubscribeNearEndTime($userId) {
        $time = time();
        $sql = "SELECT MIN(`end_time`) FROM `$this->_table` WHERE `user_id` = $userId AND `end_time`> $time";
        return $this->getDB($sql)->executeValue($sql);
    }

    /**
     * 获取有效期内的订阅列表
     * @param $nowTime
     * @return mixed
     */
    public function getEffectiveSubscribeList($nowTime){
        $sql = "SELECT `id`, `user_id`, `expert_id`, `start_time`, `end_time`, `pay_amount` FROM `$this->_table` WHERE `end_time`>=$nowTime";
        return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 获取专家未过期订阅用户
     * @param $expertId
     * @return array|bool
     */
    public function getExpertSubscribeList($expertId) {
        $nowTime = time();
        $sql = "SELECT `user_id` FROM `$this->_table` WHERE `end_time`>=$nowTime AND `expert_id`=$expertId";
        return $this->getDB($sql)->executeRows($sql);
        
    }

}