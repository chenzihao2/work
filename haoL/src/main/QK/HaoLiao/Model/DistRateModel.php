<?php
/**
 * 分销商费率处理模块
 * User: YangChao
 * Date: 2018/12/06
 */

namespace QK\HaoLiao\Model;

use QK\HaoLiao\DAL\DALUserDistRate;

class DistRateModel extends BaseModel {

    private $_dalUserDistRate;

    public function __construct() {
        parent::__construct();
        $this->_dalUserDistRate = new DALUserDistRate($this->_appSetting);
    }

    /**
     * 获取分销商分成费率
     * @param $distId
     * @return bool|mixed|null|string
     */
    public function getDistRateInfo($distId) {
        $distRateInfo = $this->_dalUserDistRate->getDistRateInfo($distId);
        return $distRateInfo;
    }

}