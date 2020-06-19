<?php
/**
 * 赛事相关接口
 * User: YangChao
 * Date: 2018/10/11
 */

namespace QK\HaoLiao\Controllers\Expert\Base;


use QK\HaoLiao\Controllers\Expert\ExpertController;
use QK\HaoLiao\Model\MatchModel;

class MatchController extends ExpertController {

    /**
     * 获取联赛列表
     */
    public function getLeagueList(){
        $param = $this->checkApiParam([], ['schedule_time' => time(), 'match_type' => 1]);
        $matchType = intval($param['match_type']);
        $scheduleTime = $param['schedule_time'];
        $matchModel = new MatchModel();
        $leagueList = $matchModel->leagueList($matchType, $scheduleTime, 1);

        $this->responseJson($leagueList);
    }

    /**
     * 获取赛事列表
     */
    public function getScheduleList(){
        $param = $this->checkApiParam([], ['match_type' => 1, 'schedule_time' => time(), 'league_id' => 0, 'page' => 1, 'pagesize' => 10]);
        $matchType = intval($param['match_type']);
        $leagueIdList = json_decode($param['league_id'],1);
        $scheduleTime = intval($param['schedule_time']);
        $page = intval($param['page']);
        $pagesize = intval($param['pagesize']);
        $matchModel = new MatchModel();
        $leagueList = $matchModel->expertScheduleList($matchType,$scheduleTime, $page, $pagesize,$leagueIdList);

        $this->responseJson($leagueList);
    }


}