<?php
/**
 * 赛事推荐
 * User: WangHui
 * Date: 2018/10/17
 * Time: 下午5:12
 */

namespace QK\HaoLiao\Controllers\User\Base;


use QK\HaoLiao\Controllers\User\UserController;
use QK\HaoLiao\Model\MatchModel;
use QK\HaoLiao\Model\OrderModel;
use QK\HaoLiao\Model\SoccerModel;
use QK\HaoLiao\Model\BasketballModel;
use QK\HaoLiao\Model\UserFollowModel;

class MatchController extends UserController {

    /**
     * 联赛列表
     */
    public function leagueList(){
        $this->checkToken();
        $param = $this->checkApiParam([], [
            'time' => time(),
            'match_type' => 1
        ]);
        $time = $param['time'];
        $matchType = $param['match_type'];
        $matchModel = new MatchModel();
        $leagueList = $matchModel->leagueList($matchType, $time, 2);
        $this->responseJson($leagueList);
    }

    /**
     * 热门赛事
     */
    public function hotMatch(){
        $this->checkToken();
        $param = $this->checkApiParam(['user_id'], [
            'page' => 1,
            'pagesize' => 1
        ]);
        $userId = $param['user_id'];
        $page = intval($param['page']);
        $pageSize = intval($param['pagesize']);
        $matchModel = new MatchModel();
        $leagueList = $matchModel->hotScheduleList($userId, $page, $pageSize);
        $this->responseJson($leagueList);
    }

    /**
     * 赛事列表
     */
    public function matchList(){
        $this->checkToken();
        $param = $this->checkApiParam(['user_id'], [
            'match_type' => 1,
            'schedule_time' => time(),
            'league_list' => "",
            'page' => 1,
            'pagesize' => 1
        ]);
        $userId = $param['user_id'];
        $matchType = intval($param ['match_type']);
        $scheduleTime = intval($param['schedule_time']);
        $page = intval($param['page']);
        $pageSize = intval($param['pagesize']);
        $leagueIdList = json_decode($param['league_list'], 1);
        $matchModel = new MatchModel();
        $leagueList = $matchModel->scheduleList($userId, $matchType, $scheduleTime, $page, $pageSize, $leagueIdList);
        $this->responseJson($leagueList);
    }

    /**
     * 赛事信息
     */
    public function matchInfo(){
        $this->checkToken();
        $param = $this->checkApiParam([
            'schedule_id',
            'user_id'
        ]);
        $scheduleId = $param['schedule_id'];
        $userId = $param['user_id'];
        $matchModel = new MatchModel();
        $scheduleInfo = $matchModel->getScheduleInfo($scheduleId);
        $scheduleInfo['expert_num'] = $matchModel->getScheduleExpertNumber($scheduleId);
        $scheduleInfo['resource_num'] = $matchModel->getScheduleResourceNum($scheduleId);
        $userFollowModel = new UserFollowModel();
        $scheduleInfo['is_follow'] = $userFollowModel->checkFollowScheduleStatus($userId, $scheduleId);
        $this->responseJson($scheduleInfo);
    }

    /**
     * 赛事推荐料
     */
    public function matchSource(){
        $this->checkToken();
        $params = $this->checkApiParam(['schedule_id'], [
            'page' => 1,
            'pagesize' => 2
        ]);
        $scheduleId = intval($params['schedule_id']);
        $page = intval($params['page']);
        $pageSize = intval($params['pagesize']);
        $matchModel = new MatchModel();
        $list = $matchModel->getScheduleResourceList($scheduleId, $page, $pageSize);
        $this->responseJson($list);
    }

    /**
     * 关注/取关 某场比赛
     */
    public function follow(){
        $this->checkToken();
        $params = $this->checkApiParam([
            'user_id',
            'schedule_id'
        ]);
        $userId = $params['user_id'];
        $scheduleId = $params['schedule_id'];
        $userFollowModel = new UserFollowModel();
        $userFollowModel->followSchedule($userId, $scheduleId);
        $this->responseJson();
    }


