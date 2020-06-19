<?php
/**
 * 比赛赛程信息数据处理类
 * User: WangHui
 * Date: 2018/10/11
 */

namespace QK\HaoLiao\DAL;


use QK\HaoLiao\Common\StringHandler;

class DALMatchSchedule extends BaseDAL {
    private $_table = "";
    private $table_map = [
      1 => 'hl_soccer_match',
      2 => 'hl_basketball_match'
    ];
    private $information_table = "hl_match_information";


    /**
     * 获取赛事列表
     * @param null $matchType
     * @param int $leagueId
     * @param null $scheduleStatus
     * @param null $scheduleStartTime
     * @param null $scheduleEndTime
     * @param      $start
     * @param      $pagesize
     * @return array|bool
     */
    public function getScheduleList($matchType = null, $leagueId = 0, $scheduleStatus = null, $result = null, $scheduleStartTime = null, $scheduleEndTime = null, $start, $pagesize) {
        $scheduleStartTime = $scheduleStartTime !== null ? $scheduleStartTime : time();
        $sql = "SELECT `schedule_id`, `$this->_table`.`match_type`, `$this->_table`.`league_id`,`hl_match_league`.`league_name`, `master_team`, `master_score`, `guest_team`, `guest_score`, `schedule_time`, `result`, `is_recommend`, `schedule_status` FROM `$this->_table` LEFT JOIN `hl_match_league` ON `$this->_table`.`league_id` =`hl_match_league`.`league_id`  WHERE 1";
        if ($matchType !== null) {
            $sql .= " AND `$this->_table`.`match_type` = $matchType";
        }
        if ($leagueId) {
            $sql .= " AND `$this->_table`.`league_id` = $leagueId";
        }
        if ($scheduleStatus !== null) {
            $sql .= " AND `schedule_status` >= $scheduleStatus";
        }
        if ($result !== null) {
            $sql .= " AND `result` = $result";
        }
        if ($scheduleStartTime !== null) {
            $sql .= " AND `schedule_time` >= $scheduleStartTime";
        }

        if ($scheduleEndTime !== null) {
            $sql .= " AND `schedule_time` < $scheduleEndTime";
        }
        if ($pagesize) {
            $sql .= " limit {$start},{$pagesize}";
        }
        return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 获取赛事总条数
     * @param null $matchType
     * @param int $leagueId
     * @param null $scheduleStatus
     * @param null $scheduleStartTime
     * @param null $scheduleEndTime
     * @return mixed
     */
    public function getScheduleTotal($matchType = null, $leagueId = 0, $scheduleStatus = null, $result = null, $scheduleStartTime = null, $scheduleEndTime = null) {
        $this->_table = $this->table_map[$matchType];
        $scheduleStartTime = $scheduleStartTime !== null ? $scheduleStartTime : time();

        $sql = "SELECT COUNT(`id`) AS total FROM `$this->_table` WHERE 1";
        if ($matchType !== null) {
            $sql .= " AND `match_type` = $matchType";
        }
        if ($leagueId) {
            $sql .= " AND `league_num` = $leagueId";
        }
        //if ($scheduleStatus !== null) {
        //    $sql .= " AND `schedule_status` = $scheduleStatus";
        //}
        if ($result !== null) {
            $sql .= " AND `status` = $result";
        }
        if ($scheduleStartTime !== null) {
            $sql .= " AND `date` >= $scheduleStartTime";
        }

        if ($scheduleEndTime !== null) {
            $sql .= " AND `date` < $scheduleEndTime";
        }
        return $this->getDB($sql)->executeValue($sql);

    }

    /**
     * 获取赛事列表(多联赛)
     * @param $scheduleTime
     * @param $start
     * @param $pageSize
     * @param null $matchType
     * @param string $leagueId
     * @param int $scheduleStatus
     * @return array|bool
     */
    public function scheduleList($scheduleTime, $start, $pageSize, $matchType = null, $leagueId = "", $scheduleStatus = 2) {
        $startTime = $scheduleTime ? $scheduleTime : time();
        $endTime = strtotime(date("Y-m-d 23:59:59", $scheduleTime));
        $sql = "SELECT `schedule_id`, `$this->_table`.`match_type`, `$this->_table`.`league_id`, `hl_match_league`.`league_name`, `master_team`, `master_score`, `guest_team`, `guest_score`, `schedule_time`, `result`, `is_recommend`, `schedule_status` FROM `$this->_table` LEFT JOIN `hl_match_league` ON `$this->_table`.`league_id` =`hl_match_league`.`league_id`  WHERE `schedule_time` >= $startTime AND `schedule_time`<=$endTime";
        if ($matchType !== null) {
            $sql .= " AND `$this->_table`.`match_type` = $matchType";
        }
        if ($leagueId != "") {
            $sql .= " AND `$this->_table`.`league_id` in ($leagueId)";
        }
        $sql .= " AND `schedule_status` >= $scheduleStatus";
        $sql .= " ORDER BY `is_recommend` DESC, ";
        $sql .= " `schedule_time` ASC ";
        $sql .= " limit {$start},{$pageSize}";
        return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 热门赛事列表
     * @param $page
     * @param $size
     * @return array|bool
     */
    public function hotScheduleList($page, $size) {
        $start = ($page - 1) * $size;
        $scheduleTime = time();
        $sql = "SELECT `schedule_id`, `$this->_table`.`match_type`, `$this->_table`.`league_id`, `hl_match_league`.`league_name`, `master_team`, `master_score`, `guest_team`, `guest_score`, `schedule_time`, `result`, `is_recommend`, `schedule_status` FROM `$this->_table` LEFT JOIN `hl_match_league` ON `$this->_table`.`league_id` =`hl_match_league`.`league_id`  WHERE `schedule_time` >= $scheduleTime AND `schedule_status` >= 2 AND `is_recommend` = 1 ORDER BY `schedule_time` ASC  limit {$start},{$size}";
        return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 热门赛事数量
     * @return mixed
     */
    public function hotScheduleCount() {
        $scheduleTime = time();
        $sql = "SELECT count(*) FROM `$this->_table` WHERE `schedule_time` >= $scheduleTime AND `schedule_status` >= 2 AND `is_recommend` = 1";
        return $this->getDB($sql)->executeValue($sql);
    }

    /**
     * 根据赛事id获取赛事信息
     * @param $scheduleId
     * @return mixed
     */
    public function getScheduleInfo($scheduleId, $type) {
        //$this->_table = $this->table_map[$type];
        $sql = "SELECT `schedule_id`, `$this->_table`.`match_type`, `$this->_table`.`league_id`, `hl_match_league`.`league_name`, `master_team`, `master_score`, `guest_team`, `guest_score`, `schedule_time`, `result`, `is_recommend`, `schedule_status` FROM `$this->_table` LEFT JOIN `hl_match_league` ON `$this->_table`.`league_id` =`hl_match_league`.`league_id`  WHERE `schedule_id` = $scheduleId";
        return $this->getDB($sql)->executeRow($sql);
    }

    /**
     * 检查赛事是否存在(数据抓取使用)
     * @param $master
     * @param $guess
     * @param $time
     * @return mixed
     */
    public function checkSchedule($master, $guess, $time) {
        $sql = "SELECT `schedule_id` FROM `$this->_table` WHERE `schedule_time` = $time AND `master_team`='$master' AND `guest_team`='$guess'";
        return $this->getDB($sql)->executeValue($sql);
    }

    /**
     * 新建一场比赛
     * @param $params
     */
    public function newMatchSchedule($params) {
        $this->insertData($params, $this->_table);
    }

    /**
     * 更新
     * @param $scheduleId
     * @param $params
     * @return int
     */
    public function updateMatchSchedule($scheduleId, $params) {
        $updateString = StringHandler::newInstance()->getDBUpdateString($params);
        $sql = "UPDATE `$this->_table` SET $updateString WHERE `schedule_id` = " . $scheduleId;
        $result = $this->getDB($sql)->executeNoResult($sql);
        return $result;
    }


    /**
     * 获取今日联赛信息
     * @param $matchType
     * @param $startTime
     * @param $endTime
     * @param $scheduleStatus
     * @return array|bool
     */
    public function getLeagueListByTime($matchType, $startTime, $endTime, $scheduleStatus) {
        $this->_table = $this->table_map[$matchType];
        $sql = "SELECT `$this->_table`. league_num as `league_id`,hl_league.`initial`,`hl_league`.`short_name` as league_name,COUNT($this->_table.id) AS `count` FROM `$this->_table` LEFT JOIN `hl_league` ON `$this->_table`.`league_num` =`hl_league`.`league_num` WHERE $this->_table.`date`>='$startTime' AND $this->_table.`date`<='$endTime' AND hl_league.`type`=$matchType GROUP BY hl_league.`id` order by initial,hl_league.short_name asc";
        //$sql = "select any_value(m.league_num) as league_id, l.initial, l.short_name as league_name, count(m.id) as count from $this->_table m left join (select * from hl_league where type = $matchType order by initial,short_name asc) l on m.league_num = l.league_num where m.`date` >= '$startTime' and m.`date` <= '$endTime' group by l.id order by l.initial,l.short_name asc";
        return $this->getDB($sql)->executeRows($sql);
    }


    /**
     * 获取赛事列表(多联赛)
     * @param        $scheduleTime
     * @param null   $matchType
     * @param string $leagueId
     * @param int    $scheduleStatus
     * @param int    $page
     * @param int    $pageSize
     * @return array|bool
     */
    public function newScheduleList($scheduleTime, $matchType = null, $leagueId = "", $scheduleStatus = 2, $page = 1, $pageSize = 10) {
        $start = ($page - 1) * $pageSize;
        $startTime = $scheduleTime ? $scheduleTime : time();
        $endTime = strtotime(date("Y-m-d 23:59:59", $scheduleTime));
        $sql = "SELECT `schedule_id`, `$this->_table`.`match_type`, `$this->_table`.`league_id`, `hl_match_league`.`league_name`, `master_team`, `master_score`, `guest_team`, `guest_score`, `schedule_time`, `result`, `is_recommend`, `schedule_status` FROM `$this->_table` LEFT JOIN `hl_match_league` ON `$this->_table`.`league_id` =`hl_match_league`.`league_id`  WHERE `schedule_time` >= $startTime AND `schedule_time`<=$endTime";
        if ($matchType !== null) {
            $sql .= " AND `$this->_table`.`match_type` = $matchType";
        }
        if ($leagueId != "") {
            $sql .= " AND `$this->_table`.`league_id` in ($leagueId)";
        }
        $sql .= " AND `schedule_status` = $scheduleStatus";
        $sql .= " ORDER BY `is_recommend` DESC, ";
        $sql .= " `schedule_time` ASC ";
        $sql .= " limit {$start},{$pageSize}";
        return $this->getDB($sql)->executeRows($sql);
    }

    public function getScheduleListV2($startTime, $endTime, $offset, $limit, $orderBy) {
      $sql = "SELECT `schedule_id`, `$this->_table`.`match_type`, `$this->_table`.`league_id`,`hl_match_league`.`league_name`, `master_team`, `master_score`, `guest_team`, `guest_score`, `schedule_time`, `result`, `is_recommend`, `schedule_status` FROM `$this->_table` LEFT JOIN `hl_match_league` ON `$this->_table`.`league_id` =`hl_match_league`.`league_id`  WHERE 1";
      $sql .= " AND `$this->_table`.`match_type` = 1";
      if($startTime) {
	$sql .= " AND `schedule_time` > $startTime";
      }
      if($endTime) {
        $sql .= " AND `schedule_time` <= $endTime";
      }
      //$sql .= $this->parseCondition($condition);
      if (!empty($orderBy)) {
        $ordersArr = array();
        foreach($orderBy as $orderKey => $orderVal) {
            $ordersArr[] = "`$orderKey` $orderVal";
        }
        $orderStr = implode(',', $ordersArr);
        $sql .= " ORDER BY $orderStr";
      }

      if ($limit) {
        $sql .= " limit $limit";
      }
      if (!empty($offset)) {
        $sql .= " offset $offset";
      }
      return $this->getDB($sql)->executeRows($sql);
    }

    public function updateMatchInfo($data, $match_num, $match_type) {
      return $this->updateByCondition(['match_num' => $match_num], $data, $this->table_map[$match_type]);
    }

    public function getMatchInformation($match_num, $match_type) {
      return $this->get($this->information_table, ['match_num' => $match_num, 'match_type' => $match_type]);
    }

    public function updateMatchInformation($updateInfo, $condition = array()) {
      return $this->update($this->information_table, $updateInfo, $condition);
    }

    public function addMatchInformation($data) {
      return $this->insertData($data, $this->information_table);
    }

}
