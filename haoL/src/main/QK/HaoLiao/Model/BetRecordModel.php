<?php
/**
 * 战绩信息处理类
 * User: YangChao
 * Date: 2018/10/17
 */

namespace QK\HaoLiao\Model;

use QK\HaoLiao\DAL\DALResource;
use QK\HaoLiao\DAL\DALStatBetRecord;
use QK\HaoLiao\DAL\DALStatBetRecordDesc;

class BetRecordModel extends BaseModel {

    private $_redisModel;
    private $_resourceModel;
    private $_dalStatBetRecord;
    private $_dalResource;

    public function __construct(){
        parent::__construct();
        $this->_redisModel = new RedisModel("betRecord");
        $this->_resourceModel = new ResourceModel();
        $this->_dalStatBetRecord = new DALStatBetRecord($this->_appSetting);
        $this->_dalResource = new DALResource($this->_appSetting);
    }

    /**
     * 根据日期获取红黑单数据统计
     * @param $date
     * @return array|bool|mixed|null|string
     */
    public function getBetRecordStatByDate($date){
        $redisKey = BETRECORD_DATE_INFO . $date;
        $stat = $this->_redisModel->redisGet($redisKey, true);
        if(empty($stat)){
            //获取该日战绩
            $dalStatBetRecord = new DALStatBetRecord($this->_appSetting);
            $statAll = $dalStatBetRecord->getBetRecordStatByDate($date);
            $stat = [];
            $stat = $this->formatBetRecordStat($statAll);
            $this->_redisModel->redisSet($redisKey, $stat);
        }
        $stat['desc'] = $this->getBetRecordDescByDate($date);
        return $stat;
    }

    /**
     * 根据日期获取推荐语
     * @param $date
     * @return bool|mixed|null|string
     */
    public function getBetRecordDescByDate($date){
        $redisKey = BETRECORD_DATE_INFO_DESC . $date;
        $desc = $this->_redisModel->redisGet($redisKey, true);
        if(empty($desc)){
            //获取该日战绩推荐介绍
            $dalStatBetRecordDesc = new DALStatBetRecordDesc($this->_appSetting);
            $descArr = $dalStatBetRecordDesc->getBetRecordDescByDate($date);
            if(!empty($descArr)){
                $desc = $descArr['desc'];
                $this->_redisModel->redisSet($redisKey, $desc);
            }
        }
        return (string) $desc;
    }

    /**
     * 获取每天专家统计数
     * @param $date
     * @return mixed
     */
    public function getBetRecordTotalByDate($date){
        $dalStatBetRecord = new DALStatBetRecord($this->_appSetting);
        return $dalStatBetRecord->getBetRecordTotalByDate($date);
    }

    /**
     * 设置战绩日期推荐语
     * @param $date
     * @return bool|mixed|null|string
     */
    public function setBetRecordDesc($date, $desc){
        $redisKey = BETRECORD_DATE_INFO_DESC . $date;
        //获取该日战绩推荐介绍
        $dalStatBetRecordDesc = new DALStatBetRecordDesc($this->_appSetting);
        $res = $dalStatBetRecordDesc->setBetRecordDescByDate($date, $desc);
        $this->_redisModel->redisDel($redisKey);
        return $res;
    }

    /**
     * 根据日期获取专家的红黑单数据
     * @param $date
     * @param $page
     * @param $pagesize
     * @return array
     */
    public function getExpertBetRecordStatByDate($date, $page, $pagesize){
        $start = ($page - 1) * $pagesize;
        $redisKey = BETRECORD_DATE_EXPERT_LIST . $date;
        //根据分值倒序获取redis数据
        $statAll = $this->_redisModel->redisZRevRange($redisKey, $start, $start + $pagesize - 1);
        if(empty($statAll)){
            //设置红黑单正确率
            $this->setExpertBetRecordStatByDate($date);
            //根据分值倒序获取redis数据
            $statAll = $this->_redisModel->redisZRevRange($redisKey, $start, $start + $pagesize - 1);
        }
        $data = [];
        if(!empty($statAll)){
            $expertModel = new ExpertModel();
            foreach($statAll as $key => $val){
                $expertId = $val;
                $expertInfo = $expertModel->getExpertInfo($expertId);
                $data[$key] = $expertInfo;
                $data[$key]['betRecord'] = $this->getBetRecordStatByExpertId($expertId, $date);
            }
        }
        return $data;
    }

