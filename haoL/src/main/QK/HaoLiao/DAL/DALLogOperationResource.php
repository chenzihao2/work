<?php
/**
 * 料相关操作数据处理
 * User: YangChao
 * Date: 2018/10/15
 */

namespace QK\HaoLiao\DAL;


use QK\WSF\Settings\AppSetting;

class DALLogOperationResource extends BaseDAL {
    protected $_table = "hl_log_operation_resource";

    public function __construct(AppSetting $appSetting){
        parent::__construct($appSetting);
    }

    /**
     * 新建料处理记录
     * @param $resourceId
     * @param $operationUserId
     * @param $operationType
     * @return int
     */
    public function createOperationLog($resourceId, $operationUserId, $operationType = 1){
        $operationLog = [];
        $operationLog['resource_id'] = $resourceId;
        $operationLog['operation_user_id'] = $operationUserId;
        $operationLog['operation_type'] = $operationType;
        $operationLog['log_time'] = time();
        $res = $this->insertData($operationLog, $this->_table);
        return $res;
    }

}