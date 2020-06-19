<?php
/**
 * 专家sql处理
 * User: WangHui
 * Date: 2018/10/10
 * Time: 10:57
 */

namespace QK\HaoLiao\DAL;


use QK\HaoLiao\Common\StringHandler;

class DALUserExpert extends BaseDAL {
    private $_table = 'hl_user_expert';

    /**
     * 新建专家
     * @param $params
     */
    public function newExpert($params) {
      $this->insertData($params, $this->_table);
      return $this->getInsertId();
    }

    /**
     * 修改专家信息
     * @param $expert
     * @param $data
     * @return int
     */
    public function updateExpert($expert, $data) {
        $updateString = StringHandler::newInstance()->getDBUpdateString($data);
        $sql = "UPDATE `$this->_table` SET " . $updateString . " WHERE `expert_id`=$expert";
        return $this->getDB($sql)->executeNoResult($sql);
    }
    /**
     * 获取专家信息
     * @param $expertId
     * @return mixed
     */
    public function getExpertInfo($expertId) {
        $sql = "SELECT `expert_id`, `phone`, `platform`, `idcard_number`, `expert_name`, `real_name`, `headimgurl`, `desc`, `identity_desc`, `tag`, `sort`, `expert_status` as `status`, `create_time`, `push_resource_time`, `expert_type`, `user_id` FROM `hl_user_expert` WHERE `expert_id` = '$expertId'";
        return $this->getDB($sql)->executeRow($sql);
    }


	/*
     * 根据手机号获取专家信息
     */
    public function getExpertInfoByPhone($phone){
        $sql = "SELECT `expert_id`, `phone`, `platform`, `idcard_number`, `expert_name`, `real_name`, `headimgurl`, `desc`, `identity_desc`, `tag`, `sort`, `expert_status` as `status`, `create_time`, `push_resource_time`, `expert_type`, `user_id` FROM `hl_user_expert` WHERE `phone` = $phone";
        return $this->getDB($sql)->executeRow($sql);
    }



    public function getExpertByCondition($condition = []) {
      return $this->get($this->_table, $condition);
    }

    /**
     * 获取专家推荐TOP
     * @return array|bool
     */
    public function getExpertRecommendTop($limit, $platform = 1) {
        $sort_field = 'is_recommend';
        if($platform == 2) {
          $sort_field = 'is_wx_recommend';
        }
        $sql = "SELECT `expert_id`, `$sort_field` as is_recommend FROM `$this->_table` WHERE `expert_status` = 1 AND `$sort_field` > 0 AND `platform` in (0, $platform) ORDER BY `$sort_field` ASC LIMIT $limit";
        return $this->getDB($sql)->executeRows($sql);
    }

	
    /*
     * 热门推荐--新增
     * $record 命中率
     */
    public function hotList($record = 0,$platform = 2){
        //$sql="SELECT * FROM hl_user_expert_extra  AS t1 JOIN (SELECT ROUND(RAND() * ((SELECT MAX(expert_id) FROM hl_user_expert_extra)-(SELECT MIN(expert_id) FROM hl_user_expert_extra))+(SELECT MIN(expert_id) FROM hl_user_expert_extra)) AS id) AS t2 WHERE t1.expert_id >= t2.id and t1.max_bet_record>=$record LIMIT $limit";
     
        $sql="SELECT a.expert_id FROM hl_user_expert as a left join hl_user_expert_extra as b on a.expert_id=b.expert_id where b.max_bet_record_v2>=$record and a.expert_status=1 and a.platform in(0,$platform)";//

        return $this->getDB($sql)->executeRows($sql);
    }


