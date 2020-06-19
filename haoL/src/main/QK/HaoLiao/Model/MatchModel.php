<?php
/**
 * 赛事信息处理类
 * User: YangChao
 * Date: 2018/10/11
 */

namespace QK\HaoLiao\Model;

use QK\HaoLiao\DAL\DALMatchLeague;
use QK\HaoLiao\DAL\DALMatchSchedule;
use QK\HaoLiao\DAL\DALResourceSchedule;
use QK\HaoLiao\DAL\DALSoccerMatch;
use QK\HaoLiao\DAL\DALBasketballMatch;
use QK\HaoLiao\Model\SoccerModel;
use QK\HaoLiao\Model\BasketballModel;


class MatchModel extends BaseModel {

    private $_redisModel;

    public function __construct() {
        parent::__construct();
        $this->_redisModel = new RedisModel("match");
        $this->match_dal = new DALMatchLeague($this->_appSetting);
        $this->soccer_dal = new DALSoccerMatch($this->_appSetting);
        $this->basket_dal = new DALBasketballMatch($this->_appSetting);
    }

    public function importLeague() {
        $data = $this->soccer_dal->getOldLeague();
        foreach ($data as $v) {
            $tmp = [];
            $tmp['old_id'] = $v['league_id'];
            $tmp['type'] = $v['match_type'];
            $tmp['initial'] = $v['initial'];
            $tmp['name'] = $v['league_name'];
            $tmp['short_name'] = $v['crawler_name'];
            //$this->soccer_dal->addLeague($tmp);
            $same_data = [];
            $same_data = $this->soccer_dal->sameLeague(['name' => $tmp['name'], 'short_name' => $tmp['short_name']], $tmp['type']);
            var_dump($tmp);
            var_dump($same_data);
            if ($same_data) {
                    $this->soccer_dal->updateLeague(['old_id' => $tmp['old_id']], $same_data['league_num']);
                    $res = $this->soccer_dal->updateRelationLeague(['league_num' => $same_data['league_num']], $tmp['old_id']);
            }
        }
    }

    /**
     * 获取联赛列表（后台）
     * @param $query
     * @param $page
     * @param $size
     * @return array|bool
     */
    public function getLeagueList($query,$page,$size) {
        $map = ['match_type' => 'type', 'league_name' => 'name'];
        foreach ($query as $k => $v) {
            $query[$map[$k]] = $v;
            unset($query[$k]);
        }
        $dalMatchLeague = new DALMatchLeague($this->_appSetting);
        $leagueList = $dalMatchLeague->getLeagueListByQuery($query, $page,$size);
        return $leagueList;
    }

    /**
     * 获取联赛列表（后台）
     * @param $query
     * @return int
     */
    public function getLeagueCount($query) {
        $map = ['match_type' => 'type', 'league_name' => 'name'];
        foreach ($query as $k => $v) {
            $query[$map[$k]] = $v;
            unset($query[$k]);
        }
        $dalMatchLeague = new DALMatchLeague($this->_appSetting);
        $leagueList = $dalMatchLeague->getLeagueCountByQuery($query);
        return $leagueList;
    }

    /**
     * 获取某日内有比赛的联赛列表
     * @param $matchType
     * @param $time
     * @param $scheduleStatus
     * @param bool $needInitial
     * @return array|bool
     */
    public function leagueList($matchType, $time, $scheduleStatus, $needInitial = true, $endTime = '') {
        $dalMatchSchedule = new DALMatchSchedule($this->_appSetting);
        $startTime = date("Y-m-d H:i:s", $time);
        $endTime = $endTime ? date('Y-m-d H:i:s', $endTime) : date("Y-m-d 23:59:59", $time);
        $leagueList = $dalMatchSchedule->getLeagueListByTime($matchType, $startTime, $endTime,$scheduleStatus);
        if($needInitial){
            foreach ($leagueList as $league) {
                $finalLeagueList[$league['initial']][] = $league;
            }
            ksort($finalLeagueList);
            return $finalLeagueList;
        } else {
	        //$initial_key =  array_column($leagueList, 'initial');
            //array_multisort($initial_key, SORT_ASC, $leagueList);
            return $leagueList;
        }
    }