    /**
     * 根据专家ID和日期获取红黑单数据
     * @param $expertId
     * @param $date
     * @return array|bool|mixed|null|string
     */
    public function getBetRecordStatByExpertId($expertId, $date){
        $redisKey = BETRECORD_EXPERT_INFO . $expertId . ':' . $date;
        $expertStatInfo = $this->_redisModel->redisGet($redisKey, true);
        if(empty($expertStatInfo)){
            $dalStatBetRecord = new DALStatBetRecord($this->_appSetting);
            $statAll = $dalStatBetRecord->getBetRecordStatByExpertId($expertId, $date);
            $expertStatInfo = $this->formatBetRecordStat($statAll);
            $this->_redisModel->redisSet($redisKey, $expertStatInfo);
        }
        return $expertStatInfo;
    }

    /**
     * 获取专家近30日战绩
     * @param $expertId
     * @return mixed
     */
    public function getBetRecordMonthStatByExpertId($expertId){
        $key = 0;
        for($i = 1; $i <= 30; $i++){
            $date = date('Y-m-d', strtotime('-' . $i . ' day'));
            $betRecordStat = $this->getBetRecordStatByExpertId($expertId, $date);
            $statList[$key]['date'] = $date;
            $statList[$key]['red'] = isset($betRecordStat['red']) ? $betRecordStat['red'] : 0;
            $statList[$key]['go'] = isset($betRecordStat['go']) ? $betRecordStat['go'] : 0;
            $statList[$key]['black'] = isset($betRecordStat['red']) ? $betRecordStat['black'] : 0;
            $key++;
        }
        return $statList;
    }

    /**
     * 重置专家近30日战绩
     * @param $expertId
     * @return mixed
     */
    public function resetBetRecordMonthStatByExpertId($expertId) {
        $key = 0;
        $redisKey = [];
        for ($i = 1; $i <= 30; $i++) {
            $date = date('Y-m-d', strtotime('-' . $i . ' day'));
            $redisKey[] = BETRECORD_EXPERT_INFO . $expertId . ':' . $date;
            $this->resetBetRecordStatByExpertId($expertId, $date);
            $key++;
        }
        $this->_redisModel->redisDel($redisKey);
    }

    public function resetBetRecordStatByExpertId($expertId, $date) {
        $startTime = strtotime($date . ' 00:00:00');
        $endTime = $startTime + 24 * 3600;
        $condition = "create_time >= $startTime AND create_time < $endTime";
        $resourceList = $this->_dalResource->getResourceListToStatByExpertId($expertId, $condition);
        $betRecordStat = [];
        if (!empty($resourceList)) {
            foreach ($resourceList as $item) {
                $matchType = $this->_resourceModel->getResourceMatchType($item['resource_id']);
                if ($matchType !== false) {
                    if(isset($betRecordStat[$matchType][$item['bet_status']])) {
                        $betRecordStat[$matchType][$item['bet_status']]++;
                    } else {
                        $betRecordStat[$matchType][$item['bet_status']] = 1;
                    }
                }
            }

            $betRecordStatList = [];
            $betRecordStatQuery = $this->_dalStatBetRecord->getBetRecordStatByExpertId($expertId, $date);
            if (!empty($betRecordStatQuery)) {
                foreach ($betRecordStatQuery as $item) {
                    $betRecordStatList[$item['match_type']] = $item;
                }
            }
            if (!empty($betRecordStat)) {
                foreach ($betRecordStat as $k => $item) {
                    $betRecordStatData['red'] = isset($item[1]) ? $item[1] : 0;
                    $betRecordStatData['go'] = isset($item[2]) ? $item[2] : 0;
                    $betRecordStatData['black'] = isset($item[3]) ? $item[3] : 0;
                    if (!isset($betRecordStatList[$k])) {
                        $betRecordStatData['match_type'] = $k;
                        $betRecordStatData['expert_id'] = $expertId;
                        $betRecordStatData['date'] = $date;
                        $this->_dalStatBetRecord->newStat($betRecordStatData);
                    } else {
                        $this->_dalStatBetRecord->updateStat($betRecordStatList[$k]['id'], $betRecordStatData);
                    }
                }
            }
        }
    }

