<?php
/**
 * 微信登录绑定表
 * User: WangHui
 * Date: 2018/9/29
 * Time: 11:21
 */

namespace QK\HaoLiao\DAL;


class DALUserSubSidiaryWeChat extends BaseDAL {
    protected $_table = 'hl_user_subsidiary_wechat';

    /**
     * 获取用户微信扩展信息
     * @param $userId
     * @return mixed
     */
    public function getUserWeChatInfo($userId){
        $sql = "SELECT `id`, `user_id`, `unionid`, `openid`, `wechat_id` FROM `$this->_table` WHERE `user_id` = $userId AND `wechat_id` = {$GLOBALS['weChatId']} ORDER BY id DESC";
        return $this->getDB($sql)->executeRow($sql);
    }

    public function getWechatSubUsers() {
  $sql = "SELECT `id`, `user_id`, `unionid`, `openid`, `wechat_id` FROM `$this->_table` WHERE `wechat_id` = {$GLOBALS['weChatId']} ORDER BY id DESC";
        return $this->getDB($sql)->executeRows($sql);
    }
    /**
     * 获取用户微信信息（后台提现用）
     * @param $userId
     * @return mixed
     */
    public function getUserWithDrawWeChatInfo($userId){
        $sql = "SELECT `id`, `user_id`, `unionid`, `openid`, `wechat_id` FROM `$this->_table` WHERE `user_id` = $userId LIMIT 1";
        return $this->getDB($sql)->executeValue($sql);
    }

    /**
     * 通过unionId查询用户主id
     * @param $unionId
     * @return mixed
     */
    public function getUserIdByUnionId($unionId) {
        $sql = "select `user_id` from `$this->_table` where `unionid`='$unionId'";
        return $this->getDB($sql)->executeValue($sql);
    }

    /**
     * 账号检查
     * @param $unionId
     * @param $openId
     * @return mixed
     */
    public function accountInfoCheck($unionId, $openId) {
        $sql = "select `id` from `$this->_table` WHERE  `unionid`='$unionId' AND `openid`='$openId'";
        return $this->getDB($sql)->executeValue($sql);
    }

    /**
     * 更新数据
     * @param $id
     * @param $data
     */
    public function updateWeChatUserInfo($id, $data) {
        $this->updateData($id, $data, $this->_table);
    }

    public function updateUserWechatInfo($userId, $updateInfo) {
        $condition = array('user_id' => $userId);
        $this->update($this->_table, $updateInfo, $condition);
    }

    public function updateUserWechatInfoV2($data, $condition) {
      return $this->update($this->_table, $data, $condition);
    }

    /**
     * 新建数据
     * @param $params
     */
    public function newWeChatAccount($params) {
        $this->insertData($params, $this->_table);
    }

    public function getUserByWechat($condition = array(), $fields = array()) {
      return $this->get($this->_table, $condition, $fields);
    }
}