    /**
     * 获取全部联赛列表
     * @param $matchType
     * @return array|bool
     */
    public function leagueTotalList($matchType) {
        $dalMatchLeague = new DALMatchLeague($this->_appSetting);
        $leagueList = $dalMatchLeague->getLeagueList($matchType);
        return $leagueList;
    }
    /**
     * 获取联赛详情数据
     * @param $leagueId
     * @return array|bool|mixed|null|string
     */
    public function getLeagueInfo($leagueId) {
        $redisKey = MATCH_LEAGUE_INFO . $leagueId;
        $leagueInfo = $this->_redisModel->redisGet($redisKey, true);
        if (empty($leagueInfo)) {
            $dalMatchLeague = new DALMatchLeague($this->_appSetting);
            $leagueInfo = $dalMatchLeague->getLeagueInfoById($leagueId);
            $this->_redisModel->redisSet($redisKey, $leagueInfo);
        }
        return $leagueInfo;
    }

    /**
     * 获取赛事总条数
     * @param      $matchType
     * @param      $leagueId
     * @param null $scheduleStatus
     * @param null $scheduleStartTime
     * @param null $scheduleEndTime
     * @return mixed
     */
    public function getScheduleTotal($matchType, $leagueId, $scheduleStatus = null, $result = null, $scheduleStartTime = null, $scheduleEndTime = null) {
        $dalMatchSchedule = new DALMatchSchedule($this->_appSetting);
        $scheduleTotal = $dalMatchSchedule->getScheduleTotal($matchType, $leagueId, $scheduleStatus, $result, $scheduleStartTime, $scheduleEndTime);
        return $scheduleTotal;
    }

    /**
     * 获取赛事列表
     * @param      $matchType
     * @param      $leagueId
     * @param null $scheduleStatus
     * @param null $scheduleStartTime
     * @param null $scheduleEndTime
     * @param      $page
     * @param      $pagesize
     * @return array|bool
     */
    public function getScheduleList($matchType, $leagueId, $scheduleStatus = null, $result = null, $scheduleStartTime = null, $scheduleEndTime = null, $page, $pagesize, $hasInformation = -1) {
        if ($matchType == 1) {
            $condition['status'] = $result;
            $condition['league_num'] = $leagueId;
            $condition['has_information'] = $hasInformation;
            $condition['page_num'] = $pagesize;
            $condition['page'] = $page;
            $dalMatchSchedule = new SoccerModel($this->_appSetting);
            $scheduleList = $dalMatchSchedule->matchAdmin($scheduleStartTime, $scheduleEndTime, $condition);
            if (!empty($scheduleList['data'])) {
                foreach ($scheduleList['data'] as $key => $val) {
                    $time = strtotime($val['date'] . $val['time']);
                    $formatScheduleTime = $this->formatScheduleTime($time);
                    $val = array_merge($val, $formatScheduleTime);
                    $val['type'] = $matchType;
                    $match_detail = $this->soccer_dal->getMatchDetailByNum($val['match_num']);
                    $val['confidence']='--';
                    if($match_detail['confidence']){
                        $confidenceArr=explode(' ',$match_detail['confidence']);
                        $val['confidence']=$confidenceArr[0];
                    }
                    $scheduleList['data'][$key] = $val;
                }
            }
            return $scheduleList;
        } else {
	          $val1 = ' between \'' . $scheduleStartTime . '\' ';
            $val2 = ' and \'' . $scheduleEndTime . '\' ';
	          $condition = array(
		          'date' => [$val1, $val2]
            );
            if ($hasInformation != -1) {
              $condition['has_information'] = $hasInformation;
            }
	          if ($result) {
		          $condition['status'] = $result;
	          }
	          if (!empty($leagueId)) {
            	$condition['league_num'] = $leagueId;
            }
	          $fields = ['match_num', 'date', 'league_num', 'host_team', 'guest_team', 'half', 'ascore', 'bscore', 'status', 'is_hot', 'has_information'];
	          $orderBy = ['date' => 'asc'];
	          $basketballModel = new BasketballModel($this->_appSetting);
            $scheduleList = $basketballModel->getMatchList($condition, $fields, $page, $pagesize, $orderBy);
            if (!empty($scheduleList['data'])) {
                foreach ($scheduleList['data'] as $key => $val) {
                    $time = strtotime($val['date'] . $val['time']);
                    $formatScheduleTime = $this->formatScheduleTime($time);
                    $val = array_merge($val, $formatScheduleTime);
                    $val['type'] = $matchType;
                    $match_detail = $this->basket_dal->getMatchDetail($val['match_num']);
                    $val['confidence']='--';
                    if($match_detail['confidence']){
                        $confidenceArr=explode(' ',$match_detail['confidence']);
                        $val['confidence']=$confidenceArr[0];
                    }

                    $scheduleList['data'][$key] = $val;
                }
            }
            return $scheduleList;
	}
    }