    /**
     * 计算3日/7日/30日胜率
     * @param $betRecord
     * @return mixed
     */
    public function statisticsWinning($betRecord, $isPercent = 1){
        //计算3日/7日/30日胜率
        $day_3_total = $day_3_red = $day_7_total = $day_7_red = $day_30_total = $day_30_red = 0;
        foreach($betRecord as $key => $val){
            if($key < 3){
                $day_3_total += $val['red'] + $val['go'] + $val['black'];
                $day_3_red += $val['red'];
            }
            if($key < 7){
                $day_7_total += $val['red'] + $val['go'] + $val['black'];
                $day_7_red += $val['red'];
            }
            $day_30_total += $val['red'] + $val['go'] + $val['black'];
            $day_30_red += $val['red'];
        }
        if($isPercent){
            $data['day_3'] = $day_3_total && $day_3_red ? intval(round($day_3_red / $day_3_total, 2) * 100) . '%' : '--';
            $data['day_7'] = $day_7_total && $day_7_red ? intval(round($day_7_red / $day_7_total, 2) * 100) . '%' : '--';
            $data['day_30'] = $day_30_total && $day_30_red ? intval(round($day_30_red / $day_30_total, 2) * 100) . '%' : '--';
        } else {
            $data['day_3'] = $day_3_total && $day_3_red ? intval(round($day_3_red / $day_3_total, 2) * 100) : 0;
            $data['day_7'] = $day_7_total && $day_7_red ? intval(round($day_7_red / $day_7_total, 2) * 100) : 0;
            $data['day_30'] = $day_30_total && $day_30_red ? intval(round($day_30_red / $day_30_total, 2) * 100) : 0;
        }
        return $data;
    }

    /**
     * 计算1,3,5,7,15,30日胜率
     * @param $betRecord
     * @return array
     */
    public function statisticsWinningV2($betRecord){
        $statisticsDate = [1,3,5,7,15,30];
        $res = [];
        foreach($statisticsDate as $key => $val){
            $res[$key]['day'] = $val;
            $res[$key]['total'] = $res[$key]['red'] = 0;
            foreach($betRecord as $k => $v){
                if($k < $val){
                    $res[$key]['total'] += $v['red'] + $v['go'] + $v['black'];
                    $res[$key]['red'] += $v['red'];
                }
            }
            $res[$key]['winning'] = $res[$key]['total'] && $res[$key]['red'] ? intval(round($res[$key]['red'] / $res[$key]['total'], 2) * 100) : 0;
        }
        return $res;
    }

    /**
     * 根据日期设置专家的红黑单正确率
     * @param $date
     */
    private function setExpertBetRecordStatByDate($date){
        $redisKey = BETRECORD_DATE_EXPERT_LIST . $date;
        $dalStatBetRecord = new DALStatBetRecord($this->_appSetting);
        $expertStatAll = $dalStatBetRecord->getExpertBetRecordStatByDate($date);
        foreach($expertStatAll as $key => $val){
            $totalResource = $val['red'] + $val['go'] + $val['black'];
            $rate = $val['red'] / $totalResource;
            $this->_redisModel->redisZAdd($redisKey, $rate, $val['expert_id']);
        }
    }

    /**
     * 格式化红黑单统计数据
     * @param $statAll
     * @return array
     */
    private function formatBetRecordStat($statAll){
        $stat = [];
        if(empty($statAll)){
            $stat['red'] = 0;
            $stat['go'] = 0;
            $stat['black'] = 0;
            $stat['football']['red'] = 0;
            $stat['football']['go'] = 0;
            $stat['football']['black'] = 0;
            $stat['basketball']['red'] = 0;
            $stat['basketball']['go'] = 0;
            $stat['basketball']['black'] = 0;
            return $stat;
        }
        //统计数据
        foreach($statAll as $key => $val){
            $stat['red'] += $val['red'];
            $stat['go'] += $val['go'];
            $stat['black'] += $val['black'];
            $stat['football']['red'] += $val['match_type'] == 1 ? $val['red'] : 0;
            $stat['football']['go'] += $val['match_type'] == 1 ? $val['go'] : 0;
            $stat['football']['black'] += $val['match_type'] == 1 ? $val['black'] : 0;
            $stat['basketball']['red'] += $val['match_type'] == 2 ? $val['red'] : 0;
            $stat['basketball']['go'] += $val['match_type'] == 2 ? $val['go'] : 0;
            $stat['basketball']['black'] += $val['match_type'] == 2 ? $val['black'] : 0;
        }
        return $stat;
    }

