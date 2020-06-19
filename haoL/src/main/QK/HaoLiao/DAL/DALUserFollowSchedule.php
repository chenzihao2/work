<?php
/**
 * 用户关注赛事
 * User: WangHui
 * Date: 2018/10/19
 * Time: 上午11:46
 */

namespace QK\HaoLiao\DAL;


class DALUserFollowSchedule extends BaseDAL {
    private $_table = 'hl_user_follow_schedule';

    /**
     * 关注赛事或取关赛事
     * @param $userId
     * @param $scheduleId
     * @param int $followStatus
     * @return int
     */
    public function updateFollow($userId, $scheduleId, $followStatus = 1) {
        $time = time();
        $sql = "INSERT INTO `$this->_table` (`user_id`,`schedule_id`,`follow_status`,`create_time`) VALUES ('$userId','$scheduleId','$followStatus','$time') ON DUPLICATE KEY UPDATE `follow_status`=$followStatus";
        return $this->getDB($sql)->executeNoResult($sql);

    }

    /**
     * 获取关注赛事的用户列表
     * @param $scheduleId
     * @return array|bool
     */
    public function getScheduleFollowList($scheduleId){
        $sql = "SELECT `id`, `user_id`, `schedule_id`, `follow_status`, `create_time` FROM `$this->_table` WHERE  `schedule_id` = $scheduleId AND `follow_status`=1";
        return $this->getDB($sql)->executeRows($sql);
    }

}