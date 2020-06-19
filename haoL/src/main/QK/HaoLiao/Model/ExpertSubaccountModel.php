<?php
/**
 *
 * User: YangChao
 * Date: 2018/10/10
 */

namespace QK\HaoLiao\Model;


use QK\HaoLiao\Common\StringHandler;
use QK\HaoLiao\DAL\DALUserExpertSubAccount;

class ExpertSubaccountModel extends BaseModel {

    private $_redisModel;
    private $_dalUserExpertSubAccount;

    public function __construct(){
        parent::__construct();
        $this->_redisModel = new RedisModel("expert");
        $this->_dalUserExpertSubAccount = new DALUserExpertSubAccount($this->_appSetting);
    }

    /**
     * 获取绑定子账户列表
     * @param $expertId
     * @param $page
     * @param $pagesize(0时为取全部数据)
     * @return array|bool
     */
    public function getSubaccountList($expertId, $page, $pagesize){
        $start = ($page - 1) * $pagesize;
        $subaccountList = $this->_dalUserExpertSubAccount->getSubaccountList($expertId, $start, $pagesize);
        return $subaccountList;
    }

    /**
     * 操作子账户绑定状态
     * @param $userId
     * @param $expertId
     * @param $status
     * @return int
     */
    public function operationBind($userId, $expertId, $status){
        $redisKey = EXPERT_SUBACCOUNT_INFO.$userId;
        $res = $this->_dalUserExpertSubAccount->operationBind($userId, $expertId, $status);
        $this->_redisModel->redisDel($redisKey);
        return $res;
    }

    /**
     * 生成唯一邀请码
     * @param $expertId
     * @return string
     */
    public function setBindOnlyCode($expertId){
        $onlyCode = StringHandler::newInstance()->getInvitationOnlyCode();
        $this->_redisModel->redisSet($onlyCode, $expertId, 1800);
        return $onlyCode;
    }

    /**
     * 根据唯一邀请码获取邀请主账户
     * @param $onlyCode
     * @return bool|mixed|null|string
     */
    public function getExpertIdByBindOnlyCode($onlyCode){
        $expertId = $this->_redisModel->redisGet($onlyCode);
        return $expertId;
    }

    /**
     * 失效唯一邀请码
     * @param $onlyCode
     * @return int
     */
    public function delBindOnlyCode($onlyCode){
        return $this->_redisModel->redisDel($onlyCode);
    }

    /**
     * 获取用户绑定信息
     * @param $userId
     * @return bool|mixed|null|string
     */
    public function getUserBindInfo($userId){
        $redisKey = EXPERT_SUBACCOUNT_INFO.$userId;
        $bindInfo = $this->_redisModel->redisGet($redisKey, true);
        if (empty($bindInfo)) {
            $bindInfo = $this->_dalUserExpertSubAccount->getBindInfo($userId);
            $this->_redisModel->redisSet($redisKey, $bindInfo);
        }
        return $bindInfo;
    }


    /**
     * 获取绑定子账户总数
     * @param $expertId
     * @return mixed
     */
    public function getSubaccountTotal($expertId){
        return $this->_dalUserExpertSubAccount->getSubaccountTotal($expertId);
    }

    /**
     * 设置绑定
     * @param $data
     * @return mixed
     */
    public function setUserBindInfo($data){
        return $this->_dalUserExpertSubAccount->setUserBindInfo($data);
    }

}