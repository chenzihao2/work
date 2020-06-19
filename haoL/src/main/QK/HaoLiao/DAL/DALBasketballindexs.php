<?php
/**
 * User: zyj
 * Date: 2019/9/04
 * Time: 11:01
 */

namespace QK\HaoLiao\DAL;


use QK\HaoLiao\Model\RedisModel;
use QK\WSF\Settings\AppSetting;

class DALBasketballindexs extends BaseDAL
{
	protected $basketball_indexs = "hl_basketball_indexs";
    protected $basketball_match = "hl_basketball_match";
	public function __construct(AppSetting $appSetting) {
		parent::__construct($appSetting);
	}

    public function getIndexsInfoByNum($match_num, $indexs_type, $comp_num = 0,$sort = 'asc',$is_first=0) {
        $condition = ['match_num' => $match_num, 'indexs_type' => $indexs_type];
        if ($comp_num) {
            $condition['comp_num'] = $comp_num;
        }
        if($is_first){
            $condition['is_first'] = $is_first;
        }

        $order['indexs_date'] = $sort;
        $result = $this->select($this->basketball_indexs, $condition, [], 0, false,$order);
        return $result;
    }


    //根据赛事 和指数类型获取 公司
    public function getIndexsCompByNum($match_num, $indexs_type) {
        $result = $this->select($this->basketball_indexs, ['match_num' => $match_num, 'indexs_type' => $indexs_type], ['distinct comp_num', 'comp_name'], 0, false);
        return $result;
    }

    public function getIndexsByComp($match_num, $indexs_type, $comp_num, $is_first = 0,$now=0) {
        $order = [];
        $limit = false;
        if ($is_first == 1) {
            $order['indexs_date'] = 'asc';
            $limit = 1;
        } elseif ($is_first == 2) {
            $order['indexs_date'] = 'desc';
            $limit = 1;
        } else {
            $order['indexs_date'] = 'desc';
        }
        $condition = ['match_num' => $match_num, 'indexs_type' => $indexs_type, 'comp_num' => $comp_num];
        if($now){
            $condition['is_first'] = $now;
        }

        $fields = ['left_indexs', 'center_indexs', 'right_indexs', 'indexs_date'];
        $result = $this->select($this->basketball_indexs, $condition, $fields, 0, $limit, $order);
        if ($result) {
            if ($is_first) {
                return  $result[0];
            } else {
                return $result;
            }
        } else {
            return [];
        }
    }


	//查询指数列表
    public function getIndexList($condition = '', $fields = '', $order = '', $start = 0, $limit = 0){
        $sql = 'SELECT ' . (empty($fields) ? '*': $fields) .
            ' FROM ' . $this->basketball_indexs;
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
    //指数详情
    public function getIndexFind($condition = '', $fields = '',$order='') {
            //$sql = "select   `user_id`,`cid`, `uuid`, `phone`,`nick_name`,`sex`,`country`,`province`,`city`,`headimgurl`,`idcard_type`,`idcard_number`,`source`,`identity`,`dist_id`,`user_status`,`create_time`,`modify_time`,`last_login_time`,`last_login_ip` from `$this->_table` WHERE `user_id` = $userId";
            $sql='select '. (empty($fields) ? '*': $fields) .' from '. $this->basketball_indexs.' limit 1';

            if($condition){
                $sql .= ' WHERE ' . $condition;
            }
        if (!empty($order)) {
            $sql .= ' ORDER BY ' . $order;
        }
        return $this->getDB($sql)->executeRows($sql);
    }

    //查询该指数是否已存在
    public function existsIndexs($match_num, $comp_num, $indexs_type, $indexs_date,$is_first='') {
	    $condition=['comp_num' => $comp_num, 'match_num' => $match_num, 'indexs_type' => $indexs_type, 'indexs_date' => $indexs_date];
	    if($is_first){
            $condition['is_first']=$is_first;
        }

        return $this->get($this->basketball_indexs,$condition , ['id']);
    }
    //添加指数
    public function addBasketballIndexs($params) {
        return $this->insertData($params, $this->basketball_indexs);
    }

    //根据时间获取最近的赛程，获取公司
    public function getMatchNumByDate($date1, $date2, $condition = []) {
        $val1 = ' between \'' . $date1 . '\' ';
        $val2 = ' and \'' . $date2 . '\' ';
        $fields = ['match_num'];

        if ($condition['indexs']) {
            $now = $this->dealIndexsWithRedis('indexs');
            $conditions['status'] = [' <> ', 4];
            $date1 = date('Y-m-d H:i:s', time() + $now * 3600);
            $val1 = ' between \'' . $date1 . '\'';
            $val2 = ' and \'' . date('Y-m-d H:i:s', strtotime($date1) + 3600) . '\'';
        }
        $conditions['date'] = [$val1, $val2];

        return array_column($this->select($this->basketball_match, $conditions, $fields, 0, false, []), 'match_num');
    }

    public function dealIndexsWithRedis($prefix = '') {
        $key = $prefix . 'basketball_indexs_deal';
        $redisModel = new RedisModel('match');
        $now = $redisModel->redisIncr($key, 3600);
     //dump($now);
        //1天
        if ($now > 24) {
            $redisModel->redisSet($key, 1, 3600);
        }
        $now = $redisModel->redisGet($key);
        //var_dump($now);
        return $now;
    }
}
