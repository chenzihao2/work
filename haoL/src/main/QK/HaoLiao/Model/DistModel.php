<?php
/**
 * 分销商处理模块
 * User: YangChao
 * Date: 2018/12/05
 */

namespace QK\HaoLiao\Model;


use QK\HaoLiao\Common\StringHandler;
use QK\HaoLiao\DAL\DALUserDist;
use QK\HaoLiao\DAL\DALUserDistExtra;
use QK\HaoLiao\DAL\DALUserDistRate;

class DistModel extends BaseModel {

    private $_dalUserDist;
    private $_dalUserDistExtra;
    private $_dalUserDistRate;

    public function __construct() {
        parent::__construct();
        $this->_dalUserDist = new DALUserDist($this->_appSetting);
        $this->_dalUserDistExtra = new DALUserDistExtra($this->_appSetting);
        $this->_dalUserDistRate = new DALUserDistRate($this->_appSetting);
    }

    /**
     * 新建一个分销商
     * @param $params
     * @return bool
     */
    public function newDist($params) {
        $distParams['dist_id'] = intval($params['dist_id']);
        $distParams['phone'] = StringHandler::newInstance()->stringExecute($params['phone']);
        $distParams['poster'] = $params['poster'];
        $distParams['dist_status'] = 1;
        $distParams['create_time'] = time();
        //主表
        $this->_dalUserDist->newDist($distParams);
        //附表
        $distExtraParams['dist_id'] = intval($params['dist_id']);
        $this->_dalUserDistExtra->newDistExtra($distExtraParams);
        //分成费率
        $distRateParams = [];
        $distRateParams['dist_id']  = $params['dist_id'];
        $distRateParams['rate'] = 0.4;
        $distRateParams['effect_time'] = $distRateParams['create_time'] = time();
        $this->_dalUserDistRate->newDistRate($distRateParams);
        return true;
    }

    /**
     * 获取分销商信息
     * @param $distId
     * @return bool|mixed|null|string
     */
    public function getDistInfo($distId) {
        $redisModel = new RedisModel('expert');
        $redisKey = DIST_INFO . $distId;
        $distInfo = $redisModel->redisGet($redisKey, true);
        if (empty($expectInfo)) {
            $distInfo = $this->_dalUserDist->getDistInfo($distId);
            $redisModel->redisSet($redisKey, $distInfo);
        }
        return $distInfo;
    }

    /**
     * 设置分销商信息
     * @param $distId
     * @return bool|mixed|null|string
     */
    public function setDistInfo($distId, $data) {
        $redisModel = new RedisModel('expert');
        $redisKey = DIST_INFO . $distId;
        $this->_dalUserDist->setDistInfo($distId, $data);
        $redisModel->redisDel($redisKey);
        return true;
    }

    /**
     * 根据手机号获取分销商信息
     * @param $phone
     * @return mixed
     */
    public function getDistInfoByPhone($phone){
        return $this->_dalUserDist->getDistInfoByPhone($phone);
    }

    /**
     * 获取分销商列表
     * @param $where
     * @param $page
     * @param $pageSize
     * @return array
     */
    public function getDistList($where, $page, $pageSize){
        $start = ($page - 1) * $pageSize;
        $distTotal = $this->_dalUserDist->getDistListTotal($where, $start, $pageSize);
        $distList = $this->_dalUserDist->getDistList($where, $start, $pageSize);

        $res = [];
        $res['total'] = $distTotal;
        $res['list'] = $distList;
        return $res;
    }

}