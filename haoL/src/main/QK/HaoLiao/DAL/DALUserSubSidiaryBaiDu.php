<?php
/**
 * 百度登录绑定表
 * User: YangChao
 * Date: 2019/02/13
 */

namespace QK\HaoLiao\DAL;


class DALUserSubSidiaryBaiDu extends BaseDAL {
    protected $_table = 'hl_user_subsidiary_baidu';

    /**
     * 通过百度USERID查询用户主ID
     * @param $baiDuUserId
     * @return mixed
     */
    public function getUserIdByBaiDuUserId($baiDuUserId) {
        $sql = "select `user_id` from `$this->_table` where `baidu_userid`='$baiDuUserId'";
        return $this->getDB($sql)->executeValue($sql);
    }

    /**
     * 更新数据
     * @param $id
     * @param $data
     */
    public function updateBaiDuUserInfo($id, $data) {
        $this->updateData($id, $data, $this->_table);
    }

    /**
     * 新建数据
     * @param $params
     */
    public function newBaiDuAccount($params) {
        $this->insertData($params, $this->_table);
    }
}