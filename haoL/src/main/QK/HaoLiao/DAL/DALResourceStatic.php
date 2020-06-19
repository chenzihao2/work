<?php
/**
 * 料附件数据处理
 * User: YangChao
 * Date: 2018/10/12
 */

namespace QK\HaoLiao\DAL;

use QK\WSF\Settings\AppSetting;

class DALResourceStatic extends BaseDAL {
    protected $_table = "hl_resource_static";

    public function __construct(AppSetting $appSetting) {
        parent::__construct($appSetting);
    }

    /**
     * 新建一个料内容详情
     * @param $resourceStatic
     * @return int
     */
    public function createResourceStatic($resourceStatic) {
        $res = $this->insertData($resourceStatic, $this->_table);
        if ($res) {
            return (int)$this->getInsertId();
        }
        return $res;
    }

    /**
     * 获取料内容详情信息列表
     * @param $resourceId
     * @param int $detailId
     * @return array|bool
     */
    public function getResourceStaticList($resourceId, $detailId = 0) {
        $sql = "SELECT `static_id`, `resource_id`, `detail_id`, `static_type`, `url`, `sort`, `static_status`, `create_time`, `modify_time` FROM `$this->_table` WHERE `resource_id` = $resourceId AND `static_status`=0";
        if ($detailId) {
            $sql .= " AND `detail_id` = $detailId";
        }
        return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 删除料附件数据（后台）
     * @param $resourceId
     * @return int
     */
    public function deleteResourceStatic($resourceId) {
        $sql = 'UPDATE `'.$this->_table.'` SET `static_status`=1 WHERE `resource_id`='.$resourceId;
        return $this->getDB($sql)->executeNoResult($sql);
    }


}