    /**
     * 专家端赛事列表（联赛多选）
     * @param $matchType
     * @param $scheduleTime
     * @param $page
     * @param $pageSize
     * @param $leagueIdList
     * @return array
     */
    public function expertScheduleList($matchType, $scheduleTime, $page, $pageSize, $leagueIdList) {
        $start = ($page - 1) * $pageSize;
        $leagueIdString = "";
        //筛选联赛列表处理
        if (!empty($leagueIdList)) {
            foreach ($leagueIdList as $leagueId) {
                $leagueIdString .= $leagueId . ",";
            }
            $leagueIdString = substr($leagueIdString, 0, -1);
        }
        $dalMatchSchedule = new DALMatchSchedule($this->_appSetting);
        $scheduleList = $dalMatchSchedule->scheduleList($scheduleTime, $start, $pageSize, $matchType, $leagueIdString, 1);
        $resultScheduleList = [];
        if (!empty($scheduleList)) {
            $over = [];
            foreach ($scheduleList as $key => $val) {
                $formatScheduleTime = $this->formatScheduleTime($val['schedule_time']);
                $val = array_merge($val, $formatScheduleTime);
                if ($val['schedule_time'] < time()) {
                    $over[] = $val;
                } else {
                    $resultScheduleList[] = $val;
                }
            }
            $resultScheduleList = array_merge($resultScheduleList, $over);
        }
        return $resultScheduleList;
    }

    /**
     * 赛事列表（用户端）
     * @param $userId
     * @param $matchType
     * @param $scheduleTime
     * @param $page
     * @param $pageSize
     * @param $leagueIdList
     * @return array
     */
    public function scheduleList($userId, $matchType, $scheduleTime, $page, $pageSize, $leagueIdList) {
        $start = ($page - 1) * $pageSize;
        $leagueIdString = "";
        //筛选联赛列表处理
        if (!empty($leagueIdList)) {
            foreach ($leagueIdList as $leagueId) {
                $leagueIdString .= $leagueId . ",";
            }
            $leagueIdString = substr($leagueIdString, 0, -1);
        }
        $dalMatchSchedule = new DALMatchSchedule($this->_appSetting);
        $scheduleList = $dalMatchSchedule->scheduleList($scheduleTime, $start, $pageSize, $matchType, $leagueIdString);
        $resultScheduleList = [];
        if (!empty($scheduleList)) {
            $over = [];
            foreach ($scheduleList as $key => $val) {
                $formatScheduleTime = $this->formatScheduleTime($val['schedule_time']);
                $val = array_merge($val, $formatScheduleTime);
                $val['expert_num'] = $this->getScheduleExpertNumber($val['schedule_id']);
                $val['resource_num'] = $this->getScheduleResourceNum($val['schedule_id']);
                $userFollowModel = new UserFollowModel();
                $val['is_follow'] = $userFollowModel->checkFollowScheduleStatus($userId, $val['schedule_id']);
                if ($val['schedule_time'] < time()) {
                    $over[] = $val;
                } else {
                    $resultScheduleList[] = $val;
                }
            }
            $resultScheduleList = array_merge($resultScheduleList, $over);
        }
        return $resultScheduleList;
    }

