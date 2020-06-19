<?php
/**
 * 料相关数据处理
 * User: YangChao
 * Date: 2018/10/12
 */

namespace QK\HaoLiao\DAL;


use QK\HaoLiao\Common\StringHandler;
use QK\HaoLiao\Model\RedisModel;
use QK\HaoLiao\Model\ResourceModel;
use QK\WSF\Settings\AppSetting;
use QK\HaoLiao\DAL\DALResourceSchedule;

class DALResource extends BaseDAL {
  protected $_table = "hl_resource";
  protected $_attention_table = "hl_resource_attention";
  protected $_view_record_table = "hl_resource_view_record";

    public function __construct(AppSetting $appSetting) {
        parent::__construct($appSetting);
    }

    /**
     * 新建一个料
     * @param $resource
     * @return int
     */
    public function createResource($resource) {
        /*$res = $this->insertData($resource, $this->_table);
        if ($res) {
            return (int)$this->getInsertId();
        }
        return $res;*/
      $keyStr = implode(',', array_keys($resource));
      $valueArr = array();
      $params = array();
      foreach(array_values($resource) as $key => $value) {
        $valueArr[] = '?';
        $params[$key+1] = $value;
      }

      $sql = 'INSERT INTO `'.$this->_table.'` ('.$keyStr.') VALUES ('. implode(',', $valueArr) .')';
      $res = $this->getDB($sql)->executeNoResult($sql, $params);
      if ($res) {
        return (int)$this->getInsertId();
      }
      return $res;
    }

    /**
     * 修改一个料信息
     * @param $resourceId
     * @param $data
     * @return int
     */
    public function updateResource($resourceId, $data) {
      $fields = array_keys($data);
      $params = array();
      $sql = "UPDATE `$this->_table` SET ";
      foreach($fields as $field) {
        // eg: 'balance=balance+1'
        if(preg_match('/=/', $field)) {
          $sql .= $field.",";
        } else {
          $sql .= $field."=?,";
        }
      }
      $sql = trim($sql, ",");
      $sql .= " WHERE `resource_id`=$resourceId";
      foreach(array_values($data) as $index => $param) {
        $params[$index+1] = $param;
      }
      //$updateString = StringHandler::newInstance()->getDBUpdateString($data);
      //$sql = "UPDATE `$this->_table` SET " . $updateString . " WHERE `resource_id`=$resourceId";
      return $this->getDB($sql)->executeNoResult($sql, $params);
    }

    /*
     * 获取方案人浏览总数
     */
    public function getViewRecord($resourceId){
        $sql = "SELECT count(*) as count FROM `$this->_view_record_table`  WHERE resource_id=$resourceId group by device";
        return $this->getDB($sql)->executeRow($sql);
    }

    /**
     * 获取料信息
     * @param $resourceId
     * @return mixed
     */
    public function getResourceInfo($resourceId) {
        $sql = "SELECT  `resource_id`, `expert_id`, `push_expert_id`, `title`, `price`, `is_free`, `resource_type`, `qrcode_url`, `odds`, `sort`, `resource_status`, `is_schedule_over`, `create_time`, `release_time`, `modify_time`, `wx_placement`, `bd_placement`, `wx_display`, `bd_display`, `is_limited`, `limited_time`, `is_groupbuy`, `is_expert`,`is_auto_bet`,`remarks`, `match_type` FROM `$this->_table` WHERE `resource_id` = $resourceId";
        return $this->getDB($sql)->executeRow($sql);
    }

