<?php
/**
 * Date: 2019/06/25
 * Time: 14:08
 */

namespace QK\HaoLiao\Controllers\Expert\Base;
use QK\HaoLiao\Controllers\Expert\ExpertController;
use QK\HaoLiao\Common\StringHandler;
use QK\HaoLiao\Controllers\BaseController;
use QK\HaoLiao\Model\ResourceModel;

class ResourceController extends ExpertController {
    private $match_lottery = [
        11 => '竞彩足球',
        12 => '竞彩篮球',
        21 => '北单足球',
        22 => '北单篮球',
    ];

   
    public function lists() {

        $params = $this->checkApiParam(['expert_id'], ['page' => 1, 'pagesize' => 10, 'start_time' => null, 'end_time' => null]);
		
        $expertId = intval($params['expert_id']);
		//默认显示近三天
	    $now = time();
        $time = strtotime('-2 day', $now);
        if(!$params['start_time'] && !$params['end_time']){
			
            $params['start_time']=date('Y-m-d 00:00:00', $time);
            $params['end_time']=date('Y-m-d 23:59:59', $now);
        }
		
		
        $startTime = strtotime($params['start_time']);
        $endTime = strtotime($params['end_time']);
        $page = intval($params['page']);
        $pagesize = intval($params['pagesize']);
		
        $resourceModel = new ResourceModel();
        $condition = [];
        $condition['expert_id'] = $expertId;
        if ( ! empty($startTime)) $condition['start_time'] = $startTime;
        if ( ! empty($endTime)) $condition['end_time'] = $endTime;

        $resourceListRes = $resourceModel->getResourceList($condition, $page, $pagesize);
		$totalCount= $resourceModel->resourceListCount($condition);
        $resourceList = [];
        foreach ($resourceListRes as $resource) {

            $resourceInfo = [
                'resource_id' => $resource['resource_id'],
                'title' => $resource['title'],
                'create_time' => date('Y-m-d H:i:s', $resource['create_time']),
				'release_time' => date('Y-m-d H:i:s', $resource['release_time']),
                'is_schedule_over' => $resource['is_schedule_over'],
                'bet_status' => $resource['bet_status'],
                'resource_status' => $resource['resource_status'],
                'odds' => $resource['odds'],
                'price' => $resource['price'],
                'sold_num' => $resource['sold_num'],
                //'sold_num' => $resource['sold_num'] + $resource['cron_sold_num'],
               //'schedule' =>$resource['schedule'] ,
            ];
			$lottery_type=[];
           foreach ($resource['schedule'] as $schedule) {
			   $lottery_type[]=$schedule['lottery_type'];
                $resourceInfo['schedule'][] = array(
                    'master_team' => $schedule['master_team'],
                    'guest_team' => $schedule['guest_team'],
                );
               $match_type = $schedule['match_type'];
		   }
			$lottery_type = array_unique($lottery_type);
			$resourceInfo['lottery_type']=$lottery_type[0];
			$resourceInfo['match_type'] = $match_type;
			$resourceInfo['match_lottery'] = $this->match_lottery[$resourceInfo['lottery_type'] . $resourceInfo['match_type']] ?: '';
			$resourceList[] = $resourceInfo;
        }
        //今天已发料数量
        $start=strtotime(date('Y-m-d 00:00:00'));
        $end=strtotime(date('Y-m-d 23:59:59'));
        $count=$resourceModel->getTodayResourceCount($expertId,$start,$end);
        $data['list']=$resourceList;
        $data['count']=$count;
		$data['totalCount']=$totalCount;
        $this->responseJson($data);
    }
    public function detail() {

        $params = $this->checkApiParam(['expert_id', 'resource_id']);
        $expertId = $params['expert_id'];
        $resourceId = $params['resource_id'];
        $resourceModel = new ResourceModel();
        //查询料
        $resourceInfo = $resourceModel->getResourceInfo($resourceId);
        //查询方案内容
        $resourceContent=$resourceModel->getResourceDetailList($resourceId);
        $resourceInfo['content']=$resourceContent[0]['content'];
        //查询关联比赛
        $ScheduleList=$resourceModel->getResourceScheduleList($resourceId);
        $lottery_type=[];
        foreach ($ScheduleList as &$schedule) {
            $lottery_type[]=$schedule['lottery_type'];
            $schedule['d']=$schedule['d']?$schedule['d']:'-';
        }
        $lottery_type = array_unique($lottery_type);
        $resourceInfo['lottery_type']=$lottery_type[0];
        $resourceInfo['schedule']=$ScheduleList;
        //今天已发料数量
        $start=strtotime(date('Y-m-d 00:00:00'));
        $end=strtotime(date('Y-m-d 23:59:59'));
        $count=$resourceModel->getTodayResourceCount($expertId,$start,$end);
        if ($expertId != $resourceInfo['expert_id']) {
            $this->responseJsonError(-1, '该资源您不能查看');
        }
        //封面图
        $surfaces = $resourceModel->getResourceStaticList($resourceId);
        $surface = $surfaces[0]['url'] ?: '';
        $resourceInfo['surface'] = $surface;
        $data['detail']=$resourceInfo;
        $data['count']=$count;
        $this->responseJson($data);
    }
	
		 //获取方案数量
    public function getTodayResourceCount(){
            $params = $this->checkApiParam(['expert_id']);
            $expertId=$params['expert_id'];
            //今天已发料数量
            $start=strtotime(date('Y-m-d 00:00:00'));
            $end=strtotime(date('Y-m-d 23:59:59'));
            $resourceModel = new ResourceModel();
            $count=$resourceModel->getTodayResourceCount($expertId,$start,$end);
            $this->responseJson($count);
    }
    public function edit() {
        $params = $this->checkApiParam(['expert_id', 'title', 'match', 'price'], ['resource_id' => '']);
        $expertId = intval($params['expert_id']);
        $title = StringHandler::newInstance()->stringExecute($params['title']);
        $matchArr = json_decode($params['match']);
        $price = StringHandler::newInstance()->stringExecute($params['price']);
        $resourceId = $params['resource_id'];

        if ( ! empty($resourceId)) {

        }

        if ( ! empty($matchArr)) {
            foreach ($matchArr as $match) {
                $resourceSchedule['resource_id'] = $resourceId;
            }
        }
    }
}