    /**
     * 热门赛事
     * @param $userId
     * @param $page
     * @param $pagesize
     * @return array|bool
     */
    public function hotScheduleList($userId, $page, $pagesize) {
        $dalMatchSchedule = new DALMatchSchedule($this->_appSetting);
        $scheduleList = $dalMatchSchedule->hotScheduleList($page, $pagesize);
        if (!empty($scheduleList)) {
            foreach ($scheduleList as $key => $val) {
                $formatScheduleTime = $this->formatScheduleTime($val['schedule_time']);
                $val = array_merge($val, $formatScheduleTime);
                $val['expert_num'] = $this->getScheduleExpertNumber($val['schedule_id']);
                $val['resource_num'] = $this->getScheduleResourceNum($val['schedule_id']);
                $userFollowModel = new UserFollowModel();
                $val['is_follow'] = $userFollowModel->checkFollowScheduleStatus($userId, $val['schedule_id']);
                $scheduleList[$key] = $val;
            }
        }
        $data['list'] = $scheduleList;
        $data['count'] = $dalMatchSchedule->hotScheduleCount();
        return $data;
    }

    /**
     * 获取赛事详情数据
     * @param $scheduleId
     * @return array|bool|mixed|null|string
     */
    public function getScheduleInfo($scheduleId, $type = 1) {
        empty($type) && $type = 1;
        $redisKey = MATCH_SCHEDULE_INFO . $scheduleId . $type;
        //$scheduleInfo = $this->_redisModel->redisGet($redisKey, true);
        if (empty($scheduleInfo)) {
          $is_signle = 0;     //判断比赛是否为单关
            if ($type == 1) {
                $soccerModel = new SoccerModel($this->_appSetting);
                $scheduleInfos = $soccerModel->nowInfo($scheduleId);
                $lotteryInfo = $soccerModel->getLotteryInfo($scheduleId);
                if (!empty($lotteryInfo)) {
                  $is_signle = $lotteryInfo['is_signle'];
                }
            } else if ($type == 2) {
		            $basketballModel = new BasketballModel($this->_appSetting);
                $scheduleInfos = $basketballModel->matchInfo($scheduleId);
	          }else {
                $dalMatchSchedule = new DALMatchSchedule($this->_appSetting);
                $scheduleInfos = $dalMatchSchedule->getScheduleInfo($scheduleId, $type);
            }
            if (empty($scheduleInfos)) {
                return [];
            }
            $scheduleInfo['is_signle'] = $is_signle;
            $scheduleInfo['is_jc'] = isset($scheduleInfos['is_jc']) ? $scheduleInfos['is_jc'] : 0;
            $scheduleInfo['is_bd'] = isset($scheduleInfos['is_bd']) ? $scheduleInfos['is_bd'] : 0;
            $scheduleInfo['match_type'] = $type;
            $scheduleInfo['match_num'] = $scheduleId;
            $scheduleInfo['league_num'] = $scheduleInfos['league_num'];
            $scheduleInfo['guest_team'] = $scheduleInfos['guest_team_name'];
            $scheduleInfo['league_name'] = $scheduleInfos['league_short_name'];
            $scheduleInfo['master_team'] = $scheduleInfos['host_team_name'];
            $scheduleInfo['schedule_status'] = $scheduleInfos['match_status']['status'];
            $scheduleInfo['result'] = $scheduleInfos['status'];
            $scheduleInfo['schedule_time'] = strtotime($scheduleInfos['date'] . $scheduleInfos['time']);
            $this->_redisModel->redisSet($redisKey, $scheduleInfo);
        }
        if (!empty($scheduleInfo)) {
            //比赛类型 1:足球  2:篮球 3:排球 4:冰球 5:网球 6:手球 7:电竞 8:羽毛球 9:橄榄球
            $scheduleInfo['match_type_icon'] = $this->_appSetting->getConstantSetting('STATIC_URL') . 'match_type/' . $scheduleInfo['match_type'] . '.png';

            $formatScheduleTime = $this->formatScheduleTime($scheduleInfo['schedule_time']);
            $scheduleInfo = array_merge($scheduleInfo, $formatScheduleTime);
        }
        return $scheduleInfo;
    }


