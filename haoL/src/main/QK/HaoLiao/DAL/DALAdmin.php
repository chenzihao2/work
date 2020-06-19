<?php
/**
 * 后台管理用户表
 * User: WangHui
 * Date: 2018/11/8
 * Time: 下午5:11
 */

namespace QK\HaoLiao\DAL;


class DALAdmin extends BaseDAL {
    protected $_table = "hl_admin";
    public function newManage($data) {
        return $this->insertData($data, $this->_table);
    }

    /**
     * 获取管理用户信息
     * @param $userName
     * @return mixed
     */
    public function getManageInfo($userName) {
        $sql = "SELECT `id` AS `uid`,`real_name`,`salt`,`pwd`,`create_time` FROM `$this->_table` WHERE `username`='$userName'";
        return $this->getDB($sql)->executeRow($sql);
    }

}