    /**
     * 新版本赛事列表
     */
    public function newMatchList(){
        $this->checkToken();
        $param = $this->checkApiParam(['user_id'], [
            'match_type' => 1,
            'schedule_time' => time(),
            'league_list' => "",
            'page' => 1,
            'pagesize' => 10
        ]);
        $userId = $param['user_id'];
        $matchType = intval($param ['match_type']);
        $scheduleTime = intval($param['schedule_time']);
        $leagueIdList = json_decode($param['league_list'], 1);
        $page = intval($param['page']);
        $pageSize = intval($param['pagesize']);

        $scheduleTime = strtotime(date("Y-m-d", $scheduleTime));

        $matchModel = new MatchModel();
        $leagueList = $matchModel->newScheduleList($userId, $matchType, $scheduleTime, $leagueIdList, $page, $pageSize);
        $this->responseJson($leagueList);
    }

    public function getMatchList() {
      $param = $this->checkApiParam([], ['tab_type' => 0, 'page' => 1, 'pagesize' => 10]);
      $tabType = intval($param['tab_type']);
      $page = intval($param['page']);
      $pageSize = intval($param['pagesize']);
      $matchModel = new MatchModel();

      $startTime = time() - 2 * 3600;
      $endTime = strtotime(date("Y-m-d 23:59:59", $startTime));
      if ($tabType == -1) {
        $startTime = strtotime(date("Y-m-d 00:00:00", strtotime("-1 day")));
        $endTime = time() - 2 * 3600;
      } else if ($tabType == 1) {
        $startTime = strtotime(date("Y-m-d 00:00:00", strtotime("+1 day")));
        $endTime = strtotime(date("Y-m-d 23:59:59", strtotime("+1 day")));
      }
      $orderBy = array('schedule_time' => 'asc');
      if($tabType == -1) {
        $orderBy = array('schedule_time' => 'desc');
      }
      $matchList = $matchModel->getMatchListV2($startTime, $endTime, ($page - 1) * $pageSize, $pageSize, $orderBy);
      foreach($matchList as $index => $matchInfo) {
	      $matchInfo['schedule_time'] = $matchInfo['schedule_time'] + 8 * 3600;
	      $matchList[$index]['schedule_time'] = $matchInfo['schedule_time'];
	      if($tabType == 0) {
	        if($matchInfo['schedule_time'] < time()) {
	          $matchList[$index]['current_status'] = 0;
	        } else {
	          $matchList[$index]['current_status'] = 1;
	        }
	      } else if($tabType == 1) {
	        $matchList[$index]['current_status'] = 1;
	      } else {
	        $matchList[$index]['current_status'] = -1;
	      }
	      $matchList[$index]['date'] = date('Y-m-d', $matchInfo['schedule_time']);
      }
      $this->responseJson($matchList);
    }

    public function getMatchInformation() {
      $param = $this->checkApiParam(['match_num', 'match_type'], ['user_id' => 0]);
      $match_num = intval($param['match_num']);
      $match_type = intval($param['match_type']);
      $userId = intval($param['user_id']);

      $matchModel = new MatchModel();
      $matchInformation = $matchModel->getMatchInformation($match_num, $match_type);
      if (empty($matchInformation) || $matchInformation['status'] == 0) {
        $this->responseJson();
      }
      $order_type = ($match_type == 1) ? 5 : 6;
      $orderModel = new OrderModel();
      $is_buy = $orderModel->checkIsBuy($userId, $match_num, $order_type);
      $matchInformation['disable'] = 0;
      if ($matchInformation['price'] > 0 && !$is_buy) {
        $is_over_schedule = 0;
        if ($match_type == 1) {
          //soccer
          $soccerModel = new SoccerModel();
          $soccerInfo = $soccerModel->nowInfo($match_num);
          if (in_array($soccerInfo['status'], [4, 6, 10, 12])) {
            $is_over_schedule = 1;
          }
        } else if ($match_type == 2) {
          //basketball
          $basketballModel = new BasketballModel();
          $basketballInfo = $basketballModel->matchInfo($match_num);
          if (in_array($basketballInfo['status'], [9, 11, 13])) {
            $is_over_schedule = 1;
          }
        }
        if (!$is_over_schedule) {
          unset($matchInformation['content']);
          $matchInformation['disable'] = 1;
        }
      }

      $this->responseJson($matchInformation);
    }

}
