<?php
/**
 * 用户虚拟币信息表数据处理类
 * User: YangChao
 * Date: 2018/10/30
 */

namespace QK\HaoLiao\DAL;

use QK\WSF\Settings\AppSetting;

class DALUserVcRecord extends BaseDAL {
    protected $_table = "hl_user_vc_record";

    public function __construct(AppSetting $appSetting){
        parent::__construct($appSetting);
    }

    public function createUserVcRecord($data) {
        $this->insertData($data, $this->_table);
        return $this->getInsertId();
    }

    public function getVcRecordByExt($ext_param) {
        return $this->select($this->_table, ['ext_params' => $ext_param]);
    }
}