    /**
     * 近十场战绩
     * @param $expertId
     * @return bool|mixed|null|string
     */
    public function nearTenScore($expertId, $platform = 2) {
        $resultRecord = $this->nearTenRecord($expertId, $platform);
        $resultScore = ['red' => 0, 'go' => 0, 'black' => 0];
        foreach($resultRecord as $key => $score){
            if ($score['bet_status'] == 1) {
                $resultScore['red']++;
            }
            if ($score['bet_status'] == 2) {
                $resultScore['go']++;
            }
            if ($score['bet_status'] == 3) {
                $resultScore['black']++;
            }
        }

//        $redisKey = BETRECORD_STAT_NEAR_TEN_SCORE . $expertId;
//        $resultScore = $this->_redisModel->redisGet($redisKey, true);
//        $dalResource = new DALResource($this->_appSetting);
//        if (empty($resultScore)) {
//            $resultScore = ['red' => 0, 'go' => 0, 'black' => 0];
//            $scoreInfo = $dalResource->nearTenScore($expertId);
//            foreach ($scoreInfo as $score) {
//                if ($score['bet_status'] == 1) {
//                    $resultScore['red'] = $score['num'];
//                }
//                if ($score['bet_status'] == 2) {
//                    $resultScore['go'] = $score['num'];
//                }
//                if ($score['bet_status'] == 3) {
//                    $resultScore['black'] = $score['num'];
//                }
//            }
//            $this->_redisModel->redisSet($redisKey, $resultScore);
//        }
        return $resultScore;

    }

    /**
     * 近十战记录
     */
    public function nearTenRecord($expertId, $platform = 2) {
        $redisKey = BETRECORD_STAT_NEAR_TEN_RECORD . $expertId . ':' . $platform;
        $resultRecord = $this->_redisModel->redisGet($redisKey, true);
        if (!$resultRecord) {
            $dalResource = new DALResource($this->_appSetting);
            //$resultRecord = $dalResource->nearTenRecord($expertId, $platform);

			$platform_key = ($platform == 1) ? 'bd_display' : 'wx_display';
            $condition = ['expert_id' => $expertId, $platform_key => 1, 'resource_status' => 1, 'is_schedule_over' => 1];
            $orderBy = ['release_time' => 'desc'];
            $resourceList = $dalResource->getAllResources($condition, ['resource_id'], 0, 10, $orderBy);

			
           /* $platform_key = ($platform == 1) ? 'bd_display' : 'wx_display';
            $condition = ['expert_id' => $expertId, $platform_key => 1, 'resource_status' => 1, 'is_over_bet' => 1];
            $orderBy = ['release_time' => 'desc'];
            $resourceList = $dalResource->getAllResources($condition, ['resource_id'], 0, 10, $orderBy);

            //兼容旧数据
            if (count($resourceList) < 10) {
              $platform_key = ($platform == 1) ? 'bd_display' : 'wx_display';
              $condition = ['expert_id' => $expertId, $platform_key => 1, 'resource_status' => 1, 'hl_resource_extra.bet_status' => ['<>', 0]];
              $orderBy = ['hl_resource.release_time' => 'desc'];
              $join = ['LEFT JOIN', ['hl_resource_extra', 'resource_id', 'resource_id']];
              $old_resourceList = $dalResource->getAllResources($condition, ['hl_resource.resource_id', 'hl_resource_extra.bet_status'], 0, 10 - count($resourceList), $orderBy, $join);
              $resourceList = array_merge($resourceList, $old_resourceList);
            }*/

            foreach($resourceList as $resource) {
				
				// $resourceExtraInfo = $this->_resourceModel->getResourceExtraInfo($resource['resource_id']);
				$resourceExtraInfo = $this->_resourceModel->getResourceBriefInfo($resource['resource_id'], true);
				$log['title']=$resourceExtraInfo['title'];
				$log['resource_id']=$resourceExtraInfo['resource_id'];
              /*if (isset($resource['bet_status'])) {
                $all_bet_status = $resource['bet_status'];
              } else {
                $resourceScheduleList = $this->_resourceModel->getResourceScheduleList($resource['resource_id']);
                $all_bet_status = $this->_resourceModel->getBetStatus($resourceScheduleList);
              }*/
			  $resourceScheduleList = $this->_resourceModel->getResourceScheduleList($resource['resource_id']);
              $all_bet_status = $this->_resourceModel->getBetStatus($resourceScheduleList);
			  
			  if(empty($resourceScheduleList)){
				$all_bet_status=$resourceExtraInfo['bet_status'];
			  }
			   //如果有手动判的 已手动判的为准
			  if ($resourceExtraInfo['bet_status']) {
				$all_bet_status=$resourceExtraInfo['bet_status'];
			  }
              $resultRecord[] = ['resource_id' => $resource['resource_id'], 'bet_status' => $all_bet_status];
            }
            $this->_redisModel->redisSet($redisKey, $resultRecord);
        }
        return $resultRecord;
    }
}
