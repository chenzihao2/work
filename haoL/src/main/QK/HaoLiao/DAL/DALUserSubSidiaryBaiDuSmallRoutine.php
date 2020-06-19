<?php
/**
 * 百度小程序登录绑定表
 * User: YangChao
 * Date: 2019/02/20
 */

namespace QK\HaoLiao\DAL;


class DALUserSubSidiaryBaiDuSmallRoutine extends BaseDAL {

    protected $_table = 'hl_user_subsidiary_baidu_small_routine';

    /**
     * 通过百度USERID查询用户主ID
     * @param $openId
     * @return mixed
     */
    public function getUserIdByBaiDuOpenId($openId) {
        $sql = "select `user_id` from `$this->_table` where `openid`='$openId'";
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