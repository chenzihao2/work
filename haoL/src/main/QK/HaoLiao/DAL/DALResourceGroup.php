<?php
/**
 * 料扩展数据处理
 * User: twenj
 * Date: 2019/04/04
 */

namespace QK\HaoLiao\DAL;

use QK\WSF\Settings\AppSetting;
use QK\HaoLiao\Common\StringHandler;

class DALResourceGroup extends BaseDAL {
    protected $_table = "hl_resource_group";

    public function __construct(AppSetting $appSetting) {
        parent::__construct($appSetting);
    }

    /**
     * 新建一个料合买信息
     */
    public function createResourceGroup($groupData) {
        $res = $this->insertData($groupData, $this->_table);
        return $res;
    }

    /**
     * 获取料的合买信息
     */
    public function getResourceGroupInfo($resourceId) {
        $sql = 'SELECT group_id, resource_id, limit_time, num, price, status, over_time FROM ' . $this->_table . ' WHERE `resource_id` = ' . $resourceId;
        return $this->getDB($sql)->executeRow($sql);
    }

    /**
     * 修改料合买信息
     * @param $condition
     * @param $data
     * @return int
     */
    public function updateResourceGroup($condition, $data) {
        if (is_array($data)) {
            $data = StringHandler::newInstance()->getDBUpdateString($data);
        }
        if (is_array($condition)) {
            $condition = StringHandler::newInstance()->getDBUpdateString($condition);
        }
        $sql = "UPDATE `$this->_table` SET " . $data . " WHERE $condition";
        return $this->getDB($sql)->executeNoResult($sql);
    }

    public function lists($condition = '', $fields = '', $order = '', $other = [], $start = 0, $limit = 0) {
        $sql = 'SELECT ' . (empty($fields) ? '*' : $fields) . ' FROM ' . $this->_table;
        if (isset($other['join'])) {
            foreach ($other['join'] as $item) {
                $sql .= ' '. (empty($item[2]) ? 'LEFT' : strtoupper($item[2])) . ' JOIN ' . $item[0] . ' ON ' . $item[1];
            }
        }
        if (!empty($condition)) {
            $sql .= ' WHERE ' . $condition;
        }
        if (!empty($order)) {
            $sql .= ' ORDER BY ' . $order;
        }
        if ($limit != 0) {
            $sql .= ' LIMIT ' . $start . ',' . $limit;
        }
        return $this->getDB($sql)->executeRows($sql);
    }
}