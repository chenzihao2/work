<?php
/**
 * User: WangHui
 * Date: 2018/9/29
 * Time: 11:01
 */

namespace QK\HaoLiao\DAL;


use QK\HaoLiao\Model\RedisModel;
use QK\WSF\Settings\AppSetting;

class DALBanner extends BaseDAL
{
	protected $_table = "hl_banner";

	public function __construct(AppSetting $appSetting) {
		parent::__construct($appSetting);
	}

	public function listsBanner($condition = '', $fields = '', $order = '', $start = 0, $limit = 0) {
	    $sql = 'SELECT ' . (empty($fields) ? '*': $fields) .
            ' FROM ' . $this->_table;
	    if (!empty($condition)) {
	        $sql .= ' WHERE ' . $condition;
        }
	    if (!empty($order)) {
	        $sql .= ' ORDER BY ' . $order;
        }
	    if ($limit != 0) {
	        $sql .= ' LIMIT ' . $start . ', ' . $limit;
        }
	    return $this->getDB($sql)->executeRows($sql);
    }

	public function newBanner($params) {
	    return $this->insertData($params, $this->_table);
    }

    public function bannerInfo($condition = '', $fields = '') {
	    $sql = 'SELECT ' . (empty($fields) ? '*': $fields) .
            ' FROM ' . $this->_table;
	    if (!empty($condition)) {
	        $sql .= ' WHERE ' . $condition;
        }
        return $this->getDB($sql)->executeRow($sql);
    }

    public function updateBanner($bid, $params) {
	    return $this->updateData($bid, $params, $this->_table);
    }

    public function getMaxSort() {
	    //$redisModule = new RedisModel('other');
	    //$sort = $redisModule->redisGet(OTHER_BANNER_SORTMAX);
	    //if (!$sort) {
            $sql = 'SELECT MAX(`sort`) sort FROM `' . $this->_table . '`';
            $sortQuery = $this->getDB($sql)->executeRow($sql);
            $sort = $sortQuery['sort'];
        //}
	    $sort = $sort ? ($sort + 1) : 1;
        //$redisModule->redisSet(BANNER_SORT_MAX, $sort);
	    return $sort;
    }
}
