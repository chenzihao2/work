<?php
/**
 * 专家扩展信息处理类
 * User: YangChao
 * Date: 2018/10/11
 */

namespace QK\HaoLiao\Model;


use QK\HaoLiao\DAL\DALUserExpertExtra;
use QK\HaoLiao\Model\ExpertModel;

class ExpertExtraModel extends BaseModel {

    private $_redisModel;
    private $_dalUserExpertExtra;

    public function __construct(){
        parent::__construct();
        $this->_redisModel = new RedisModel("expert");
        $this->_dalUserExpertExtra = new DALUserExpertExtra($this->_appSetting);
    }

    /**
     * 设置自增或者自减专家扩展信息
     * @param $expertId
     * @param $params
     * @return int
     */
    public function setExpertExtraIncOrDec($expertId, $params){
        $redisKey = EXPERT_EXTRA_INFO . $expertId;
        $this->_redisModel->redisDel($redisKey);
        return $this->_dalUserExpertExtra->setExpertExtraIncOrDec($expertId, $params);
    }

    /**
     * 获取专家扩展信息
     * @param $expertId
     * @return bool|mixed|null|string
     */
    public function getExpertExtraInfo($expertId){
        $redisKey = EXPERT_EXTRA_INFO . $expertId;
        $expertExtraInfo = $this->_redisModel->redisGet($redisKey, true);
        if(empty($expertExtraInfo)){
            //$expertModel = new ExpertModel();
            //$expertModel->updateStatInfo($expertId);
            $expertExtraInfo = $this->_dalUserExpertExtra->getExpertExtraInfo($expertId);
            $expertExtraInfo['income'] = $this->ncPriceFen2Yuan($expertExtraInfo['income']);
            $expertExtraInfo['balance'] = $this->ncPriceFen2Yuan($expertExtraInfo['balance']);
            $expertExtraInfo['withdrawed'] = $this->ncPriceFen2Yuan($expertExtraInfo['withdrawed']);
            $expertExtraInfo['freezing'] = $this->ncPriceFen2Yuan($expertExtraInfo['freezing']);
            $expertExtraInfo['service_fee'] = $this->ncPriceFen2Yuan($expertExtraInfo['service_fee']);
            $expertExtraInfo['discount_service_fee'] = $this->ncPriceFen2Yuan($expertExtraInfo['discount_service_fee']);
            $this->_redisModel->redisSet($redisKey, $expertExtraInfo);
        }
        return $expertExtraInfo;
    }

    /**
     * 提现后修改已提现金额，以及手续费
     * @param $expertId
     * @param $withDraw
     * @param $service
     * @return int
     */
    public function updateExpertExtraWithdrawInfo($expertId,$withDraw,$service) {
        $result =  $this->_dalUserExpertExtra->updateWithdrawInfo($expertId,$withDraw,$service);
        $redisManageModel = new RedisKeyManageModel('expert');
        $redisManageModel->delExpertKey($expertId);
        return $result;
    }

    /**
     * 专家扩展信息修改
     * @param $expertId
     * @param $params
     * @return int
     */
    public function updateExtra($expertId,$params) {
        $redisKey = EXPERT_EXTRA_INFO . $expertId;
        $this->_redisModel->redisDel($redisKey);
        if (empty($this->_dalUserExpertExtra->getExpertExtraInfo($expertId))) {
            $this->_dalUserExpertExtra->newExpertExtra(['expert_id' => $expertId]);
        }
        return  $this->_dalUserExpertExtra->updateExtra($expertId,$params);
    }

    /**
     * 计算盈利率
     * @param $expertExtraInfo 专家扩展信息
     * @param $odds 料赔率
     * @param $betStatus 料红黑结果
     * @return array|bool 命中率相关信息更新值
     */
    public function countProfitRate(&$expertExtraInfo, $odds, $betStatus) {
        if (intval($odds) < 1) {
            return false;
        }
        $profitAll = $expertExtraInfo['profit_all'];
        $profitResourceNum = $expertExtraInfo['profit_resource_num'];
        switch ($betStatus) {
            case 1:  // 红单
              $profit = floatval($odds) - 1;  // 收益 - 本金
              break;
            case 2:  // 走单
              $profit = 0;
              break;
            case 3:  // 黑单
              $profit = -1;
              break;
            case 4:   //副推红单
              $profit = floatval($odds) - 1;
              break;
            case 5:   //主推，副推半红
            case 6:
              $profit = (floatval($odds) - 1)/2;
              break;
            case 7:   //半黑
              $profit = -0.5;
              break;
        }
        if (isset($profit)) {
            $profitAll += $profit * 100;
            $profitResourceNum++;
        }
        $profitRate = ceil($profitAll / $profitResourceNum);
        return ['profitAll' => $profitAll, 'profitRate' => $profitRate, 'profitResourceNum' => $profitResourceNum];
    }

    /**
     * 统计盈利率计算时料的数量
     * @param $expertId
     */
    // todo 专家端上架时需要更新该数量
    public function getProfitResourceNum($expertId) {
        $resourceModel = new ResourceModel();
        $profitResourceNum = $resourceModel->lists('expert_id = ' . $expertId. ' AND resource_status = 1 AND bet_status <> 0 AND odds >= 1', 'count(*) num', '', ['join' => [['hl_resource_extra', 'hl_resource_extra.resource_id = hl_resource.resource_id']]]);

        $this->updateExtra($expertId, ['profit_resource_num' => empty($profitResourceNum[0]['num']) ? 0 : $profitResourceNum[0]['num']]);
    }
}
