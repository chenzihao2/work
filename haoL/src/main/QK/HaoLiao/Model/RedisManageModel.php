<?php
/**
 * redis管理
 * User: WangHui
 * Date: 2018/11/6
 * Time: 下午3:37
 */

namespace QK\HaoLiao\Model;

class RedisManageModel extends BaseModel {
    private $_redisModel;

    public function __construct($selectDB) {
        parent::__construct();
        if ($selectDB != null) {
            $this->_redisModel = new RedisModel($selectDB);
        }
    }

    /**
     * 获取列表
     */
    public function getList($key, $order, $page, $limit) {
        $listKeys = $this->getListKey($key, $order, $page);
        $redisKey = $listKeys['redisKey'];
        $fieldLimitKey = $listKeys['fieldLimitKey'];
        $fieldDataKey = $listKeys['fieldDataKey'];
        $redisLimit = $this->_redisModel->redisGetHashList($redisKey, $fieldLimitKey);
        $refresh = 0;
        if (is_null($redisLimit) || $redisLimit === false || $limit != $redisLimit) {
            $this->delList($key);
            $refresh = 1;
        } else {
            $redisData = $this->_redisModel->redisGetHashList($redisKey, $fieldDataKey, true);
            if (is_null($redisData) || $redisData === false) {
                $refresh = 1;
            }
        }
        if ($refresh == 1) {
            return false;
        }
        return $redisData;
    }

    /**
     * 设置列表
     */
    public function setList($key, $order, $page, $limit, $val) {
        $listKeys = $this->getListKey($key, $order, $page);
        $redisKey = $listKeys['redisKey'];
        $fieldLimitKey = $listKeys['fieldLimitKey'];
        $fieldDataKey = $listKeys['fieldDataKey'];
        $this->_redisModel->redisSetHashList($redisKey, $fieldLimitKey, $limit);
        $this->_redisModel->redisSetHashList($redisKey, $fieldDataKey, $val);
    }

    /**
     * 删除列表
     */
    public function delList($key) {
        $list2Keys = $this->_redisModel->redisKeys($key . '*');
        $this->_redisModel->redisDel($list2Keys);
    }

    /**
     * 获取列表key
     */
    public function getListKey($key, $order, $page) {
        $redisKey = $key;
        $fieldLimitKey = $order . ':limit';
        $fieldDataKey = $order . ':page:' . $page;
        return ['redisKey' => $redisKey, 'fieldLimitKey' => $fieldLimitKey, 'fieldDataKey' => $fieldDataKey];
    }

    /**
     * 通过集合获取列表
     */
    public function getListBySort($key, $start, $limit) {
        $data = $this->_redisModel->redisZRevRange($key, $start, $start + $limit - 1);
        return $data;
    }
}