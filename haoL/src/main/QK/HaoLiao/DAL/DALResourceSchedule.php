<?php
/**
 * 料关联比赛数据处理
 * User: YangChao
 * Date: 2018/10/12
 */

namespace QK\HaoLiao\DAL;

use QK\WSF\Settings\AppSetting;

class DALResourceSchedule extends BaseDAL {
    protected $_table = "hl_resource_schedule";

    public function __construct(AppSetting $appSetting) {
        parent::__construct($appSetting);
    }

    /**
     * 新建一个料关联赛事
     * @param $resourceSchedule
     * @return int
     */
    public function createResourceSchedule($resourceSchedule) {
        $res = $this->insertData($resourceSchedule, $this->_table);
        return $res;
    }


    /**
     * 获取料关联赛事数据
     * @param $resourceId
     * @param int $detailId
     * @return mixed
     */
    public function getResourceScheduleList($resourceId, $detailId = 0) {
        $sql = "SELECT `id`, `resource_id`, `detail_id`, `league_id`, `schedule_id`, `type`, `lottery_type`, `lottery_id`,`bet_status`, `lottery_result`,`h`,`w`,`d`,`l`,`recommend` FROM `$this->_table` WHERE `resource_id` = $resourceId AND `schedule_status`=0";
        if ($detailId) {
            $sql .= " AND `detail_id` = $detailId";
        }
        return $this->getDB($sql)->executeRows($sql);
    }

    public function syncData() {
        $sql = "SELECT `id`, `resource_id`, `detail_id`, `league_id`, `schedule_id` FROM `$this->_table`";
        $relations = $this->getDB($sql)->executeRows($sql);
        foreach ($relations as $v) {
            $format_data = [];
            $res_id = $v['resource_id'];
            $sql_league = "select league_name from hl_match_league where league_id = " . $v['league_id'];
            $league = $this->getDB($sql_league)->executeRows($sql_league);
            $format_data['league_name'] = $league[0]['league_name'] ?: '';
            $sql_match = "select match_type,master_team,guest_team,schedule_time from hl_match_schedule where schedule_id = ". $v['schedule_id'];
            $match = $this->getDB($sql_match)->executeRows($sql_match);
            $format_data['host_name'] = $match[0]['master_team'] ?: '';
            $format_data['guest_name'] = $match[0]['guest_team'] ?: '';
            if ($match[0]['schedule_time']) {
                $format_data['date'] = date('Y-m-d H:i:s', $match[0]['schedule_time']);
            }
            $format_data['resource_id'] = $res_id;
            $format_data['type'] = $match[0]['match_type'] ?: 0;
            $exist_sql = "select id from hl_resource_match where resource_id = $res_id and league_name = '" . $format_data['league_name'] . "' and host_name = '" . $format_data['host_name'] . "' and guest_name = '" . $format_data['guest_name'] . "'";
            $exist = $this->getDB($exist_sql)->executeRows($exist_sql);
            if (!$exist) {
              $this->insertData($format_data, 'hl_resource_match');
            }
        }
    }

    public function getResourceMatchInfo($res_id) {
            $sql = "select * from hl_resource_match where resource_id = $res_id"; 
            return $this->getDB($sql)->executeRows($sql);
    }

