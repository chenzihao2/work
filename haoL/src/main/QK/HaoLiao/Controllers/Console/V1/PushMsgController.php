<?php
namespace QK\HaoLiao\Controllers\Console\V1;

use QK\HaoLiao\Controllers\Console\Base\PushMsgController as PushMsg;
use QK\WSF\Settings\AppSetting;
use QK\HaoLiao\Model\ExpertModel;
use QK\HaoLiao\Model\ResourceModel;
use QK\HaoLiao\Model\NewsModel;
use QK\HaoLiao\Model\UserModel;
use QK\HaoLiao\Model\SoccerModel;
use QK\HaoLiao\Model\BasketballModel;
use QK\HaoLiao\Model\PushMsgModel;
use QK\HaoLiao\Model\MatchModel;

class PushMsgController extends PushMsg {

    public function __construct(AppSetting $appSetting) {
        $this->setTokenCheck(false);
        parent::__construct($appSetting);
    }

    private $_status_map = [
      0 => '待发送',
      1 => '已发送',
      2 => '删除',
      3 => '发送失败',
    ];

    private $_send_limit_map = [
      1 => '全部用户',
      2 => 'iOS用户',
      3 => 'Android用户',
      4 => '7天未登录用户',
      5 => '一月未登录用户',
      6 => '付费用户',
      7 => '自定义用户'
    ];

    public function msgList() {
      $params = $this->checkApiParam([], ['type' => 0, 'page' => 1, 'pagesize' => 50, 'rcev_id' => 0, 'msg_type' => 0, 'relate_id' => 0, 'title' => '', 'status' => -1, 'start_time' => '', 'end_time' => '']);
      $page = intval($params['page']);
      $pagesize = intval($params['pagesize']);

      $type = intval($params['type']);
      $msg_type = intval($params['msg_type']);
      $status = intval($params['status']);
      $rcev_id = intval($params['rcev_id']);
      $relate_id = intval($params['relate_id']);
      $title = $params['title'];
      $st = $params['start_time'];
      $et = $params['end_time'];

      $pushMsgModel = new PushMsgModel();
      $condition = [];
      $fields = [];
      if ($type == 0) {   //通用消息
        $condition['msg_type'] = 1;
        if (!empty($title)) {
          $condition['title'] = ['like', '%' .$title. '%'];
        }
      } else {      //触发类消息
        $condition['hl_push_msg.msg_type'] = $msg_type ? : ['in', '(2, 3, 4)'];
        if ($relate_id) {
          $condition['hl_push_msg.relate_id'] = $relate_id;
        }
        //if ($rcev_id) {
          //$condition['hl_user_msg.user_id'] = $rcev_id;
        //}

        //$fields = ['hl_push_msg.*', 'hl_user_msg.user_id', 'hl_user_msg.status as user_status'];
      }
      if ($status != -1) {
        $condition['hl_push_msg.status'] = $status;
      }

      if (!empty($st)) {
        $condition['-'][] = "hl_push_msg.send_time >= '$st'";
      }
      if (!empty($et)) {
        $condition['-'][] = "hl_push_msg.send_time <= '$et'";
      }
      
      $orderBy = ['ctime' => 'desc'];
      $total = $pushMsgModel->getMsgCount($condition, 0);
      $list = $pushMsgModel->getMsgList($condition, $fields, $page, $pagesize, $orderBy, 0);

      foreach($list as $key => $value) {
        $list[$key]['status_text'] = $this->_status_map[$value['status']];
        $list[$key]['ios_status_text'] = $this->_status_map[$value['ios_status']];
       /* if($value['upush_id'] || $value['upush_ios_id']){
              if(!$value['upush_id']){
                  $list[$key]['status_text']='';
              }
              if(!$value['upush_ios_id']){
                  $list[$key]['ios_status_text']='';
              }
          }*/
          if($value['platform']=='ios'){
              $list[$key]['status_text']='';
          }
          if($value['platform']=='android'){
              $list[$key]['ios_status_text']='';
          }
        if ($value['send_limit'] == 7) {
          //获取当前消息的所有发送用户
          $recvUsers = $pushMsgModel->getMsgRelation(['msg_id' => $value['id']], [], 1, 0, []);
          $list[$key]['send_limit_text'] = implode(',', array_column($recvUsers, 'user_id'));
        } else {
          $list[$key]['send_limit_text'] = $this->_send_limit_map[$value['send_limit']];
        }
        $rcev_rate = 0;
        if ($value['send_count']) {
          $rcev_rate = round($value['receive_count']/$value['send_count'] * 100, 1);
        }
        $click_rate = 0;
        if ($value['receive_count']) {
          $click_rate = round($value['open_count']/$value['receive_count'] * 100, 1);
        }
        $list[$key]['rcev_rate'] = ($value['send_count'] > 0) ? $rcev_rate . '%' : '0.0%';
        $list[$key]['click_rate'] = ($value['receive_count'] > 0) ? $click_rate . '%' : '0.0%';
        //$list[$key]['open_info'] = $openInfo;
        if ($type == 1) {
          $userModel = new UserModel();
          if ($value['msg_type'] == 2) {
            $list[$key]['msg_type_text'] = '专家上新';
            $resourceModel = new ResourceModel();
            $resourceInfo = $resourceModel->getResourceInfo($value['relate_id']);
            $list[$key]['relation_title'] = $resourceInfo['title'];
          }else if($value['msg_type'] == 3) {
            $list[$key]['msg_type_text'] = '比赛开始【足球】';
            $soccerModel = new SoccerModel();
            $matchInfo = $soccerModel->nowInfo($value['relate_id']);
            $list[$key]['relation_title'] = "【" . $matchInfo['league_short_name']. "】" . $matchInfo['host_team_name'] . "VS" . $matchInfo['guest_team_name'];
          } else if ($value['msg_type'] == 4) {
            $list[$key]['msg_type_text'] = '比赛开始【篮球】';
            $basketballModel = new BasketballModel();
            $matchInfo = $basketballModel->matchInfo($value['relate_id']);
            $list[$key]['relation_title'] = "【" . $matchInfo['league_short_name']. "】" . $matchInfo['host_team_name'] . "VS" . $matchInfo['guest_team_name'];
          }
        }
      }
      $result = ['count' => $total, 'list' => $list];
      $this->responseJson($result);
    }

