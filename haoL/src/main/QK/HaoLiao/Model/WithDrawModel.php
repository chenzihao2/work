<?php
/**
 * 提现模块
 * User: WangHui
 * Date: 2018/10/15
 * Time: 上午10:53
 */

namespace QK\HaoLiao\Model;


use QK\HaoLiao\DAL\DALUserExpertExtra;
use QK\HaoLiao\DAL\DALUserExpertMoneyChange;
use QK\HaoLiao\DAL\DALUserExpertWithDraw;

class WithDrawModel extends BaseModel {
    private $_dalUserExpertMoneyChange;
    private $_dalUserExpertWithDraw;
    private $_dalUserExpertExtra;
    private $_redisModel;

    public function __construct() {
        parent::__construct();
        $this->_dalUserExpertWithDraw = new DALUserExpertWithDraw($this->_appSetting);
        $this->_redisModel = new RedisModel("withdraw");
    }

    /**
     * 获取提现列表
     * @param $expertId
     * @param $page
     * @param $size
     * @return mixed
     */
    public function getExpertWithDrawList($expertId, $page, $size) {
        $start = ($page - 1) * $size;
        $redisKey = WITHDRAW_LIST . $expertId;
        //根据分值范围获取redis数据
        $withDrawIdList = $this->_redisModel->redisZRangeByScore($redisKey, $start, $start + $size - 1);
        if (empty($withDrawIdList)) {
            //获取mysql数据
            $withDrawList = $this->_dalUserExpertWithDraw->getExpertWithDrawLists($expertId, $page, $size);
            $withDrawIdList = [];
            if (!empty($withDrawList)) {
                foreach ($withDrawList as $key => $val) {
                    //相关数据入redis
                    $withDrawIdList[] = $withDrawId = $val['withdraw_id'];
                    $this->_redisModel->redisZAdd($redisKey, $key + $start, $withDrawId);
                }
            }
        }
        $resultWithDrawList = [];
        if (!empty($withDrawIdList)) {
            foreach ($withDrawIdList as $withDrawId) {
                $resultWithDrawList[] = $this->getWithDrawInfo($withDrawId);
            }
        }
        return $resultWithDrawList;
    }

    /**
     * 用户提现列表
     * @param $expertId
     * @param $page
     * @param $size
     * @param int $withDrawId
     * @return array
     */
    public function getMoneyChangeList($expertId, $page, $size, $withDrawId = 0) {
        $this->_dalUserExpertMoneyChange = new DALUserExpertMoneyChange($this->_appSetting);
        $start = ($page - 1) * $size;
        $redisKey = WITHDRAW_CHANGE_LIST . $expertId . ":" . $withDrawId;
        //根据分值范围获取redis数据
        $changeList = $this->_redisModel->redisZRangeByScore($redisKey, $start, $start + $size - 1);
        if (empty($changeList)) {
            //获取mysql数据
            $changeIdList = $this->_dalUserExpertMoneyChange->getUserMoneyChangeListByWithDrawId($expertId, $page, $size, $withDrawId);
            $changeList = [];
            if (!empty($changeIdList)) {
                foreach ($changeIdList as $key => $val) {
                    //相关数据入redis
                    $changeList[] = $withDrawId = $val['id'];
                    $this->_redisModel->redisZAdd($redisKey, $key + $start, $withDrawId);
                }
            }
        }
        $resultWithDrawList = [];
        if (!empty($changeList)) {
            foreach ($changeList as $id) {
                $resultWithDrawList[] = $this->getChangeInfo($id);
            }
        }
        return $resultWithDrawList;
    }

    /**
     * 用户提现列表 金额变更记录数（后台）
     * @param $expertId
     * @param $withdrawId
     * @return mixed
     */
    public function getMoneyChangeCount($expertId, $withdrawId) {
        $this->_dalUserExpertMoneyChange = new DALUserExpertMoneyChange($this->_appSetting);
        return $this->_dalUserExpertMoneyChange->getUserMoneyChangeCount($expertId,$withdrawId);
    }

