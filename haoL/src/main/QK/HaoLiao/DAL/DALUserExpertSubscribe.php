<?php
/**
 * 专家订阅价格管理信息处理类
 * User: YangChao
 * Date: 2018/10/20
 */

namespace QK\HaoLiao\DAL;


use QK\HaoLiao\Common\StringHandler;

class DALUserExpertSubscribe extends BaseDAL {
    private $_table = "hl_user_expert_subscribe";


    /**
     * 根据时长获取专家订阅价格
     * @param $expertId
     * @param $lengthDay
     * @return mixed
     */
    public function getExpertSubscribeByDays($expertId, $lengthDay) {
        $sql = "SELECT `expert_id`, `subscribe_price`, `length_day` FROM `$this->_table` WHERE `expert_id` = $expertId AND `length_day` = $lengthDay LIMIT 1";
        return $this->getDB($sql)->executeRow($sql);
    }

    /**
     * 更新或新建订阅价格表（后台）
     * @param $params
     * @return int
     */
    public function updateSubscribeInfo($params) {
        $insertString = StringHandler::newInstance()->getDBInsertString($params);
        $insertKeySql = $insertString['insert'];
        $insertValueSql = $insertString['value'];
        unset($params['create_time']);
        $updateString = StringHandler::newInstance()->getDBUpdateString($params);
        $sql = "INSERT INTO `$this->_table` (" . $insertKeySql . ') VALUES (' . $insertValueSql . ') ON DUPLICATE KEY UPDATE ' . $updateString;
        return $this->getDB($sql)->executeNoResult($sql);
    }

}