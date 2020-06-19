<?php
/**
 * 料扩展数据处理
 * User: YangChao
 * Date: 2018/10/12
 */

namespace QK\HaoLiao\DAL;

use QK\HaoLiao\Common\StringHandler;
use QK\WSF\Settings\AppSetting;

class DALResourceExtra extends BaseDAL {
    protected $_table = "hl_resource_extra";

    public function __construct(AppSetting $appSetting) {
        parent::__construct($appSetting);
    }

    /**
     * 新建一个料扩展
     * @param $resourceExtra
     * @return int
     */
    public function createResourceExtra($resourceExtra) {
        $res = $this->insertData($resourceExtra, $this->_table);
        return $res;
    }

    /**
     * 修改一个料信息（后台）
     * @param $resourceId
     * @param $data
     * @return int
     */
    public function updateResourceExtra($resourceId, $data) {
        $updateString = StringHandler::newInstance()->getDBUpdateString($data);
        $sql = "UPDATE `$this->_table` SET " . $updateString . " WHERE `resource_id` = $resourceId";
        return $this->getDB($sql)->executeNoResult($sql);
    }

    /**
     * 获取料扩展信息
     * @param $resourceId
     * @return mixed
     */
    public function getResourceExtraInfo($resourceId) {
        $sql = "SELECT `resource_id`, `sold_num`,  `cron_sold_num`, `thresh_num`, `view_num`, `schedule_time`, `schedule_start`, `schedule_end`, `bet_status`, `pack_day`, `delayed_day`, `recommend_desc`, `modify_time` FROM `$this->_table` WHERE `resource_id` = $resourceId";
        return $this->getDB($sql)->executeRow($sql);
    }

    /**
     * 设置自增或者自减料扩展信息
     * @param $resourceId
     * @param $params
     * @return int
     */
    public function setResourceExtraIncOrDec($resourceId, $params) {
        $updateString = StringHandler::newInstance()->getDBIncOrDecString($params);
        $sql = "UPDATE `$this->_table` SET $updateString WHERE resource_id = " . $resourceId;
        return $this->getDB($sql)->executeNoResult($sql);
    }

    public function lists($condition = '', $fields = '', $start = 0, $limit = 0) {
        $sql = 'SELECT ' . (empty($fields) ? '*' : $fields) .
            ' FROM ' . $this->_table;
        if (!empty($condition)) {
            $sql .= ' WHERE ' . $condition;
        }
        if ($limit != 0) {
            $sql .= ' LIMIT ' . $start . ', ' . $limit;
        }
        return $this->getDB($sql)->executeRows($sql);
    }

}