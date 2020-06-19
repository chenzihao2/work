<?php
/**
 * 料内容详情数据处理
 * User: YangChao
 * Date: 2018/10/12
 */

namespace QK\HaoLiao\DAL;

use QK\WSF\Settings\AppSetting;

class DALResourceDetail extends BaseDAL {
    protected $_table = "hl_resource_detail";

    public function __construct(AppSetting $appSetting){
        parent::__construct($appSetting);
    }

    /**
     * 新建一个料内容详情
     * @param $resourceDetail
     * @return int
     */
    public function createResourceDetail($resourceDetail){
        $res = $this->insertData($resourceDetail, $this->_table);
        if($res){
            return (int) $this->getInsertId();
        }
        return $res;
    }

    /**
     * 获取料内容详情信息列表
     * @param $resourceId
     * @return mixed
     */
    public function getResourceDetailList($resourceId){
        $sql = "SELECT `detail_id`, `resource_id`, `content`, `modify_time` FROM `$this->_table` WHERE `resource_id` = $resourceId AND `detail_status`=0";
        return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 删除料内容信息（后台）
     * @param $ResourceId
     * @return int
     */
    public function deleteResourceDetail($ResourceId) {
        $sql = 'UPDATE `'.$this->_table.'` SET `detail_status`=1 WHERE `resource_id`='.$ResourceId;
        return $this->getDB($sql)->executeNoResult($sql);
    }

}