    /**
     * 获取赛事专家数
     * @param $scheduleId
     * @return mixed
     */
    public function getScheduleExpertNumber($scheduleId) {
        $dalResourceSchedule = new DALResourceSchedule($this->_appSetting);
        return $dalResourceSchedule->getScheduleExpertNum($scheduleId);
    }
    /**
     * 获取赛事料数
     * @param $scheduleId
     * @return mixed
     */
    public function getScheduleResourceNum($scheduleId) {
        $dalResourceSchedule = new DALResourceSchedule($this->_appSetting);
        return $dalResourceSchedule->getScheduleResourceNum($scheduleId);
    }


    /**
     * 赛事料列表
     * @param $scheduleId
     * @return array
     */
    public function getScheduleResourceList($scheduleId, $page, $size) {
        //根据赛事id获取料id
        $redisKey = MATCH_SCHEDULE_RESOURCE_LIST . $scheduleId;

        //根据分值范围获取redis数据
        $start = ($page - 1) * $size;
        $max = $start + $size - 1;
        //根据分值范围获取redis数据
        $resourceIdList = $this->_redisModel->redisZRangeByScore($redisKey, $start, $max);
        if (empty($resourceIdList) || count($resourceIdList) != $size) {
            $resourceIdList = [];
            $dalResourceSchedule = new DALResourceSchedule($this->_appSetting);
            $resourceList = $dalResourceSchedule->getScheduleResourceList($scheduleId, $page, $size);
            if (!empty($resourceList)) {
                foreach ($resourceList as $key => $val) {
                    //相关数据入redis
                    $resourceId =$resourceIdList[] =  $val['resource_id'];
                    $this->_redisModel->redisZAdd($redisKey, $key + $start, $resourceId);
                }
            }
        }
        $resultResourceList = [];
        if (!empty($resourceIdList)) {
            $expertModel = new ExpertModel();
            $expertExtraModel = new ExpertExtraModel();
            $resourceModel = new ResourceModel();
            $betRecordModel = new BetRecordModel();
            foreach ($resourceIdList as $key => $val) {
                $info = [];
                $resourceInfo = $resourceModel->getResourceInfo($val);
                $expertInfo = $expertModel->getExpertInfo($resourceInfo['expert_id']);
                $expertExtraInfo = $expertExtraModel->getExpertExtraInfo($resourceInfo['expert_id']);
                $resourceExtraInfo = $resourceModel->getResourceExtraInfo($val);
                $info['expert_id'] = $resourceInfo['expert_id'];
                $info['expert_name'] = $expertInfo['expert_name'];
                $info['headimgurl'] = $expertInfo['headimgurl'];
                $info['resource_num'] = $expertExtraInfo['publish_resource_num'];
                $info['combat_gains_ten'] = $betRecordModel->nearTenScore($resourceInfo['expert_id']);
                $info['resource_id'] = $val;
                $info['title'] = $resourceInfo['title'];
                $info['price'] = $resourceInfo['price'];
                $info['resource_type'] = $resourceInfo['resource_type'];
                $info['view_num'] = $resourceExtraInfo['view_num'];
                $info['sold_num'] = $resourceExtraInfo['sold_num'];
                $info['is_schedule_over'] = $resourceInfo['is_schedule_over'];
                $info['create_time'] = $resourceInfo['create_time'];
                $info['release_time_friendly'] = $resourceInfo['release_time_friendly'];
                $resultResourceList[] = $info;
            }
        }
        return $resultResourceList;
    }

