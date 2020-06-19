<?php
/**
 * User: YangChao
 * Date: 2018/11/17
 */

namespace QK\HaoLiao\DAL;


use QK\HaoLiao\Common\StringHandler;
use QK\WSF\Settings\AppSetting;

class DALComplaint extends BaseDAL
{
	protected $_table = "hl_complaint";

	public function __construct(AppSetting $appSetting) {
		parent::__construct($appSetting);
	}

    /**
     * 创建一个投诉建议
     * @param $params
     * @return mixed
     */
	public function createComplain($params) {
		$this->insertData($params, $this->_table);
        return $this->getInsertId();
	}

    /**
     * 获取投诉建议列表
     * @param $where
     * @param $start
     * @param $size
     * @return array|bool
     */
	public function getComplaintList($where, $start, $size){
	    $sql = "SELECT `complaint_id`, `user_id`, `user_source`, `resource_id`, `content`, `image`, `price`, `complaint_status`, `phone`, `type`, `module_type`, `create_time`, `modify_time` FROM `$this->_table` WHERE 1";
        if(!empty($where)){
            foreach($where as $key => $val){
                if(!empty($val)){
                    if(!in_array($key, ['create_time_start', 'create_time_end'])){
                        $sql .= " AND $key = $val";
                    } elseif ($key == 'create_time_start'){
                        $sql .= " AND `create_time` >= $val";
                    } elseif ($key == 'create_time_end'){
                        $sql .= " AND `create_time` <= $val";
                    }
                }
            }
        }
        $sql .= " ORDER BY `create_time` DESC LIMIT $start, $size";

        return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 获取总数
     * @param $where
     * @return mixed
     */
    public function getComplaintTotal($where){
        $sql = "SELECT COUNT(`complaint_id`) as total FROM `$this->_table` WHERE 1";
        if(!empty($where)){
            foreach($where as $key => $val){
                if(!empty($val)){
                    if(!in_array($key, ['create_time_start', 'create_time_end'])){
                        $sql .= " AND $key = $val";
                    } elseif ($key == 'create_time_start'){
                        $sql .= " AND `create_time` >= $val";
                    } elseif ($key == 'create_time_end'){
                        $sql .= " AND `create_time` <= $val";
                    }
                }
            }
        }

        return $this->getDB($sql)->executeValue($sql);
    }
}