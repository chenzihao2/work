<?php
/**
 * User: zyj
 * Date: 2019/9/2
 * Time: 11:01
 */

namespace QK\HaoLiao\DAL;


use QK\HaoLiao\Model\RedisModel;
use QK\WSF\Settings\AppSetting;

class DALVideo extends BaseDAL
{
	protected $_table = "hl_news_video";
	private $attent_table = 'hl_video_attention';

	public function __construct(AppSetting $appSetting) {
		parent::__construct($appSetting);
	}


    public function getVideoList($condition = array(), $fields = array(), $page = 0, $pageSize = 20, $orderBy = array()) {
        $fieldStr = " a.*,from_unixtime(a.create_time) as create_time, from_unixtime(a.modify_time) as modify_time,b.name as cname ";
        if (!empty($fields)) {
            $fieldStr = implode(',', $fields);
        }
        $sql = "SELECT $fieldStr FROM  `$this->_table` as a left join hl_category as b on a.cid=b.id WHERE  1=1";

        if (!empty($condition)) {
            foreach($condition as $key => $val) {
                if (is_array($val)) {
                    $sql .= " AND $key $val[0] '$val[1]'";
                } else {
                    $sql .= " AND $key = '$val'";
                }
            }
        }

        if (!empty($orderBy)) {
            $ordersArr = array();
            foreach($orderBy as $orderKey => $orderVal) {
                $ordersArr[] = "$orderKey $orderVal";
            }
            $orderStr = implode(',', $ordersArr);
            $sql .= " ORDER BY $orderStr";
        }

        if ($pageSize) {
            $sql .= " limit $pageSize";
        }
        if (!empty($page)) {
            $offset = ($page - 1) * $pageSize;
            $sql .= " offset $offset";
        }


        return $this->getDB($sql)->executeRows($sql);
    }












    //视频列表
	public function listsVideo($condition = '', $fields = '', $order = '', $start = 0, $limit = 0) {
	    $sql = 'SELECT ' . (empty($fields) ? 'a.*,b.name as cname': $fields) .
            ' FROM ' . $this->_table.' as a left join hl_category as b on a.cid=b.id';

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
    //添加视频
	public function videoInsert($params) {
	    return $this->insertData($params, $this->_table);
    }


    //修改视频
    public function updateVideo($bid, $params) {

	    return $this->updateData($bid, $params, $this->_table);
    }
    /**
     * 获取视频信息
     * @param $newsId
     * @return mixed
     */
    public function getVideoInfo($id) {


        $sql = "SELECT a.*,b.name as cname,from_unixtime(a.create_time) as create_time, from_unixtime(a.modify_time) as modify_time FROM `$this->_table` as a left join hl_category as b on a.cid=b.id WHERE a.id = $id";
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

    /**
     * 获取总量
     * @param $where
     * @return mixed
     */
    public function getNewsTotal($condition) {
        $sql = "SELECT count(a.id) AS total FROM `$this->_table` as a WHERE 1=1";
        if (!empty($condition)) {
            foreach($condition as $key => $val) {
                if (is_array($val)) {
                    $sql .= " AND $key $val[0] '$val[1]'";
                } else {
                    $sql .= " AND $key = '$val'";
                }
            }
        }
        return $this->getDB($sql)->executeValue($sql);
    }

    public function getVideoByCategory($condition) {
        $order_by = ['create_time' => 'desc'];
        $page_num = $condition['page_num'];
        $offset = ($condition['page'] - 1) * $page_num;
        unset($condition['page_num']);
        unset($condition['page']);
        $condition['deleted'] = 0;
	$total = $this->getNewsTotal($condition);
        $data = $this->select($this->_table, $condition, [], $offset, $page_num, $order_by);
	return ['data' => $data, 'total' => $total];
    }

    public function columnAddSub($id, $column, $action = 'add') {
        $info = $this->get($this->_table, ['id' => $id], [$column]);
        if (empty($info[$column]) && $action != 'add') {
            return;
        }
        $do = '+ 1';
        if ($action == 'sub') {
            $do = '- 1';
        }
        $sql = "update $this->_table set $column = $column $do where id = $id";
        return $this->getDB($sql)->executeValue($sql);
    }

    public function getAttentInfo($user_id, $video_id) {
        $info = $this->get($this->attent_table, ['user_id' => $user_id, 'video_id' =>$video_id], []);
        if ($info) {
            return $info;
        } else {
            return false;
        }
    }

    public function addAttention($user_id, $video_id, $column) {
        $params = ['user_id' => $user_id, 'video_id' => $video_id, $column => 1];
        return $this->insertData($params, $this->attent_table);
    }

    public function updateAttent($user_id, $video_id, $data) {
        $datas = [];
        foreach($data as $k => $v) {
            $v++;
            if ($v > 1) {
                $v = 0;
            }
            $datas[$k] = $v;
        }
        return $this->updateByCondition(['user_id' => $user_id, 'video_id' => $video_id], $datas, $this->attent_table);
    }

    public function getVideoByUserId($user_id, $page, $page_num) {
        $offset = ($page - 1) * $page_num;
        $sql = "select v.* from $this->_table v left join $this->attent_table a on v.id = a.video_id where a.user_id = $user_id and v.status = 1 and v.deleted = 0 and a.collect = 1 order by create_time desc limit $offset,$page_num";
        $data = $this->getDB($sql)->executeRows($sql) ?: [];
        $sql_count = "select count(*) as count from $this->_table v left join $this->attent_table a on v.id = a.video_id where a.user_id = $user_id and v.status = 1 and v.deleted = 0 and a.collect = 1";
        $count = $this->getDB($sql_count)->executeRows($sql_count) ?: 0;
        return ['data' => $data, 'total' => $count[0]['count']];
    }

    public function getVideoById($id) {
        return $this->get($this->_table, ['id' => $id, 'status' => 1, 'deleted' => 0], []);
    }

}