    /**
     * 新建一场比赛
     * @param $params
     */
    public function newMatchSchedule($params) {
        $dalMatchLeague = new DALMatchSchedule($this->_appSetting);
        return $dalMatchLeague->newMatchSchedule($params);
    }

    /**
     * 更新/添加联赛信息
     * @param $leagueId
     * @param $params
     * @return int
     */
    public function editMatchLeague($leagueId, $params) {
        $dalMatchLeague = new DALMatchLeague($this->_appSetting);
        if($leagueId){
            $result = $dalMatchLeague->updateMatchLeague($leagueId, $params);
            $this->_redisModel->redisDel(MATCH_LEAGUE_INFO.$leagueId);
        } else {
            $result = $dalMatchLeague->newMatchLeague($params);
        }
        return $result;
    }

    /**
     * 更新赛事信息
     * @param $id
     * @param $params
     * @return int
     */
    public function updateMatchSchedule($id, $params) {
        $dalMatchSchedule = new DALMatchSchedule($this->_appSetting);
        $result = $dalMatchSchedule->updateMatchSchedule($id, $params);
        $this->_redisModel->redisDel(MATCH_SCHEDULE_INFO.$id);
        return $result;
    }


    /**
     * 赛事列表（用户端）（新版本）
     * @param $userId
     * @param $matchType
     * @param $scheduleTime
     * @param $leagueIdList
     * @return array
     */
    public function newScheduleList($userId, $matchType, $scheduleTime,$leagueIdList, $page, $pageSize) {
        $leagueIdString = "";
        //筛选联赛列表处理
        if (!empty($leagueIdList)) {
            foreach ($leagueIdList as $leagueId) {
                $leagueIdString .= $leagueId . ",";
            }
            $leagueIdString = substr($leagueIdString, 0, -1);
        }
        $dalMatchSchedule = new DALMatchSchedule($this->_appSetting);
        $dalResourceSchedule = new DALResourceSchedule($this->_appSetting);
        $scheduleList = $dalMatchSchedule->newScheduleList($scheduleTime,  $matchType, $leagueIdString, 2, $page, $pageSize);
        $resultScheduleList = [];
        $recommendList = [];
        $hasSourceList = [];
        $noHasSource = [];
        $overRecommendList = [];
        $overHasSourceList = [];
        $overNoHasSource = [];
        //赛事列表第一次循环。
        if (!empty($scheduleList)) {
            $over = [];
            foreach ($scheduleList as $key => $val) {
                $formatScheduleTime = $this->formatScheduleTime($val['schedule_time']);
                $val = array_merge($val, $formatScheduleTime);
                $val['expert_num'] = $this->getScheduleExpertNumber($val['schedule_id']);
                $val['resource_num'] = $this->getScheduleResourceNum($val['schedule_id']);
                $userFollowModel = new UserFollowModel();
                $val['is_follow'] = $userFollowModel->checkFollowScheduleStatus($userId, $val['schedule_id']);
                if ($val['schedule_time'] < time()) {
                    //完赛赛事，按照排序规则分离
                    if($val['is_recommend']==1){
                        //推荐列表
                        $overRecommendList[] = $val;
                    }else{
                        //检查是否有料
                        $hasSource = $dalResourceSchedule->checkHasSource($val['schedule_id']);
                        if($hasSource){
                            $overHasSourceList[] = $val;
                        }else{
                            $overNoHasSource[] = $val;
                        }
                    }
                } else {
                    if($val['is_recommend']==1){
                        //推荐列表
                        $recommendList[] = $val;
                    }else{
                        //检查是否有料
                        $hasSource = $dalResourceSchedule->checkHasSource($val['schedule_id']);
                        if($hasSource){
                            $hasSourceList[] = $val;
                        }else{
                            $noHasSource[] = $val;
                        }
                    }
                }
            }

            $over = array_merge($overRecommendList,$overHasSourceList,$overNoHasSource);
            $resultScheduleList = array_merge($recommendList,$hasSourceList,$noHasSource, $over);
        }
        return $resultScheduleList;
    }