    public function getResourceByMatch($match_num, $type = 1) {
            $sql = "select resource_id from $this->_table where schedule_id = $match_num and type= $type";
            return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 获取赛事发料专家数
     * @param $scheduleId
     * @return mixed
     */
    public function getScheduleExpertNum($scheduleId) {
        $sql = "SELECT COUNT(DISTINCT `expert_id`) FROM `$this->_table` LEFT JOIN `hl_resource` ON `$this->_table`.`resource_id`= `hl_resource`.`resource_id` WHERE `schedule_id`=$scheduleId AND `$this->_table`.`schedule_status` = 0 AND `resource_status`=1";
        return $this->getDB($sql)->executeValue($sql);
    }
    /**
     * 获取赛事发料数
     * @param $scheduleId
     * @return mixed
     */
    public function getScheduleResourceNum($scheduleId) {
        $sql = "SELECT COUNT(DISTINCT `hl_resource`.`resource_id`) FROM `$this->_table` LEFT JOIN `hl_resource` ON `$this->_table`.`resource_id`= `hl_resource`.`resource_id` WHERE `schedule_id`=$scheduleId AND `$this->_table`.`schedule_status` = 0 AND `resource_status`=1";
        return $this->getDB($sql)->executeValue($sql);
    }

    /**
     * 赛事推荐料
     * @param $scheduleId
     * @param $page
     * @param $size
     * @return array|bool
     */
    public function getScheduleResourceList($scheduleId, $page, $size) {
        $start = ($page - 1) * $size;
        $sql = "SELECT `hl_resource`.`resource_id` AS `resource_id` FROM `$this->_table` LEFT JOIN `hl_resource` ON `$this->_table`.`resource_id`=`hl_resource`.`resource_id` WHERE `schedule_id`=$scheduleId AND `resource_status`=1 AND `$this->_table`.`schedule_status` = 0 GROUP  BY `hl_resource`.`resource_id` limit $start,$size";
        return $this->getDB($sql)->executeRows($sql);
    }


    /**
     * 删除料赛事关联信息（后台）
     * @param $resourceId
     * @return int
     */
    public function deleteResourceSchedule($resourceId) {
        $sql = 'UPDATE `' . $this->_table . '` SET `schedule_status`=1 WHERE `resource_id`=' . $resourceId;
        return $this->getDB($sql)->executeNoResult($sql);
    }

    /**
     * 获取料的比赛类型，不中退款料统计使用（后台统计用）
     * @param $resourceId
     * @return mixed
     */
    public function getResourceMatchType($resourceId) {
        //$sql = "SELECT `hl_match_schedule`.`match_type` AS `match_type` FROM `$this->_table` LEFT JOIN `hl_match_schedule` ON `$this->_table`.`schedule_id` = `hl_match_schedule`.`schedule_id` WHERE `resource_id`=$resourceId";
        //return $this->getDB($sql)->executeValue($sql);
        $result = $this->get($this->_table, ['resource_id' => $resourceId], ['type']);
        if ($result) {
            return $result['type'];
        } else {
            return 0;
        }
    }

    /**
     * 检查赛事是否有关联料
     * @param $scheduleId
     * @return mixed
     */
    public function checkHasSource($scheduleId) {
        $sql = "SELECT count(*) FROM `$this->_table` LEFT JOIN `hl_resource` ON `$this->_table`.`resource_id`=`hl_resource`.`resource_id` WHERE `schedule_id`=$scheduleId AND `resource_status`=1 AND `$this->_table`.`schedule_status` = 0";
        return $this->getDB($sql)->executeValue($sql);


    }

    public function getResourceScheduleInfo($condition = []) {
      return $this->get($this->_table, $condition);
    }

    public function getMatchWithResource($st, $et, $match_type = 1) {
      $related_table = ($match_type == 1) ? 'hl_soccer_match' : 'hl_basketball_match';
      $status=($match_type == 1) ? '4':'9,11';
      $sql = "SELECT `$this->_table`.* from $this->_table LEFT JOIN $related_table ON `$this->_table`.`schedule_id` = `$related_table`.`match_num` left join hl_resource  s on `$this->_table`.resource_id = s.resource_id  WHERE `$this->_table`.schedule_status = 0 AND `$related_table`.`status` in('$status')  AND `$this->_table`.bet_status = 0 AND `$related_table`.`date` BETWEEN '$st' AND '$et' and s.is_auto_bet = 1";
      return $this->getDB($sql)->executeRows($sql);
    }

    public function updateResoureMatch($updateInfo, $condition = []) {
      return $this->update($this->_table, $updateInfo, $condition);
    }

    public function getResourceSchedules($condition = [], $fields = [], $offset = 0, $limit = 0, $orderBy = []) {
      return $this->select($this->_table, $condition, $fields, $offset, $limit, $orderBy);
    }
}
