<?php
/**
 * 后台管理员模块
 * User: WangHui
 * Date: 2018/11/8
 * Time: 下午5:09
 */

namespace QK\HaoLiao\Model;


use QK\HaoLiao\DAL\DALAdmin;

class ManageModel extends BaseModel {
    protected $_DALAdmin;

    public function __construct() {
        parent::__construct();
        $this->_DALAdmin = new DALAdmin($this->_appSetting);
    }


    /**
     * 新建管理员
     * @param $data
     * @return int
     */
    public function newManage($data) {
        return $this->_DALAdmin->newManage($data);
    }

    /**
     * 获取管理员信息
     * @param $username
     * @return mixed
     */
    public function getManageInfo($username) {
        return $this->_DALAdmin->getManageInfo($username);
    }
}