    public function getMatchListV2($startTime, $endTime, $offset, $limit, $orderBy) {
      $dalMatchSchedule = new DALMatchSchedule($this->_appSetting);
      return $dalMatchSchedule->getScheduleListV2($startTime, $endTime, $offset, $limit, $orderBy);
    }

    public function getHotMatch() {
      $soccer_model = new SoccerModel();
	    $basketballModel = new BasketballModel();
      $soccer_match =  $soccer_model->hotMatchList();
	    $basketball_match = $basketballModel->hotMatchList();
	    $res = array_merge($soccer_match, $basketball_match);
      return $res;
    }

    public function updateMatchInfo($data, $match_num, $match_type) {
      $dalMatchSchedule = new DALMatchSchedule($this->_appSetting);
      return $dalMatchSchedule->updateMatchInfo($data, $match_num, $match_type);
    }

    /**
     * 获取有方案关联的比赛信息
     * @param condition = array() 查询条件
     * @return 方案关联比赛的基本信息
     */
    public function getMatchWithResource($st, $et, $match_type = 1) {
      $dalResourceSchedule = new DALResourceSchedule($this->_appSetting);
      return $dalResourceSchedule->getMatchWithResource($st, $et, $match_type);
    }

    public function updateResoureMatch($updateInfo, $condition = []) {
      $dalResourceSchedule = new DALResourceSchedule($this->_appSetting);
      return $dalResourceSchedule->updateResoureMatch($updateInfo, $condition);
    }

    /**
     * 比赛对应的红黑判定
     * @param cate_type 竞猜类型[1:竞彩胜平负，2:竞彩让球胜平负，3:北单让球胜负，4:全部比赛足球让球胜负，5:全部比赛足球大小球]
     * @param match_num
     * @param handicap 让球数
     * @param match_type  [1.足球，2.篮球]
     * @return 1[主胜/大球],2[平],3[客胜/小球],4[主半胜],5[客半胜]
     */
    public function checkMatchResult($match_num, $handicap = 0, $lottery_type, $match_type = 1) {

      $soccerModel = new SoccerModel();
      $matchInfo = $soccerModel->nowInfo($match_num);
      //$host_score = $matchInfo['host_score'];
      $host_score = $matchInfo['host_score_90'];
      //$guest_score = $matchInfo['guest_score'];
      $guest_score = $matchInfo['guest_score_90'];
      //足球相关计算
      $res = [];
      $handicaps = explode('.', $handicap);
      $float_num = 0;
      if (count($handicaps) > 1) {
        $float_num = '0.' . $handicaps[1]; 
      }
      if ($lottery_type == 4) {
            if ($float_num == '0.75' || $float_num == '0.25') { //半红半黑下注类型
                $min_res = $host_score + $guest_score - $handicap + 0.25;
                $max_res = $host_score + $guest_score - $handicap - 0.25;
                if ($min_res > 0 && $max_res > 0) {
                  //主胜
                    $res = 1;
                } else if ($min_res < 0 && $max_res < 0) {
                  //客胜
                    $res = 3;
                } else if (($min_res > 0 && $max_res == 0)) {
                  //主半胜
                    $res = 4;
                } else if (($min_res == 0 && $max_res < 0)) {
                  //客半胜
                    $res = 5;
                }
            } else {
                $result = $host_score - $handicap + $guest_score;
                if ($result > 0) {
                  $res = 1;
                } else if ($result == 0) {
                  $res = 2;
                } else {
                  $res = 3;
                }
            }
      } else { 
            if ($float_num == '0.75' || $float_num == '0.25') { //半红半黑下注类型
                $min_res = $host_score - $guest_score + $handicap + 0.25;
                $max_res = $host_score - $guest_score + $handicap - 0.25;
                if ($min_res > 0 && $max_res > 0) {
                  //主胜
                    $res = 1;
                } else if ($min_res < 0 && $max_res < 0) {
                  //客胜
                    $res = 3;
                } else if (($min_res > 0 && $max_res == 0)) {
                  //主半胜
                    $res = 4;
                } else if (($min_res == 0 && $max_res < 0)) {
                  //客半胜
                    $res = 5;
                }
            } else {
                $result = $host_score + $handicap - $guest_score;
                if ($result > 0) {
                  $res = 1;
                } else if ($result == 0) {
                  $res = 2;
                } else {
                  $res = 3;
                }
            }
      }
      return $res;
    }


