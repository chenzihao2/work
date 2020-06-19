<?php
/**
 * 专家提现费率信息处理类
 * User: YangChao
 * Date: 2018/10/30
 */

namespace QK\HaoLiao\Model;

use QK\HaoLiao\DAL\DALUserExpertRate;

class ExpertRateModel extends BaseModel {

    private $_redisModel;
    private $_dalUserExpertRate;

    public function __construct() {
        parent::__construct();
        $this->_redisModel = new RedisModel("expert");
        $this->_dalUserExpertRate = new DALUserExpertRate($this->_appSetting);
    }

    /**
     * 获取专家提现费率
     * @param $expertId
     * @return bool|mixed|null|string
     */
    public function getExpertRate($expertId){
        $redisKey = EXPERT_RATE_INFO . $expertId;
        $rateInfo = $this->_redisModel->redisGet($redisKey, true);
        if(empty($rateInfo)){
            $rateInfo = $this->_dalUserExpertRate->getExpertRate($expertId);
            $this->_redisModel->redisSet($redisKey, $rateInfo);
        }
        return $rateInfo;
    }

    /**
     * 新提现费率
     * @param $params
     * @return int
     */
    public function insertNewRate($params) {
       return $this->_dalUserExpertRate->insertNewRate($params);
    }

}