    /**
     * 获取料列表
     * @param $expertId
     * @param $source
     * @param $start
     * @param $pagesize
     * @return array|bool
     */
    public function getResourceIdListByExpertId($expertId, $source, $start, $pagesize) {
        if ($expertId != 0) {
            $sql = "SELECT `resource_id` FROM `$this->_table` WHERE `expert_id` = $expertId";
        } else {
            $sql = "SELECT `resource_id` FROM `$this->_table` WHERE 1";
        }
        switch ($source) {
            case 1:
                //用户访问获取
                $sql .= " AND `resource_status` = 1";
                break;
            case 2:
                //专家访问获取
                $sql .= " AND `resource_status` < 4";
                break;
            case 3:
                //后台访问获取
                break;
        }

        switch($GLOBALS['display']){
            case 1:
                $sql .= " AND `wx_display` = 1";
                break;
            case 2:
                $sql .= " AND `bd_display` = 1";
                break;
        }

        $sql .= " ORDER BY `is_schedule_over` ASC, `resource_status` ASC, `release_time` DESC";
        $sql .= " limit {$start},{$pagesize}";

        return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 获取首页推荐数据
     * @param $start
     * @param $pagesize
     * @return array|bool
     */
    public function getRecommendResourceList($start, $pagesize, $match_num = 0){
        if ($match_num) {
            $GLOBALS['display'] = '';
            $dal_rs = new DALResourceSchedule($this->_appSetting);
            $res_ids = $dal_rs->getResourceByMatch($match_num);
            if (empty($res_ids)) {
                return [];
            }
            $resource_id = [];
            foreach ($res_ids as $v) {
               $resource_id[] = $v['resource_id']; 
            }
            $resource_ids = implode(',', $resource_id);
        }
        $sql = "SELECT `resource_id` FROM `$this->_table` as r LEFT JOIN `hl_user_expert_extra` as ea ON r.expert_id=ea.expert_id LEFT JOIN `hl_user_expert` as e ON r.expert_id=e.expert_id WHERE 1";
        switch($GLOBALS['display']){
            case 1:
                $sql .= " AND `wx_display` = 1";
                break;
            case 2:
                $sql .= " AND `bd_display` = 1";
                break;
        }
        if ($match_num) {
            $sql .= " AND r.resource_id in ($resource_ids)";
        }
        $sql .= " AND r.`is_free` = 0 AND r.`resource_status` = 1 AND e.`expert_status` = 1";
        $sql .= " ORDER BY r.`sort` DESC, FIELD(r.is_schedule_over, 0, 2, 3, 1), ";
        switch($GLOBALS['display']){
            case 1:
                $sql .= "`wx_placement` DESC, ";
                break;
            case 2:
                $sql .= "`bd_placement` DESC, ";
                break;
        }
        $sql .= "r.`schedule_over_date` DESC, ea.`max_bet_record` DESC, r.`release_time` DESC";
        $sql .= " limit {$start},{$pagesize}";

        return $this->getDB($sql)->executeRows($sql);
    }

    public function getRecommendListV2($start = 0, $pagesize = 20, $platform = 1, $is_new = 0, $match_num = 0, $type = 1, $is_free = 0, $match_type = 0){
        if ($match_num) {
            $dal_rs = new DALResourceSchedule($this->_appSetting);
            $res_ids = $dal_rs->getResourceByMatch($match_num, $type);
            if (empty($res_ids)) {
                return [];
            }
            $resource_id = [];
            foreach ($res_ids as $v) {
                $resource_id[] = $v['resource_id'];
            }
            $resource_ids = implode(',', $resource_id);
        }
        $sql = "SELECT r.*,r.create_time as r_create_time, e.*, ea.`max_bet_record`, ea.`max_bet_record_v2` FROM `$this->_table` as r LEFT JOIN `hl_user_expert_extra` as ea ON r.expert_id=ea.expert_id LEFT JOIN `hl_user_expert` as e ON r.expert_id=e.expert_id left join hl_resource_schedule rs on r.resource_id = rs.resource_id and rs.`schedule_status` = 1 WHERE 1";
        switch($platform){
            case 1:
                $sql .= " AND `bd_display` = 1";
                break;
            case 2:
                $sql .= " AND `wx_display` = 1";
                break;
        }
        $sql .= " AND r.`resource_status` = 1 AND e.`expert_status` = 1";
        if ($match_num) {
            $sql .= " AND r.resource_id in ($resource_ids)";
        } else {
            if ($is_free) {
                $sql .= " AND r.`is_free` = 1";
            } else {
                $sql .= " AND r.`is_free` = 0";
            }
        }
        if ($match_type) {
            $sql .= " AND (r.`match_type` = $match_type or rs.type = $match_type)";
        }
        $sql .= " group by r.resource_id";
       // $sql .= " ORDER BY r.`sort` DESC, r.`is_schedule_over` ASC, ";
        $sql .= " ORDER BY r.`sort` DESC,FIELD(r.is_schedule_over, 0, 2, 3, 1), ";
        switch($platform){
            case 1:
                $sql .= "`bd_placement` DESC, ";
                break;
            case 2:
                $sql .= "`wx_placement` DESC, ";
                break;
        }
        $sql .= "r.`schedule_over_date` DESC,";
        $sql .= $is_new ? "ea.`max_bet_record_v2` DESC," : "ea.`max_bet_record` DESC,";
        $sql .= "r.`release_time` DESC";
        $sql .= " limit {$start},{$pagesize}";

        return $this->getDB($sql)->executeRows($sql);
    }

    public function getFreeResourcesCount($platform = 1) {
      $sql = "SELECT count(*) as count FROM `$this->_table` as r LEFT JOIN `hl_user_expert` as e ON r.expert_id=e.expert_id WHERE 1";
      switch($platform){
        case 1:
          $sql .= " AND `bd_display` = 1";
          break;
        case 2:
          $sql .= " AND `wx_display` = 1";
          break;
      }
      $sql .= " AND r.`is_free` = 1 AND r.`resource_status` = 1 AND r.`is_schedule_over` = 0 AND e.`expert_status` = 1";
      return $this->getDB($sql)->executeRow($sql);
    }

    /**
     * 获取更新料列表
     * @param $startTime
     * @return array|bool
     */
    public function getNewRecommendResourceList($startTime, $platform = 2, $is_free = 1) {
      $sql = "SELECT `resource_id` FROM `$this->_table` WHERE `resource_status` = 1 AND `release_time` > $startTime ";
      switch($platform){
        case 1:
          $sql .= " AND `bd_display` = 1 AND `is_free` = $is_free";
          break;
        case 2:
          $sql .= " AND `wx_display` = 1";
          break;
      }
      $sql .= " ORDER BY `release_time` DESC";
      return $this->getDB($sql)->executeRows($sql);
    }



    /**
     * 我关注的专家的料数据--新增
     * @param $start
     * @param $pagesize
     * @return array|bool
     */
    public function getMyExpertResourceList($expertIds,$start, $pagesize, $match_num = 0,$platform=2){
       /* if ($match_num) {
            $GLOBALS['display'] = '';
            $dal_rs = new DALResourceSchedule($this->_appSetting);
            $res_ids = $dal_rs->getResourceByMatch($match_num);
            if (empty($res_ids)) {
                return [];
            }
            $resource_id = [];
            foreach ($res_ids as $v) {
                $resource_id[] = $v['resource_id'];
            }
            $resource_ids = implode(',', $resource_id);
        }*/
        $sql = "SELECT `resource_id` FROM `$this->_table` as r LEFT JOIN `hl_user_expert_extra` as ea ON r.expert_id=ea.expert_id LEFT JOIN `hl_user_expert` as e ON r.expert_id=e.expert_id WHERE 1";
         switch($platform){
            case 1:
				$sql .= " AND `bd_display` = 1";
               
                break;
            case 2:
                 $sql .= " AND `wx_display` = 1";
                break;
        }
        /*if ($match_num) {
            $sql .= " AND r.resource_id in ($resource_ids)";
        }*/
        $sql .= " AND r.`resource_status` = 1 AND e.`expert_status` = 1 AND r.expert_id in($expertIds) AND e.platform in(0,$platform)";
        $sql .= " ORDER BY r.`is_schedule_over` ASC, ";
        //置顶
        switch($platform){
            case 1:
                $sql .= "`bd_placement` DESC, ";
                break;
            case 2:
                
				$sql .= "`wx_placement` DESC, ";
                break;
        }
        $sql .= "r.`schedule_over_date` DESC, ea.`max_bet_record` DESC, r.`release_time` DESC";
        $sql .= " limit {$start},{$pagesize}";
        return $this->getDB($sql)->executeRows($sql);
    }

        /*
        * 推荐料数量--新增
        */
    public function getRecommendResourceCount($expertId){
        $sql="select count(*) as count from hl_resource as a left join hl_resource_extra as b on a.resource_id=b.resource_id where a.expert_id=$expertId and a.resource_status=1 and a.is_schedule_over=0 and b.bet_status=0 ";
        return $this->getDB($sql)->executeValue($sql);
    }


	/*
     *获取专家下最新的料--新增
     */
    public function getExpertNewResource($expertId){
        $sql="select * from hl_resource as a left join hl_resource_extra as b on a.resource_id=b.resource_id where a.expert_id=$expertId and a.resource_status=1 and a.is_schedule_over=0 and b.bet_status=0 order by a.create_time desc limit 1";
        return $this->getDB($sql)->executeRows($sql);
    }



   /**
     * 获取某个专家某个时间段料总数--新增
     * @param      $expertId
     * @param null $start
     * @param null $end
     * @return mixed
     */
    public function getTodayResourceCount($expertId, $start = 0,$end=0) {
        $sql = "SELECT COUNT(`resource_id`) AS total FROM `$this->_table` WHERE `expert_id` = $expertId AND `resource_status` in(0,1)";

        if ($start !==0) {
            $sql .= " AND `create_time` >= $start";
        }
        if ($end !==0) {
            $sql .= " AND `create_time` <= $end";
        }

        return $this->getDB($sql)->executeValue($sql);
    }


    /**
     * 获取料列表（后台）
     * @param $query
     * @param $start
     * @param $pagesize
     * @return array|bool
     */
    public function getResourceIdList($query, $start, $pagesize,$order=[]) {
        $sql = "SELECT a.resource_id FROM `$this->_table` as a left join hl_user_expert as b on a.expert_id=b.expert_id left join hl_resource_extra  as c on a.resource_id = c.resource_id WHERE 1";
        if (!empty($query)) {
            foreach ($query as $key => $val) {

                if($key== "start_time"){
                    $sql .= " AND a.release_time >= $val";
                }elseif ($key=="end_time"){
                    $sql .= " AND a.release_time < " . (strtotime(date('Y-m-d', $val)) + 24 * 3600);
                }elseif($key=="expertName"){
                    $sql .= " AND b.expert_name like '%$val%'";
                }elseif($val !== '') {
                    $sql .= " AND a.$key = $val";
                }
            }
        }
        $sql.=" ORDER BY ";
        if($order){
            foreach($order as $k=>$v){
                if($k=='sold_num' && $v){
                    $sql.="c.$k $v ,";
                }else if($v){
                    $sql.="a.$k $v ,";
                }
            }
        }
        $sql .= " a.sort DESC,a.resource_id DESC,a.is_schedule_over ASC, a.resource_status ASC, a.create_time DESC";
        $sql .= " limit {$start},{$pagesize}";
        return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 获取料列表
     * @param $query
     * @param $start
     * @param $pagesize
     * @return array|bool
     */
    public function getResourceIdList2($condition = '', $fields = '', $order = '', $start = 0, $limit = 0) {
        $sql = "SELECT `resource_id` FROM `$this->_table`";
        if (!empty($condition)) {
            $sql .= ' WHERE ' . $condition;
        }
        if (!empty($order)) {
            $sql .= " ORDER BY " . $order;

        }
        if ($limit != 0) {
            $sql .= " LIMIT {$start},{$limit}";
        }

        return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 获取料列表总数(后台)
     * @param $query
     * @return mixed
     */
    public function getResourceListCount($query) {
        $sql = "SELECT COUNT(*) FROM `$this->_table` as a left join hl_user_expert as b on a.expert_id=b.expert_id WHERE 1";
        if (!empty($query)) {
            foreach ($query as $key => $val) {

                if($key== "start_time"){
                    $sql .= " AND a.release_time >= $val";
                }elseif ($key=="end_time"){
                    $sql .= " AND a.release_time < " . (strtotime(date('Y-m-d', $val)) + 24 * 3600);
                }elseif($key=="expertName"){
                    $sql .= " AND b.expert_name like '%$val%'";
                }elseif($val !== '') {
                    $sql .= " AND a.$key = $val";
                }
            }
        }

        return $this->getDB($sql)->executeValue($sql);
    }

    /**
     * 根据专家ID获取最近连红次数
     * @param $expertId
     * @return mixed
     */
    public function getContinuityRedNumByExpertId($expertId) {
        $sql = "SELECT `release_time` FROM `$this->_table` AS r LEFT JOIN `hl_resource_extra` AS re ON r.resource_id = re.resource_id WHERE r.expert_id = $expertId AND r.resource_status = 1 AND re.bet_status = 3 ORDER BY r.release_time DESC LIMIT 1";
        $lastBlackResource = $this->getDB($sql)->executeRow($sql);
        $lastBlackResourceReleaseTime = $lastBlackResource['release_time'] ? $lastBlackResource['release_time'] : 0;

        $redSql = "SELECT COUNT(r.resource_id) FROM `$this->_table` AS r LEFT JOIN `hl_resource_extra` AS re ON r.resource_id = re.resource_id WHERE r.expert_id = $expertId AND r.release_time > $lastBlackResourceReleaseTime AND r.resource_status = 1 AND re.bet_status = 1";
        $continuityRedNum = $this->getDB($redSql)->executeValue($redSql);
        return $continuityRedNum;
    }


    /**
     * 最近十场红黑走单数量
     * @param $expertId
     * @return array|bool
     */
    public function nearTenScore($expertId) {
        $sql = "SELECT `bet_status`,COUNT(*) as `num` FROM (SELECT `bet_status` FROM `$this->_table` LEFT JOIN `hl_resource_extra` ON `$this->_table`.`resource_id` = `hl_resource_extra`.`resource_id` WHERE `$this->_table`.`expert_id` = $expertId AND `$this->_table`.resource_status = 1 AND `hl_resource_extra`.`bet_status`>=1 limit 0,10) temp GROUP BY temp.`bet_status`";
        return $this->getDB($sql)->executeRows($sql);
    }

    public function getResourceListToStatByExpertId($expertId, $condition) {
        $sql = "SELECT `hl_resource`.`resource_id`, `bet_status` FROM `hl_resource` LEFT JOIN `hl_resource_extra` ON `hl_resource`.`resource_id` = `hl_resource_extra`.`resource_id` WHERE `hl_resource`.`expert_id` = $expertId AND $condition AND `hl_resource`.resource_status = 1 AND `hl_resource_extra`.`bet_status`>=1";
        return $this->getDB($sql)->executeRows($sql);
    }

    public function nearTenRecord($expertId, $platform = 2) {
      $platform_field = ($platform == 1) ? 'bd_display' : 'wx_display';
        $sql = "SELECT `$this->_table`.`resource_id`,`bet_status` " .
            "FROM `$this->_table` " .
            "LEFT JOIN `hl_resource_extra` " .
            "ON `$this->_table`.`resource_id` = `hl_resource_extra`.`resource_id` " .
            "WHERE `$this->_table`.`expert_id` = $expertId AND `$this->_table`.resource_status = 1 AND `$this->_table`.`$platform_field` = 1 AND `hl_resource_extra`.`bet_status`>=1 " .
            "ORDER BY `$this->_table`.`release_time` desc " .
            "LIMIT 0,10";
        return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 专家料列表(首页用)
     * @param $expertId
     * @return array|bool
     */
    public function getExpertResourceList($expertId) {
        $sql = "SELECT `resource_id` FROM `$this->_table`  WHERE `expert_id` = $expertId AND `is_schedule_over`=1 ORDER BY `create_time` LIMIT 0, 2";
        return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 获取专家料总数
     * @param      $expertId
     * @param null $is_schedule_over
     * @return mixed
     */
    public function getResourceTotalByExpertId($expertId, $is_schedule_over = null) {
        $sql = "SELECT COUNT(`resource_id`) AS total FROM `$this->_table` WHERE `expert_id` = $expertId AND `resource_status` = 1";
        if ($is_schedule_over !== null) {
            $sql .= " AND `is_schedule_over` = $is_schedule_over";
        }
        return $this->getDB($sql)->executeValue($sql);
    }


    /**
     * 获取未完赛料
     * @param $start
     * @return mixed
     */
    public function getNotOverResource($start) {
        $time = time() - 3600 * 3;
        //$sql = "SELECT  `resource_id` FROM `$this->_table` WHERE `is_schedule_over` = 0 AND `resource_status`=1 AND `release_time`<$time limit $start,1";
        $sql = "SELECT  `resource_id` FROM `$this->_table` WHERE (`is_schedule_over` = 0 or `is_schedule_over` = 3) AND `resource_status`=1  limit $start,1";
        return $this->getDB($sql)->executeValue($sql);
    }


    /**
     * 获取专家有效料的最后发布时间
     * @param $expertId
     * @return mixed
     */
    public function getLastResourceReleaseTime($expertId){
        $sql = "SELECT `release_time` FROM `$this->_table` WHERE `expert_id` = $expertId AND `resource_status` = 1 ORDER BY `is_schedule_over` ASC,`release_time` DESC LIMIT 1";
        return $this->getDB($sql)->executeValue($sql);
    }

    /**
     * 获取料列表
     * @param string $condition
     * @param string $fields
     * @param string $order
     * @param array $other
     * @param int $start
     * @param int $limit
     * @return array|bool
     */
    public function lists($condition = '', $fields = '', $order = '', $other = [], $start = 0, $limit = 0) {
        $sql = 'SELECT ' . (empty($fields) ? '*' : $fields) . ' FROM ' . $this->_table;
        if (isset($other['join'])) {
            foreach ($other['join'] as $item) {
                $sql .= ' '. (empty($item[2]) ? 'LEFT' : strtoupper($item[2])) . ' JOIN ' . $item[0] . ' ON ' . $item[1];
            }
        }
        if (!empty($condition)) {
            $sql .= ' WHERE ' . $condition;
        }
        if (!empty($order)) {
            $sql .= ' ORDER BY ' . $order;
        }
        if ($limit != 0) {
            $sql .= ' LIMIT ' . $start . ',' . $limit;
        }
        return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 获取时间段内的限时查看料
     * @param $startTime
     * @param $endTime
     * @return array|bool
     */
    public function getLimitedResourceList($startTime, $endTime){
        $sql = "SELECT * FROM `$this->_table` WHERE `resource_status` = 1 AND `is_limited` = 1 AND `limited_time` >= $startTime AND `limited_time` <= $endTime";
        return $this->getDB($sql)->executeRows($sql);
    }

    public function getSoldCronInfo($resource_id) {
        $redisKey = RESOURCE_CRONSOLDNUM_DATA;
        $redisModule = new RedisModel('resource');
        $cronInfo = $redisModule->redisGetHashList($redisKey, $resource_id);
        return $cronInfo;
    }

    public function getResourceListV2($condition = array(), $fields = array(), $offset = 0, $limit = 0, $orderBy = array()) {
      return $this->select($this->_table, $condition, $fields, $offset, $limit, $orderBy);
    }

    public function getExpertBetRecordList($expertId, $limit = 20, $platform = 2,$resource_id='') {
      $platform_field = ($platform == 1) ? 'bd_display' : 'wx_display';
        $sql = "SELECT `$this->_table`.`resource_id`,`bet_status` " .
            "FROM `$this->_table` " .
            "LEFT JOIN `hl_resource_extra` " .
            "ON `$this->_table`.`resource_id` = `hl_resource_extra`.`resource_id` " .
            "WHERE `$this->_table`.`expert_id` = $expertId AND `$this->_table`.resource_status = 1 AND `$this->_table`.`$platform_field` = 1 AND `hl_resource_extra`.`bet_status`>=1 ";
            if($resource_id){
				$sql.="AND `$this->_table`.resource_id not in($resource_id) ";
			}
			$sql.="ORDER BY `$this->_table`.`release_time` desc " .
            "LIMIT 0,$limit";

      return $this->getDB($sql)->executeRows($sql);
    }

    public function getExpertScheduleBetRecordList($expertId, $limit = 20, $platform = 2) {
      //$platform_field = ($platform == 1) ? 'bd_display' : 'wx_display';
      //$sql = "SELECT `$this->_table`.`resource_id`,`bet_status` " .
      //  "FROM $this->_table LEFT JOIN hl_resource_schedule ON `$this->_table`.`resource_id` = `hl_resource_schedule`.`resource_id` " .
      //  "WHERE `$this->_table`.`expert_id` = $expertId AND `$this->_table`.resource_status = 1 AND `$this->_table`.`$platform_field` = 1 AND hl_resource_schedule.`bet_status` > 0 " . 
      //  "ORDER BY `$this->_table`.`release_time` desc LIMIT 0,$limit";
      $sql = "SELECT r.`resource_id`,s.`bet_status`,e.`bet_status` AS manual_bet_status FROM hl_resource r LEFT JOIN hl_resource_schedule s ON r.`resource_id` = s.`resource_id` AND s.`schedule_status` = 0 LEFT JOIN `hl_resource_extra` e ON r.`resource_id` = e.`resource_id` WHERE r.`expert_id` = $expertId AND r.resource_status = 1  AND (s.`bet_status` <> 0 OR e.`bet_status` <> 0) ORDER BY r.`release_time` DESC LIMIT 0," . $limit;
      $data = $this->getDB($sql)->executeRows($sql);
      $resourceModel = new ResourceModel();
      foreach ($data as $k => $item) {
          if ($item['manual_bet_status']) {
              $data[$k]['bet_status'] = $item['manual_bet_status'];
          } else {
              $resourceScheduleList = $resourceModel->getResourceScheduleList($item['resource_id']);
              $bet_status = $resourceModel->getBetStatus($resourceScheduleList);
              $data[$k]['bet_status'] = $bet_status;
          }
      }
      return $data;
    }

    public function getResourceListByMatch($start = 0, $pagesize = 20, $platform = 1, $match_num = 0, $match_type = 1){
        if ($match_num) {
            $dal_rs = new DALResourceSchedule($this->_appSetting);
            $res_ids = $dal_rs->getResourceByMatch($match_num, $match_type);
            if (empty($res_ids)) {
                return [];
            }
            $resource_id = [];
            foreach ($res_ids as $v) {
               $resource_id[] = $v['resource_id'];
            }
            $resource_ids = implode(',', $resource_id);
        }
        $sql = "SELECT r.*, e.*, ea.`max_bet_record`, ea.`max_bet_record_v2` FROM `$this->_table` as r LEFT JOIN `hl_user_expert_extra` as ea ON r.expert_id=ea.expert_id LEFT JOIN `hl_user_expert` as e ON r.expert_id=e.expert_id WHERE 1";
        switch($platform){
            case 1:
                $sql .= " AND `bd_display` = 1";
                break;
            case 2:
                $sql .= " AND `wx_display` = 1";
                break;
        }
        $sql .= " AND r.`resource_status` = 1 AND e.`expert_status` = 1";
        if ($match_num) {
            $sql .= " AND r.resource_id in ($resource_ids)";
        }
        $sql .= " ORDER BY r.`is_schedule_over` ASC, ";
        switch($platform){
            case 1:
                $sql .= "`bd_placement` DESC, ";
                break;
            case 2:
                $sql .= "`wx_placement` DESC, ";
                break;
        }
        $sql .= "r.`schedule_over_date` DESC, ea.`max_bet_record_v2` DESC, r.`release_time` DESC";
        $sql .= " limit {$start},{$pagesize}";
        return $this->getDB($sql)->executeRows($sql);
    }
    /*
    *获取料关联得赛事
    */
    public function getResourceSchedulesV2($condition = '', $fields = '') {
        $sql = 'SELECT ' . (empty($fields) ? '*' : $fields) . ', e.bet_status as manual_bet_status  FROM ' . $this->_table ." as a left join hl_resource_schedule  on a.resource_id=hl_resource_schedule.resource_id and hl_resource_schedule.schedule_status = 0 left join hl_resource_extra e on a.resource_id = e.resource_id ";
        $sql .= ' WHERE ' . $condition;
        return $this->getDB($sql)->executeRows($sql);
    }


    /**
     * 条件修改置顶
     */
    public function updateSort($condition, $sort) {
        $sortData = $sort * 100000000;
        if(!$sort) {
            $sortData = $sort;
        }
        $up_time = time();
        //$sql = "UPDATE `$this->_table` SET `modify_time`=$up_time,`sort`= `create_time`+$sortData WHERE 1=1";
        $sql = "UPDATE `$this->_table` SET `modify_time`=$up_time,`sort`= $sortData WHERE 1=1";
        if(!empty($condition)) {
            foreach($condition as $key => $val) {
                if (is_array($val)) {
                    $sql .= " AND `$key` $val[0] '$val[1]'";
                } else {
                    $sql .= " AND `$key` = '$val'";
                }
            }
        }
        return $this->getDB($sql)->executeNoResult($sql);
    }

    public function getAttentionList($condition = array(), $fields = array(), $offset = 0, $limit = 20, $orderBy = array()) {
      return $this->select($this->_attention_table, $condition, $fields, $offset, $limit, $orderBy);
    }

    public function getAttentionCount($condition = []) {
      return $this->counts($this->_attention_table, $condition);
    }

    public function getAttentionInfo($condition = []) {
      return $this->get($this->_attention_table, $condition);
    }

    public function createAttention($data) {
      return $this->insertData($data, $this->_attention_table);
    }

    public function updateAttentionInfo($data, $condition = []) {
      return $this->update($this->_attention_table, $data, $condition);
    }

    public function getResourceSchedules($condition = [], $fields = [], $offset = 0, $limit = 0, $orderBy = [], $join = []) {
      return $this->getAll($this->_table, $condition, $fields, $offset, $limit, $orderBy, $join);
    }

    public function getAllResources($condition, $fields, $offset, $limit, $orderBy, $join = []) {
      return $this->getAll($this->_table, $condition, $fields, $offset, $limit, $orderBy, $join);
    }
}
