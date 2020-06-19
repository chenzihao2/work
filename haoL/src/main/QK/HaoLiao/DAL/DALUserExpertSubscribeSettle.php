<?php
/**
 * 订阅订单结算表
 * User: YangChao
 * Date: 2018/11/23
 */

namespace QK\HaoLiao\DAL;

class DALUserExpertSubscribeSettle extends BaseDAL {
    private $_table = 'hl_user_expert_subscribe_settle';

    /**
     * 创建一个新结算
     * @param $settleData
     * @return bool
     */
    public function setSettle($settleData){
        $res = $this->insertData($settleData, $this->_table);
        return $res;
    }


}