    /**
     * 提现申请
     * @param $expertId
     * @param null $subAccount
     * @param int $accountType
     */
    public function withDraw($expertId, $subAccount, $accountType = 3) {
        //获取提现金额，手续费。
        $this->_dalUserExpertMoneyChange = new DALUserExpertMoneyChange($this->_appSetting);
        $this->_dalUserExpertExtra = new DALUserExpertExtra($this->_appSetting);
        $info = $this->_dalUserExpertMoneyChange->getWithDrawMoney($expertId);
        //写入提现表
        $withDrawData = [];
        $withDrawData['expert_id'] = $expertId;
        $withDrawData['subaccount_id'] = $subAccount;
        $withDrawData['service_fee'] = $info['service_amount'];
        $withDrawData['tax_fee'] = 0;//税收暂时没有
        $withDrawData['withdraw_money'] = $info['amount'];
        $withDrawData['is_manual'] = 0;
        $withDrawData['account_type'] = $accountType;
        $withDrawData['withdraw_status'] = 1;
        $withDrawData['withdraw_time'] = time();
        $this->_dalUserExpertWithDraw->newWithDraw($withDrawData);
        $withDrawId = $this->_dalUserExpertWithDraw->getInsertId();
        //更新变更记录表
        $this->_dalUserExpertMoneyChange->updateWithDrawId($expertId, $info['id'], $withDrawId);
        //写入用户扩展表
        $balance = $info['amount'] + $info['service_amount'];
        $this->_dalUserExpertExtra->updateBalance($expertId, $balance);
        //写入变更记录表
        $changeData['expert_id'] = $expertId;
        $changeData['change_type'] = 2;
        $changeData['change_type'] = 5;
        $changeData['source'] = 5;
        $changeData['separate_amount'] = $info['service_amount'];
        $changeData['settle_amount'] = $info['amount'];
        $changeData['total_amount'] = 0;
        $changeData['change_time'] = time();
        $this->_dalUserExpertMoneyChange->newChange($changeData);
        //删除提现列表redisKey
        $WithDrawChangeListRedisKey = WITHDRAW_CHANGE_LIST . $expertId . ":0";
        $WithDrawListRedisKey = WITHDRAW_LIST . $expertId;
        $this->_redisModel->redisDel($WithDrawChangeListRedisKey);
        $this->_redisModel->redisDel($WithDrawListRedisKey);
    }

    /**
     * 获取提现记录详细信息
     * @param $withDrawId
     * @return bool|mixed|null|string
     */
    public function getWithDrawInfo($withDrawId) {
        //获取订单主要信息
        $redisKey = WITHDRAW_INFO . $withDrawId;
        $withDrawInfo = $this->_redisModel->redisGet($redisKey, true);
        if (empty($withDrawInfo)) {
            $withDrawInfo = $this->_dalUserExpertWithDraw->getWithDrawInfo($withDrawId);
            $this->_redisModel->redisSet($redisKey, $withDrawInfo);
        }
        if (!empty($withDrawInfo)) {
            $withDrawInfo['withdraw_money'] = $this->ncPriceFen2Yuan($withDrawInfo['withdraw_money']);
            $withDrawInfo['withdraw_time'] = $this->friendlyDate($withDrawInfo['withdraw_time']);
            $withDrawInfo['status'] = intval($withDrawInfo['withdraw_status']);
        }
        return $withDrawInfo;
    }

    /**
     * 获取金额变更记录
     * @param $id
     * @return bool|mixed|null|string
     */
    public function getChangeInfo($id) {
        //获取订单主要信息
        $redisKey = WITHDRAW_CHANGE_INFO . $id;
        $changeInfo = $this->_redisModel->redisGet($redisKey, true);
        if (empty($changeInfo)) {
            $this->_dalUserExpertMoneyChange = new DALUserExpertMoneyChange($this->_appSetting);
            $changeInfo = $this->_dalUserExpertMoneyChange->getExpertMoneyChangeInfoById($id);
            $this->_redisModel->redisSet($redisKey, $changeInfo);
        }
        if (!empty($changeInfo)) {
            $userModel = new UserModel();
            $userInfo = $userModel->getUserInfo($changeInfo['user_id']);
            $changeInfo['nick_name'] = $userInfo['nick_name'];
            $changeInfo['headimgurl'] = $userInfo['headimgurl'];
            if ($changeInfo['source'] < 3) {
                $changeInfo['order_type'] = 1;
            } else {
                $changeInfo['order_type'] = 2;
            }
            $changeInfo['price'] = $this->ncPriceFen2Yuan($changeInfo['pay_amount']);
            $changeInfo['balance_price'] = $this->ncPriceFen2Yuan($changeInfo['settle_amount']);
            $changeInfo['buy_time'] = $this->friendlyDate($changeInfo['change_time']);
        }
        return $changeInfo;
    }


    /**
     * 提现列表(后台)
     * @param $query
     * @param $page
     * @param $size
     * @return mixed
     */
    public function getWithDrawList($query, $page, $size) {
        $data['list'] =  $this->_dalUserExpertWithDraw->getWithDrawList($query, $page, $size);
        $data['count'] = $this->_dalUserExpertWithDraw->getWithDrawCount($query);
        return $data;
    }

    /**
     * 获取提现状态(后台)
     * @param $withDrawId
     * @return mixed
     */
    public function getWithDrawStatus($withDrawId) {
        return $this->_dalUserExpertWithDraw->getWithDrawStatus($withDrawId);
    }

    /**
     * 更新提现记录(后台)
     * @param $withDrawId
     * @param $params
     * @return int
     */
    public function updateWithDraw($withDrawId,$params) {
        return $this->_dalUserExpertWithDraw->updateWithDraw($withDrawId,$params);
    }

}