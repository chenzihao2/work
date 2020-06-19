<?php
/**
 * 关注专家数据处理
 * User: YangChao
 * Date: 2018/10/19
 */

namespace QK\HaoLiao\DAL;


use QK\WSF\Settings\AppSetting;

class DALUserFollowExpert extends BaseDAL {
    protected $_table = "hl_user_follow_expert";

    public function __construct(AppSetting $appSetting){
        parent::__construct($appSetting);
    }

    /**
     * 关注专家或取关专家
     * @param     $userId
     * @param     $expertId
     * @param int $followStatus
     * @return int
     */
    public function updateFollow($userId, $expertId, $followStatus = 1){
        $time = time();
        $sql = "INSERT INTO `$this->_table` (`user_id`,`expert_id`,`follow_status`,`create_time`) VALUES ('$userId','$expertId','$followStatus','$time') ON DUPLICATE KEY UPDATE `follow_status`=$followStatus";
        return $this->getDB($sql)->executeNoResult($sql);
    }

    /**
     * 获取关注专家数
     * @param $userId
     * @return mixed
     */
    public function getUserFollowExpertNumber($userId){
        $sql = "SELECT COUNT(*) FROM `$this->_table` WHERE  `user_id` = $userId AND `follow_status`=1";
        return $this->getDB($sql)->executeValue($sql);
    }

    /**
     * 获取关注专家列表
     * @param $userId
     * @param $page
     * @param $size
     * @return mixed
     */
    public function getUserFollowExpertList($userId, $page = 0, $size = 0){
        $sql = "SELECT `expert_id` FROM `$this->_table` WHERE  `user_id` in($userId) AND `follow_status`=1";
        if($page && $size){
            $start = ($page - 1) * $size;
            $sql .= " LIMIT $start,$size";
        }
        return $this->getDB($sql)->executeRows($sql);
    }

    public function getFollowList($condition = array(), $fields = array(), $offset = 0, $limit = 0, $orderBy = array()){
      return $this->select($this->_table, $condition, $fields, $offset, $limit, $orderBy);
    }

    /**
     * 获取关注专家的用户列表
     * @param $expertId
     * @return mixed
     */
    public function getExpertFollowList($expertId){
        $sql = "SELECT `id`, `user_id`, `expert_id`, `follow_status`, `create_time` FROM `$this->_table` WHERE  `expert_id` = $expertId AND `follow_status`=1";
        return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 检测是否关注专家
     * @param $userId
     * @param $expertId
     * @return mixed
     */
    public function checkUserFollowExpert($userId, $expertId){
        $sql = "SELECT `expert_id` FROM `$this->_table` WHERE  `user_id` = $userId AND `expert_id` = $expertId AND `follow_status`=1";
        return $this->getDB($sql)->executeRow($sql);
    }

    public function getFollowByCondition($condition = array(), $fields = array()) {
      return $this->get($this->_table, $condition, $fields);
    }
	
   /*
     * 根据最新推荐排序 获取关注的专家
     * 新增
     */

     public function getNewFollowList($user_id, $offset = 0, $limit = 0, $orderBy = array(),$platform=2){

        //$sql="SELECT ANY_VALUE(expert_id)as expert_id, count(expert_id) as count FROM `hl_resource` where  expert_id in(select expert_id from hl_user_follow_expert where follow_status=1 and user_id in($user_id)) GROUP BY `expert_id` ORDER BY ANY_VALUE(create_time) DESC LIMIT $offset , $limit";
        //$sql="select ANY_VALUE(a.expert_id)as expert_id, count(a.expert_id) as count from hl_user_follow_expert as a left join hl_resource as b on a.expert_id=b.expert_id where a.follow_status=1 and b.resource_status=1 and b.is_schedule_over=0 and  a.user_id in($user_id) group by a.expert_id order by ANY_VALUE(b.create_time) desc LIMIT $offset , $limit";
		$sql="select a.expert_id from hl_user_follow_expert as a left join hl_user_expert as b on a.expert_id=b.expert_id where a.follow_status=1 and a.user_id in($user_id) and b.platform in(0,$platform) order by a.id desc LIMIT $offset , $limit";
	   
	   return $this->getDB($sql)->executeRows($sql);
    }



}
