<?php
/**
 * User: zyj
 * Date: 2019/9/2
 * Time: 11:01
 */

namespace QK\HaoLiao\DAL;


use QK\HaoLiao\Model\RedisModel;
use QK\WSF\Settings\AppSetting;

class DALCategory extends BaseDAL
{
	protected $_table = "hl_category";

	public function __construct(AppSetting $appSetting) {
		parent::__construct($appSetting);
	}
    //分类列表
	public function listsCategory($condition = '', $fields = '', $order = '', $start = 0, $limit = 0) {
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

    //添加分类
	public function categoryInsert($params) {
	    return $this->insertData($params, $this->_table);
    }


    //修改分类
    public function updateCategory($bid, $params) {

	    return $this->updateData($bid, $params, $this->_table);
    }
    /**
     * 获取分类信息
     * @param $newsId
     * @return mixed
     */
    public function getCategoryInfo($id) {
        $sql = "SELECT * FROM `$this->_table` WHERE `id` = $id";
        return $this->getDB($sql)->executeRow($sql);
    }
	  /**
     * 根据名称获取分类信息
     * @param $newsId
     * @return mixed
     */
    public function getNameCategoryInfo($name,$type) {
        $sql = "SELECT * FROM `$this->_table` WHERE name = '$name' and type=$type";
        return $this->getDB($sql)->executeRow($sql);
    }
	
	
	
	
	
	
	
    public function CategoryInfo($condition = '', $fields = '') {
        $sql = 'SELECT ' . (empty($fields) ? '*': $fields) .
            ' FROM ' . $this->_table;
        if (!empty($condition)) {
            $sql .= ' WHERE ' . $condition;
        }
        return $this->getDB($sql)->executeRow($sql);
    }
	
	
    //获取最大的排序数量
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

    public function getCategory($condition, $fields) {
        $order_by = ['sort' => 'asc'];
	$condition['deleted'] = 0;
        return $this->select($this->_table, $condition, $fields, 0, false, $order_by);
    }
}