    public function checkBasketMatchResult($match_num, $handicap = 0, $lottery_type, $match_type = 1,$play_method) {

        $basketModel = new BasketballModel();
        $matchInfo = $basketModel->matchInfo($match_num);
        $host_score = $matchInfo['host_score'];
        $guest_score = $matchInfo['guest_score'];
        //篮球相关计算
        $res = [];

        switch ($play_method){
            case 1:
                //竞彩胜平负
                //客队得分+(0)-主队得分>0，则为客胜；
                //客队得分+(0)-主队得分 <0，则为主胜；
                $res_score=$guest_score-$host_score;
                //主胜
                $res = 1;
                if($res_score>0){
                    //客胜
                    $res = 3;
                }
                break;
            case 2:
                //竞彩让球胜平负
                //客队得分+（+2.5）-主队得分>0，则为客胜；

                //客队得分+（+2.5）-主队得分 <0，则为主胜；
                //让分 -2.5 同理
                $res_score=($guest_score-$handicap)-$host_score;
                $res = 1;//主胜
                if($res_score>0){
                    //客胜
                    $res = 3;
                }

                break;

            case 3:
                //竞彩比赛大小分

                //客队得分+主队得分>200.5，则为大分；

                //客队得分+主队得分 <200.5，则为小分；

                $res_score=$guest_score+$host_score;
                $res = 1;
                if($res_score<$handicap){
                    //小分
                    $res = 3;
                }
                break;
        }

        return $res;
    }




    public function getMatchInformation($match_num, $match_type) {
      $dalMatchSchedule = new DALMatchSchedule($this->_appSetting);
      $res = $dalMatchSchedule->getMatchInformation($match_num, $match_type);
      if (!$res) {
        return [];
      } else {
        $res['is_free'] = ($res['price'] == 0) ? 1 : 0;
        $res['price'] = $this->ncPriceFen2Yuan($res['price']);
        return $res;
      }
    }

    public function updateMatchInformation($updateInfo, $condition = array()) {
      $dalMatchSchedule = new DALMatchSchedule($this->_appSetting);
      if (isset($updateInfo['price']) && $updateInfo['price'] != 0) {
        $updateInfo['price'] = intval($this->ncPriceYuan2Fen($updateInfo['price']));
      }
      return $dalMatchSchedule->updateMatchInformation($updateInfo, $condition);
    }

    public function addMatchInformation($data) {
      $dalMatchSchedule = new DALMatchSchedule($this->_appSetting);
      if (isset($data['price']) && $data['price'] != 0) {
        $data['price'] = intval($this->ncPriceYuan2Fen($data['price']));
      }
      return $dalMatchSchedule->addMatchInformation($data);
    }

    public function dealLotteryChange($data) {
        if ($data['match_type'] == 1) {
            $soccer_model = new SoccerModel(); 
            $changed = $soccer_model->dealLotteryChange($data);
            return $changed;
        }
        if ($data['match_type'] == 2) {
            $soccer_model = new BasketballModel();
            $changed = $soccer_model->dealLotteryChange($data);
            return $changed;
        }
        return false;
    }

}