    /**
     * 获取专家列表
     * @param $start
     * @param $size
     * @return array|bool|mixed
     */
    public function getExpertList($start, $size, $orderBy, $platform = 0) {
      $platformCondition = "";
        if($platform > 0) {
          $platformCondition = " AND `platform` in (0, $platform)";
        } else {
          $platformCondition = " AND `platform` = $platform";
        }
        switch($orderBy){
            case 1:
                $sql = "SELECT `expert_id` FROM `$this->_table` WHERE  `expert_status` = 1 $platformCondition";
                $sql .= " ORDER BY `sort` DESC";
                break;
            case 2:
                $sql = "SELECT `expert_id` FROM `$this->_table` WHERE  `expert_status` = 1 $platformCondition";
                $sql .= " ORDER BY `push_resource_time` DESC,`sort` DESC";
                break;
            case 3:
                $sql = "SELECT expert.expert_id as expert_id FROM `$this->_table` AS expert LEFT JOIN `hl_user_expert_extra` AS expert_extra ON expert.expert_id=expert_extra.expert_id WHERE  expert.expert_status = 1 $platformCondition ORDER BY expert_extra.red_num DESC, expert.sort DESC";
                break;
            default:
                return [];
        }

        if ($size) {
            $sql .= " LIMIT $start, $size";
        }
        if ($size == 1) {
            return $this->getDB($sql)->executeValue($sql);
        }
        return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 全部专家列表(后台)
     * @return array|bool
     */
    public function expertList() {
        $sql = "SELECT `expert_id` FROM `$this->_table` WHERE  `expert_status` = 1 ORDER BY `sort` DESC";
        return $this->getDB($sql)->executeRows($sql);
    }


    /**
     * 获取专家列表（后台）
     * @param $where
     * @param $page
     * @param $size
     * @return array|bool
     */
    public function getExpertListConsole($where, $page, $size) {
        $start = ($page - 1) * $size;
        $sql = "SELECT `expert_id`, `phone`, `platform`, `expert_name`, `real_name`, `headimgurl`, `idcard_type`, `idcard_number`, `source`, `is_recommend`, `is_placement`, `is_wx_recommend`, `is_wx_placement`,`desc`, `identity_desc`, `tag`, `sort`, `expert_status`, `create_time`, `check_time`, `modify_time`, `push_resource_time`,`expert_type` FROM `$this->_table` WHERE 1";
        if (!empty($where)) {
          foreach ($where as $key => $val) {
            if($key == 'platform') {
              if($val > 0) {
                $sql .= " AND $key in (0, $val)";
              } else if($val == 0) {
                $sql .= " AND $key = $val";
              }
            } else {
              if(!empty($val)) {
                $sql .= " AND $key = $val";
              }
            }
          }
        }
        $sql .= " ORDER BY `sort` DESC LIMIT $start, $size";

        return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 获取专家总数（后台）
     * @param $where
     * @return mixed
     */
    public function getExpertTotal($where) {
        $sql = "SELECT count(`expert_id`) AS total FROM `$this->_table` WHERE 1";
        if (!empty($where)) {
          foreach ($where as $key => $val) {
            if($key == 'platform') {
              if($val > 0) {
                $sql .= " AND $key in (0, $val)";
              } else if($val == 0){
                $sql .= " AND $key = $val";
              }
            }else{
              if(!empty($val)) {
                $sql .= " AND $key = $val";
              }
            }
          }
        }
        return $this->getDB($sql)->executeValue($sql);
    }

    /**
     * 获取专家当前最大排序（后台）
     * @return mixed
     */
    public function getMaxSort() {
        $sql = "SELECT MAX(`sort`) FROM `$this->_table`";
        return $this->getDB($sql)->executeValue($sql);
    }
    /**
     * 获取专家当前最小排序（后台）
     * @return mixed
     */
    public function getMinSort() {
        $sql = "SELECT MIN(`sort`) FROM `$this->_table`";
        return $this->getDB($sql)->executeValue($sql);
    }

    /**
     * 获取前一个专家排序（后台）
     * @param $sort
     * @return array|bool
     */
    public function getLeftOnlineSort($sort) {
        $sql = "SELECT `sort`,`expert_id` FROM `$this->_table` WHERE `sort`<$sort ORDER BY  `sort` DESC LIMIT 0,1";
        return $this->getDB($sql)->executeRow($sql);
    }

    /**
     * 获取下一个专家排序（后台）
     * @param $sort
     * @return array|bool
     */
    public function getRightOnlineSort($sort) {
        $sql = "SELECT `sort`,`expert_id` FROM `$this->_table` WHERE `sort`>$sort ORDER BY  `sort` ASC LIMIT 0,1";
        return $this->getDB($sql)->executeRow($sql);
    }

    /**
     * 更新排序（后台）
     * @param $sort
     * @param $newSort
     * @return int
     */
    public function updateSort($sort, $newSort) {
        $sql = "UPDATE `$this->_table` SET `sort` = '$newSort' WHERE `sort` = $sort";
        return $this->getDB($sql)->executeNoResult($sql);
    }

    /**
     * 更新卡片排序（后台）
     * @param $id
     * @param $newSort
     * @return int
     */
    public function updateSortById($id, $newSort) {
        $sql = "UPDATE `$this->_table` SET `sort` = '$newSort' WHERE `expert_id` = $id";
        return $this->getDB($sql)->executeNoResult($sql);
    }

    /**
     * 获取推荐位置专家id（后台）
     * @param $type
     * @return mixed
     */
    public function getRecommendExpertId($type, $platform = 1) {
      $where = '';
      if($platform == 1) {
        $where = "WHERE is_recommend = $type AND `platform` in (0, 1)";
      }else {
        $where = "WHERE is_wx_recommend = $type AND `platform` in (0, 2)";
      }
        $sql = "SELECT `expert_id` FROM $this->_table $where";
        return $this->getDB($sql)->executeValue($sql);
    }

    /**
     * 获取置顶位置专家id（后台）
     * @param $placement
     * @return mixed
     */
    public function getPlacementExpertId($placement, $platform = 1) {
      $where = '';
      if($platform == 1) {
        $where = "WHERE is_placement = $placement AND `platform` in (0, 1)";
      }else {
        $where = "WHERE is_wx_placement = $placement AND `platform` in (0, 2)";
      }
        $sql = "SELECT `expert_id` FROM $this->_table $where";
        return $this->getDB($sql)->executeValue($sql);
    }

    public function lists($condition = "", $fields = "", $order = "", $other = [], $start = 0, $limit = 0) {
        $sql = 'SELECT ' . (empty($fields) ? '*' : $fields) . ' FROM ' . $this->_table;
        if (isset($other['join'])) {
            foreach ($other['join'] as $item) {
                $sql .= ' ' . (empty($item[2]) ? 'LEFT' : strtoupper($item[2])) . ' JOIN ' . $item[0] . ' ON ' . $item[1];
            }
        }
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

    public function newExpertList($condition = array(), $fields = array(), $offset = 0, $limit = 0, $orderBy = array()) {
      return $this->select($this->_table, $condition, $fields, $offset, $limit, $orderBy);
    }

    public function getExpertInfoV2($expertId) {
        $sql = "SELECT expert.`expert_id`, expert.`phone`, expert.`platform`, expert.`idcard_number`, expert.`expert_name`, expert.`real_name`, expert.`headimgurl`, expert.`desc`, expert.`identity_desc`, expert.`tag`, expert.`sort`, expert.`expert_status` as `status`, expert.`create_time`, expert.`push_resource_time`, expert_extra.profit_rate, expert_extra.profit_all,expert_extra.publish_resource_num, expert_extra.max_red_num, expert_extra.max_bet_record, expert_extra.max_bet_record_v2 FROM `hl_user_expert`AS expert LEFT JOIN `hl_user_expert_extra` AS expert_extra ON expert.expert_id=expert_extra.expert_id WHERE expert.`expert_id` = '$expertId'";
        return $this->getDB($sql)->executeRow($sql);
    }
}
