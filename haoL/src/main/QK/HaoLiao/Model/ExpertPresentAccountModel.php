<?php
/**
 * 专家提现账户信息处理类
 * User: YangChao
 * Date: 2018/10/11
 */

namespace QK\HaoLiao\Model;


use QK\HaoLiao\DAL\DALUserExpertPresentAccount;

class ExpertPresentAccountModel extends BaseModel {

    private $_redisModel;
    private $_dalUserExpertPresentAccount;

    public function __construct() {
        parent::__construct();
        $this->_redisModel = new RedisModel("expert");
        $this->_dalUserExpertPresentAccount = new DALUserExpertPresentAccount($this->_appSetting);
    }

    /**
     * 获取专家提现账户列表
     * @param $expertId
     * @return bool|mixed|null|string
     */
    public function getExpertPresentAccountList($expertId) {
        $redisKey = EXPERT_PRESENT_ACCOUNT_LIST . $expertId;
        $expertPresentAccountList = $this->_redisModel->redisGet($redisKey, true);
        if (empty($expertPresentAccountList)) {
            $expertPresentAccountList = $this->_dalUserExpertPresentAccount->getExpertPresentAccountList($expertId);
            $this->_redisModel->redisSet($redisKey, $expertPresentAccountList);
        }
        return $expertPresentAccountList;
    }

    /**
     * 获取专家提现账户（后台提现）
     * @param $expertId
     * @param $type 1支付宝2银行卡
     * @return array|bool
     */
    public function getPresentAccount($expertId, $type) {
        return $this->_dalUserExpertPresentAccount->getExpertPresentAccount($expertId,$type);
    }

    /**
     * 更新专家提现信息(后台)
     * @param $expertId
     * @param $type
     * @param $params
     */
    public function updateExpertPresentAccount($expertId,$type,$params) {
        $this->_dalUserExpertPresentAccount->updateExpertPresentAccount($expertId,$type,$params);
        $redisKey = EXPERT_PRESENT_ACCOUNT_LIST . $expertId;
        $this->_redisModel->redisDel($redisKey);
    }
}