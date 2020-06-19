<?php
/**
 * 赛事管理
 * User: YangChao
 * Date: 2018/11/09
 */

namespace QK\HaoLiao\Controllers\Console\Base;


use QK\HaoLiao\Controllers\Console\ConsoleController;
use QK\HaoLiao\Model\MatchModel;
use QK\HaoLiao\Model\SoccerModel;
use QK\WSF\Settings\AppSetting;
use QK\HaoLiao\Common\CommonHandler;

class MatchController extends ConsoleController {

    public function __construct(AppSetting $appSetting) {
        $this->setTokenCheck(false);
        $this->match_model = new MatchModel();
        parent::__construct($appSetting);
    }

    /**
     * 获取赛事列表
     */
    public function scheduleList(){
        $param = $this->checkApiParam([], ['query' => '', 'page' => 1, 'pagesize' => 20]);
//        $where = ['match_type' => 1, 'league_id' => 401, 'schedule_status' => 2, 'schedule_time_start' => '1559995200', 'schedule_time_end' => '1560427200'];
        $where = json_decode($param['query'], true);
        $page = intval($param['page']);
        $pagesize = intval($param['pagesize']);

        $matchType = isset($where['match_type']) && $where['match_type'] ? intval($where['match_type']) : 1; 
        $leagueId = isset($where['league_id']) && $where['league_id'] ? intval($where['league_id']) : null;
        $scheduleStatus = isset($where['schedule_status']) ? intval($where['schedule_status']) : null;
        $result = isset($where['result']) ? intval($where['result']) : null;
        $hasInformation = (isset($where['has_information']) && $where['has_information'] != 2) ? intval($where['has_information']) : -1;
        $scheduleStartTime = isset($where['schedule_time_start']) && date('Y-m-d', $where['schedule_time_start']) ? date('Y-m-d H:i:s', $where['schedule_time_start']) : null;
        $scheduleEndTime = isset($where['schedule_time_end']) &&  date('Y-m-d',$where['schedule_time_end']) ? date('Y-m-d H:i:s', $where['schedule_time_end']) : null;

        $matchModel = new MatchModel();
        //$scheduleTotal = $matchModel->getScheduleTotal($matchType, $leagueId, $scheduleStatus, $result, $scheduleStartTime, $scheduleEndTime);

        $scheduleList = $matchModel->getScheduleList($matchType, $leagueId, $scheduleStatus, $result, $scheduleStartTime, $scheduleEndTime, $page, $pagesize, $hasInformation);

        $res['total'] = $scheduleList['total'];
        $datalist = [];
        foreach($scheduleList['data'] as $key => $value) {
          $matchModel = new MatchModel();
          $matchInformation = $matchModel->getMatchInformation($value['match_num'], $matchType);
          $value['content'] = $value['has_information'] ? $matchInformation['content'] : '--';
          $value['price'] = $value['has_information'] ? $matchInformation['price'] : '--';
          $datalist[$key] = $value;
        }
        $res['list'] = $datalist;
        $this->responseJson($res);
    }

    /**
     * 设置/取消  赛事推荐
     */
    public function setScheduleRecommend(){
        $param = $this->checkApiParam(['schedule_id', 'type']);
        $scheduleId = intval($param['schedule_id']);
        $type = $param['type'];
        if ($type == 1) {
            $model = new SoccerModel();
            $model->hotMatch($scheduleId);
            $this->responseJson();
            return;
        }
        $matchModel = new MatchModel();

        $scheduleInfo = $matchModel->getScheduleInfo($scheduleId);
        if($scheduleInfo['is_recommend'] == 1){
            $isRecommend = 0;
        } else {
            $isRecommend = 1;
        }
        $res = $matchModel->updateMatchSchedule($scheduleId, ['is_recommend'=>$isRecommend]);
        $this->responseJson();
    }

    /**
     * 设置赛事状态
     */
    public function setScheduleStatus(){
        $param = $this->checkApiParam(['schedule_id', 'schedule_status']);
        $scheduleId = intval($param['schedule_id']);
        $scheduleStatus = intval($param['schedule_status']);

        $matchModel = new MatchModel();
        $res = $matchModel->updateMatchSchedule($scheduleId, ['schedule_status'=>$scheduleStatus]);
        $this->responseJson();
    }

    /**
     * 获取赛事详情
     */
    public function getScheduleInfo(){
        $param = $this->checkApiParam(['schedule_id']);
        $scheduleId = intval($param['schedule_id']);
        $matchModel = new MatchModel();
        $scheduleInfo = $matchModel->getScheduleInfo($scheduleId);
        $this->responseJson($scheduleInfo);
    }