    public function distList() {
      $params = $this->checkApiParam(['type'], ['start_time' => time(), 'end_time' => '']);
      $type = intval($params['type']);

      $result = array();
      //type: 1:专家，2:方案，3:资讯，4:足球，5:篮球
      switch($type) {
        case 1:
          $expertModel = new ExpertModel();
          $condition = array('expert_status' => 1);
          $orderBy = array('create_time' => 'desc');
          $expert_result = $expertModel->newExpertListV2($condition, ['expert_id', 'expert_name', 'platform'], 0, 0, $orderBy);
          foreach($expert_result as $value) {
            $result[] = array(
              'id' => $value['expert_id'],
              'title' => $value['expert_name'],
              'platform' => $value['platform']
            );
          }
          break;
        case 2:
          $resourceModel = new ResourceModel();
          $condition = array('resource_status' => 1, 'create_time' => ['>=', strtotime('-2 day')]);
          $orderBy = array('release_time' => 'desc');
          $resource_result = $resourceModel->getResourceListV2($condition, ['resource_id', 'title', 'wx_display', 'bd_display'], 0, 0, $orderBy);
          foreach($resource_result as $value) {
            $platform = 0;
            if($value['bd_display'] && !$value['wx_display'])     $platform = 1;
            if($value['wx_display'] && !$value['bd_display'])     $platform = 2;
            $result[] = array(
              'id' => $value['resource_id'],
              'title' => $value['title'],
              'platform' => $platform
            );
          }
          break;
        case 3:
          $newsModel = new NewsModel();
          $condition = array('status' => 1,'source'=>['!=', 3], 'create_time' => ['>=', strtotime('-2 day')]);
          $orderBy = ['create_time' => 'DESC'];
          $news_result = $newsModel->getNewsListV2($condition, array(), 0, 0, $orderBy);
          foreach($news_result as $value) {
            $result[] = array(
              'id' => $value['nid'],
              'title' => $value['title'],
              'platform' => 0
            );
          }
          break;
        case 4:
        case 5:
          $matchModel = new MatchModel();
          $matchType = ($type == 4) ? 1 : 2;
          $result = $matchModel->leagueList($matchType, $params['start_time'], 1, false, $params['end_time']);
          break;
      }
      $this->responseJson($result);
    }

    public function createMsg() {
        $upush = new PushMsgModel();
        $params = $this->checkApiParam(['title', 'text'], ['after_open' =>'', 'send_limit' => 1, 'user_ids' => '', 'send_time' => '', 'icon' => '', 'expire_time' => '','platform'=>'']);
        $result = $upush->createMsg($params);
        $send_time = $params['send_time'];
        $aweeklater = date('Y-m-d H:i:s', time() + 86400 * 7);
        if ($send_time > $aweeklater) {
            $this->responseJsonError(5006, '发送时间不能大于当前时间一周');
            return true;
        }
        $expire_time = $params['expire_time'];
        if (!$send_time) {
            $exp_weeklater = $aweeklater;
        } else {
            $exp_weeklater = date('Y-m-d H:i:s', strtotime($send_time) + 86400 * 7);
        }
        if ($expire_time > $exp_weeklater) {
            $this->responseJsonError(5007, '过期时间不能大于发送时间一周');
            return true;
        }
        if ($result) {
            $this->responseJson();
            return true;
        } else {
            $this->responseJsonError(5008, '创建失败');
            return true;
        }
    }

    public function msgCenter() {
        $params = $this->checkApiParam(['user_id'], ['page' => 1, 'pagesize' => 20]);
        $upush = new PushMsgModel();
        $result = $upush->msgCenter($params);
        $this->responseJson($result);
    }

    public function cancelMsg() {
        $params = $this->checkApiParam(['id']);
        $upush = new PushMsgModel();
        $msg_id = $params['id'];
        $result = $upush->cancelMsg($msg_id);//取消安卓任务
        $result_ios=$upush->cancelIosMsg($msg_id);//取消 ios 任务
        if (isset($result['ret'])) {
            $this->responseJsonError(2001, $result);
            return;
        }
        $this->responseJson($result);
    }
}
