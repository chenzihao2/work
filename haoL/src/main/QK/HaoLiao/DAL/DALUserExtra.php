<?php
/**
 * 用户扩展信息表数据处理类
 * User: YangChao
 * Date: 2018/10/30
 */

namespace QK\HaoLiao\DAL;


use QK\HaoLiao\Common\StringHandler;
use QK\WSF\Settings\AppSetting;

class DALUserExtra extends BaseDAL {
    protected $_table = "hl_user_extra";

    public function __construct(AppSetting $appSetting){
        parent::__construct($appSetting);
    }

    /**
     * 获取用户信息扩展
     * @param $userId
     * @return mixed
     */
    public function getUserExtraInfo($userId){
        $sql = "SELECT  `user_id`,`pay_amount`,`subscribe_num`,`follow_num` FROM `$this->_table` WHERE `user_id` = $userId";
        return $this->getDB($sql)->executeRow($sql);
    }


    /**
     * 新建一个用户扩展
     * @param $params
     */
    public function newUserExtra($params){
        $this->insertData($params, $this->_table);
    }

    /**
     * 更新用户信息扩展
     * @param $userId
     * @param $params
     * @return int
     */
    public function setUserExtraIncOrDec($userId, $params){
        $updateString = StringHandler::newInstance()->getDBIncOrDecString($params);
        $sql = "UPDATE `$this->_table` SET $updateString WHERE user_id = " . $userId;
        $result = $this->getDB($sql)->executeNoResult($sql);
        return $result;
    }
}