    /**
     * 添加/修改  赛事信息
     */
    public function setSchedule(){
        $param = $this->checkApiParam(['match_type', 'league_id', 'master_team', 'guest_team', 'schedule_time'], ['schedule_id'=>0, 'master_score'=>0, 'guest_score'=>0, 'result'=>0, 'is_recommend'=>0, 'schedule_status'=>1]);
//        $param = ['match_type'=>1, 'league_id'=>62, 'master_team'=>'te22st', 'guest_team'=>'tes111t1', 'schedule_time'=>'1534269600', 'schedule_id'=>96754, 'master_score'=>0, 'guest_score'=>0, 'result'=>0, 'is_recommend'=>0, 'schedule_status'=>1];
        $scheduleId = $param['schedule_id'];
        $data = [];
        $data['match_type'] = $param['match_type'];
        $data['league_id'] = $leagueId = $param['league_id'];
        $data['master_team'] = $param['master_team'];
        $data['master_score'] = intval($param['master_score']);
        $data['guest_team'] = $param['guest_team'];
        $data['guest_score'] = intval($param['guest_score']);
        $data['schedule_time'] = $param['schedule_time'];
        $data['result'] = $param['result'];
        $data['is_recommend'] = $param['is_recommend'];
        $data['schedule_status'] = 2;
        $matchModel = new MatchModel();
        if($scheduleId){
            // 修改联赛信息
            $matchModel->updateMatchSchedule($scheduleId, $data);
        } else {
            // 新建联赛信息
            $matchModel->newMatchSchedule($data);
        }
        $this->responseJson();
    }


    /**
     * 联赛列表接口
     */
    public function leagueList() {
        $param = $this->checkApiParam([], ['query' => '', 'page' => 1, 'pagesize' => 20]);
        $where = json_decode($param['query'], true);
        $page = intval($param['page']);
        $pageSize = intval($param['pagesize']);
        $matchModel = new MatchModel();
        $scheduleTotal = $matchModel->getLeagueCount($where);
        $scheduleList = $matchModel->getLeagueList($where, $page, $pageSize);
        $res['total'] = $scheduleTotal;
        $res['list'] = $scheduleList;
        $this->responseJson($res);
    }

    /**
     * 更新/添加联赛信息API
     */
    public function leagueEdit(){
        $param = $this->checkApiParam(['league_id', 'match_type', 'initial', 'crawler_name', 'league_name']);
        $leagueId = $param['league_id'];
        $data['match_type'] = $param['match_type'];
        $data['initial'] = $param['initial'];
        $data['crawler_name'] = $param['crawler_name'];
        $data['league_name'] = $param['league_name'];
        $matchModel = new MatchModel();
        $matchModel->editMatchLeague($leagueId, $data);
        $this->responseJson();
    }

    public function getMatchInfomation() {
      $param = $this->checkApiParam(['match_num', 'match_type']);
      $match_num = intval($param['match_num']);
      $match_type = intval($param['match_type']);

      $matchModel = new MatchModel();
      $res = $matchModel->getMatchInformation($match_num, $match_type);
      $this->responseJson($res);
    }

    public function saveMatchInformation() {
      $param = $this->checkApiParam(['match_num', 'match_type'], ['price' => -1, 'status' => -1, 'content' => null]);
      $match_num = intval($param['match_num']);
      $match_type = intval($param['match_type']);
      $status = $param['status'];
      $price = $param['price'];
      $content = $param['content'];

      $currenttime = time();

      $matchModel = new MatchModel();
      $matchInformation = $matchModel->getMatchInformation($match_num, $match_type);
      $data = array();
      if (!empty($matchInformation)) {
        if ($status != -1) {  $data['status'] = intval($status);  }
        if ($price != -1) {   $data['price'] = $price;          }
        if ($content !== null) {  $data['content'] = $content;  }
        $data['utime'] = $currenttime;

        $condition = ['match_num' => $match_num, 'match_type' => $match_type];
        $matchModel->updateMatchInformation($data, $condition);
      } else {
        $status = 1;
        $data = array(
          'match_num' => $match_num,
          'match_type' => $match_type,
          'price' => ($price != -1) ? $price : 0,
          'status' => $status,
          'content' => $content,
          'ctime' => $currenttime,
          'utime' => $currenttime
        );
        $matchModel->addMatchInformation($data);
      }
      $matchModel->updateMatchInfo(['has_information' => $status], $match_num, $match_type);
      $this->responseJson();
    }

    public function test() {
        $this->match_model->importLeague();        
    }

}
