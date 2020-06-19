<?php
/**
 * 料信息处理类
 * User: YangChao
 * Date: 2018/10/12
 */

namespace QK\HaoLiao\Model;

use QK\HaoLiao\Common\CommonHandler;
use QK\HaoLiao\DAL\DALLogOperationResource;
use QK\HaoLiao\DAL\DALResource;
use QK\HaoLiao\DAL\DALResourceDetail;
use QK\HaoLiao\DAL\DALResourceExtra;
use QK\HaoLiao\DAL\DALResourceRefund;
use QK\HaoLiao\DAL\DALResourceSchedule;
use QK\HaoLiao\DAL\DALResourceStatic;
use QK\HaoLiao\DAL\DALResourceGroup;
use QK\HaoLiao\DAL\DALUserFollowExpert;

class ResourceModel extends BaseModel {

    private $_redisModel;
    private $play_method = [1 => '主队', 2 => '主队', 3 => '大小分'];

    public function __construct(){
        parent::__construct();
        $this->_redisModel = new RedisModel("resource");
    }

    /**
     * 新建一个料
     * @param $resource
     * @return int
     */
    public function createResource($resource){
        $dalResource = new DALResource($this->_appSetting);
        $resource['price'] = intval($this->ncPriceYuan2Fen($resource['price']));
        $resourceId = $dalResource->createResource($resource);
        if($resourceId){
            //添加料日志处理记录
            $dalLogOperationResource = new DALLogOperationResource($this->_appSetting);
            $dalLogOperationResource->createOperationLog($resourceId, $resource['push_expert_id'], 0);

            $expertId = $resource['expert_id'];
            $redisKey[] = RESOURCE_EXPERT_LIST . 1 . ':' . $expertId;
            $redisKey[] = RESOURCE_EXPERT_LIST . 2 . ':' . $expertId;
            $this->_redisModel->redisDel($redisKey);
        }
        return $resourceId;
    }

    /**
     * 条件更新
     */
    public function updateSort($condition, $sort) {
        $dalResource = new DALResource($this->_appSetting);
        $res = $dalResource->updateSort($condition, $sort);
        return $res;
    }
    /**
     * 修改料信息状态
     * @param $resourceId
     * @param $operationCode
     * @param int $userId
     * @param int $expertId
     * @param bool $isAdmin
     * @return bool|int
     */
    public function operationResourceStatus($resourceId, $operationCode, $userId = 0, $expertId = 0, $isAdmin = false) {
        $data = [];
        switch($operationCode){
            case 1:
                //发布、上架
                $data['release_time'] = time();
                $data['resource_status'] = 1;
                break;
            case 2:
                //用户下架
                $data['resource_status'] = 2;
                break;
            case 3:
                //系统下架
                $data['resource_status'] = 3;
                break;
            case 4:
                //删除
                $data['resource_status'] = 4;
                break;
            default:
                //不支持的操作
                return 101;
                break;
        }

        $resourceInfo = $this->getResourceInfo($resourceId);
        if(empty($resourceInfo)){
            //料信息不存在
            return 2001;
        }

        //非后台修改检查用户专家id
        if (!$isAdmin) {
            if ($expertId != $resourceInfo['expert_id']) {
                //您尚不能修改此条料信息
                return 2002;
            }
        }
        //后台修改将专家id设置为料id
        if($isAdmin){
            $expertId = $resourceInfo['expert_id'];
        }

        $dalResource = new DALResource($this->_appSetting);
        $res = $dalResource->updateResource($resourceId, $data);
        if($res){
            //添加料日志处理记录
            $dalLogOperationResource = new DALLogOperationResource($this->_appSetting);
            $dalLogOperationResource->createOperationLog($resourceId, $userId, 1);

            //删除相关redis

            //首页专家列表key
            $redisKey[] = RESOURCE_RECOMMEND_LIST . 1;
            $redisKey[] = RESOURCE_RECOMMEND_LIST . 2;
            $redisKey[] = EXPERT_LIST . 1;
            $redisKey[] = EXPERT_LIST . 2;
            $redisKey[] = EXPERT_LIST . 3;
            //料详情key
            $redisKey[] = RESOURCE_INFO . $resourceInfo['resource_id'];
            //用户访问专家料列表key
            $redisKey[] = RESOURCE_LIST . 1 . ':' . $resourceInfo['expert_id'];
            $redisKey[] = RESOURCE_LIST . 2 . ':' . $resourceInfo['expert_id'];
            //专家访问内容管理key
            $redisKey[] = RESOURCE_EXPERT_LIST . 1 . ':' . $resourceInfo['expert_id'];
            $redisKey[] = RESOURCE_EXPERT_LIST . 2 . ':' . $resourceInfo['expert_id'];
            //专家推荐料数量
            $redisKey[] = RESOURCE_EXPERT_TOTAL . $resourceInfo['expert_id'] . ':';
            $redisKey[] = RESOURCE_EXPERT_TOTAL . $resourceInfo['expert_id'] . ':0';

            $this->_redisModel->redisDel($redisKey);

            // 资源列表二
            $redisManageModel = new RedisManageModel('resource');
            $redisManageModel->delList(RESOURCE_EXPERT_LIST_V2 . $expertId);

            $expertExtraModel = new ExpertExtraModel();

            if($operationCode == 1){
                $expertModel = new ExpertModel();
                //发布操作
                $expertModel->updateExpert($expertId, ['push_resource_time' => time()]);

                $expertExtraModel->setExpertExtraIncOrDec($expertId, ['publish_resource_num' => '+1']);

                //进入资源通知粉丝列表
                $this->_redisModel->redisLpush(RESOURCE_NOTICE_LIST, $resourceInfo['resource_id']);
            }

            $expertExtraModel->getProfitResourceNum($expertId);
            return true;
        } else {
            return 2003;
        }
    }

    /**
     * 获取首页推荐数据
     * @param     $page
     * @param     $pagesize
     * @return array
     */
    public function getRecommendResourceList($page = 1, $pagesize = 20){
        $start = ($page - 1) * $pagesize;
        $redisKey = RESOURCE_RECOMMEND_LIST . $GLOBALS['display'];

        //根据分值范围获取redis数据
        //$resourceIds = !empty($redisKey) ? $this->_redisModel->redisZRangeByScore($redisKey, $start, $start + $pagesize - 1) : [];
        $resourceIds = array();
        if(empty($resourceIds) || count($resourceIds) < $pagesize){
            //获取mysql数据
            $dalResource = new DALResource($this->_appSetting);
            $resourceIdList = $dalResource->getRecommendResourceList($start, $pagesize);

            $resourceIds = [];
            if(!empty($resourceIdList)){
                foreach($resourceIdList as $key => $val){
                    //相关数据入redis
                    $resourceIds[] = $resourceId = $val['resource_id'];
                    //!empty($redisKey) && $this->_redisModel->redisZAdd($redisKey, $key + $start, $resourceId);
                }
            }
        }

        $resourceList = [];
        if(!empty($resourceIds)){
            foreach($resourceIds as $key => $resourceId){
                $resourceInfo = $this->getResourceBriefInfo($resourceId);
                $resourceScheduleList = $this->getResourceScheduleList($resourceId);
                $resourceList[$key] = $resourceInfo;
                $resourceList[$key]['schedule'] = $resourceScheduleList;
                if ($resourceInfo['is_groupbuy'] == 1) {
                    $resourceList[$key]['group'] = $this->getResourceGroupInfo($resourceId);
                }
            }
        }
        return $resourceList;
    }

    public function getRecommendListV2($start = 0, $pagesize = 20, $platform = 1, $is_new = 0, $match_num = 0, $type = 1, $is_free = 0, $match_type = 0){
        //$start = ($page - 1) * $pagesize;

        $dalResource = new DALResource($this->_appSetting);
        $resourceList = $dalResource->getRecommendListV2($start, $pagesize, $platform, $is_new, $match_num, $type, $is_free, $match_type);
        return $resourceList;
    }





    /*
     * 我关注的专家料数据--新增
     */
    public function getMyExpertResourceList($userId,$page = 1, $pagesize = 20,$platform = 2){
        $start = ($page - 1) * $pagesize;
        //获取关联账号
        $userModel = new UserModel();
        $userInfo = $userModel->getUserInfo($userId);
        if($userInfo['uuid']) {
            $users = $userModel->getUsersByUUid($userInfo['uuid']);
            $uids = implode(', ', array_column($users, 'user_id'));
            $userId=$uids;
        }
        //获取我关注的专家
        $DALUserFollowExpert=new DALUserFollowExpert($this->_appSetting);
        $expertIds=$DALUserFollowExpert->getUserFollowExpertList($userId);
        if(empty($expertIds)){
            return [];
        }
        $expertIds=array_column($expertIds,'expert_id');
        $expertIdStr=implode(',',$expertIds);

        //获取mysql数据
        $dalResource = new DALResource($this->_appSetting);
        //获取专家料
        $resourceIdList = $dalResource->getMyExpertResourceList($expertIdStr,$start, $pagesize,0,$platform);


        $resourceIds = [];
        if(!empty($resourceIdList)){
//                foreach($resourceIdList as $key => $val){
//                    //相关数据入redis
//                    $resourceIds[] = $resourceId = $val['resource_id'];
//                }
            $resourceIds=array_column($resourceIdList,'resource_id');
        }


        $resourceList = [];
        if(!empty($resourceIds)){
            foreach($resourceIds as $key => $resourceId){
                $resourceInfo = $this->getResourceBriefInfo($resourceId);
                $resourceScheduleList = $this->getResourceScheduleList($resourceId);
                $resourceList[$key] = $resourceInfo;
                $resourceList[$key]['schedule'] = $resourceScheduleList;
                if ($resourceInfo['is_groupbuy'] == 1) {
                    $resourceList[$key]['group'] = $this->getResourceGroupInfo($resourceId);
                }
            }
        }

        return $resourceList;
    }
    //推荐料数量--新增
    public function getRecommendResourceCount($expertId){
        $dalResource = new DALResource($this->_appSetting);
        $count=$dalResource->getRecommendResourceCount($expertId);
        return $count;
    }

    /*
     * 获取专家下最新的料
     */
    public function getExpertNewResource($expertId){
        $dalResource = new DALResource($this->_appSetting);
        $result=$dalResource->getExpertNewResource($expertId);
        return $result;
    }

    /**
     * 首页N条新方案
     * @param     $startTime
     * @return array
     */
    public function getNewRecommendResourceList($startTime, $platform = 2, $is_free = 1){
        //获取mysql数据
        $dalResource = new DALResource($this->_appSetting);
        $resourceIdList = $dalResource->getNewRecommendResourceList($startTime, $platform, $is_free);
        $resourceIds = [];
        if(!empty($resourceIdList)){
            foreach($resourceIdList as $key => $val){
                //相关数据入redis
                $resourceIds[] = $resourceId = $val['resource_id'];
            }
        }

        $resourceList = [];
        if(!empty($resourceIds)){
            foreach($resourceIds as $key => $resourceId){
                $resourceInfo = $this->getResourceBriefInfo($resourceId);
                $resourceScheduleList = $this->getResourceScheduleList($resourceId);
                $resourceList[$key] = $resourceInfo;
                $resourceList[$key]['schedule'] = $resourceScheduleList;
            }
        }
        return $resourceList;
    }

    /**
     * 设置自增或者自减料扩展信息
     * @param $resourceId
     * @param $params
     * @return int
     */
    public function setResourceExtraIncOrDec($resourceId, $params){
        $dalResourceExtra = new DALResourceExtra($this->_appSetting);
        $redisKey = RESOURCE_EXTRA_INFO . $resourceId;
        $this->_redisModel->redisDel($redisKey);
        return $dalResourceExtra->setResourceExtraIncOrDec($resourceId, $params);
    }

    /**
     * 获取料简要信息
     * @param $resourceId
     * @return array|bool|mixed|null|string
     */
    public function getResourceBriefInfo($resourceId, $isYuan = true){
        $resourceInfo = $this->getResourceInfo($resourceId, $isYuan);
        $resourceExtraInfo = $this->getResourceExtraInfo($resourceId);
        $resourceInfo = array_merge($resourceInfo, $resourceExtraInfo);
        return $resourceInfo;

    }

    /**
     * 获取料详细信息
     * @param $resourceId
     * @return array
     */
    public function getResourceDetailedInfo($resourceId, $userId = 0){
        //获取料主要信息
        $resourceInfo = $this->getResourceInfo($resourceId);
        if(empty($resourceInfo)){
            //料信息不存在
            return [];
        }
        $resourceInfo['is_attented'] = 0;
        if ($userId) {
            $dalResource = new DALResource($this->_appSetting);
            $attentedInfo = $dalResource->getAttentionInfo(['resource_id' => $resourceId, 'user_id' => $userId]);
            $is_attented = empty($attentedInfo) ? 0 : $attentedInfo['status'];
            $resourceInfo['is_attented'] = $is_attented;
        }
        //获取料扩展信息
        $resourceExtraInfo = $this->getResourceExtraInfo($resourceId);

        //料浏览数量
        //$redisKey = RESOURCE_VIEW;
        // $resourceExtraInfo['view_num'] = $this->_redisModel->redisGetHashList($redisKey,$resourceId);

        $data = array_merge($resourceInfo, $resourceExtraInfo);

        $data['is_placement'] = $this->transPlatform($data, 'placement');
        $data['display_platform'] = $this->transPlatform($data, 'display');

        //获取料详情
        $resourceDetailList = $this->getResourceDetailList($resourceId);

        foreach($resourceDetailList as $key => $val){
            $detailId = $val['detail_id'];

            //获取关联附件
            $staticList = $this->getResourceStaticList($resourceId, $detailId);
            $resourceStaticList = [];
            if(!empty($staticList)){
                foreach($staticList as $k => $v){
                    if (!empty($v['url'])) {
                        $resourceStaticList[$k]['static_type'] = $v['static_type'];
                        $resourceStaticList[$k]['url'] = $v['url'];
                    }
                }
            }
            $resourceDetailList[$key]['static'] = $resourceStaticList;

            //获取关联赛事
            $resourceScheduleList = $this->getResourceScheduleList($resourceId, $detailId);

            $resourceDetailList[$key]['schedule'] = $resourceScheduleList;
        }

        $data['detail'] = $resourceDetailList;
        return $data;
    }

    /**
     * 获取料信息
     * @param $resourceId
     * @return bool|mixed|null|string
     */
    public function getResourceInfo($resourceId, $isYuan = true){
        $dalResource = new DALResource($this->_appSetting);
        $redisKey = RESOURCE_INFO . $resourceId;
        //$resourceInfo = $this->_redisModel->redisGet($redisKey, true);
        if(empty($resourceInfo)){
            $resourceInfo = $dalResource->getResourceInfo($resourceId);
            //$this->_redisModel->redisSet($redisKey, $resourceInfo);
        }
        if(!empty($resourceInfo)){
            $price = $resourceInfo['price'];
            $resourceInfo['price'] = $isYuan ? $this->ncPriceFen2Yuan($resourceInfo['price']) : $resourceInfo['price'];
            $resourceInfo['price_int'] = $isYuan ? $this->ncPriceFen2YuanInt($price) : $price;
            $resourceInfo['release_time_friendly'] = $this->friendlyDate($resourceInfo['release_time']);
            $resourceInfo['stat_time'] = $resourceInfo['create_time'];
            $resourceInfo['limited_time_friendly'] = $this->friendlyDate($resourceInfo['limited_time'], 'full');
            $resourceInfo['create_time_friendly'] = $this->friendlyDate($resourceInfo['create_time']);
        }
        $resourceInfo['display_platform'] = (string)$resourceInfo['wx_display'] . $resourceInfo['bd_display'];
        return $resourceInfo;
    }

    /**
     * 创建料扩展数据
     * @param $resourceExtra
     * @return int
     */
    public function createResourceExtra($resourceExtra){
        $dalResourceExtra = new DALResourceExtra($this->_appSetting);
        return $dalResourceExtra->createResourceExtra($resourceExtra);
    }

    /**
     * 创建料扩展数据
     * @param $groupData array
     * @return array | bool
     */
    public function createResourceGroup($groupData) {
        $dalResourceGroup = new DALResourceGroup($this->_appSetting);
        $groupData['price'] = intval($this->ncPriceYuan2Fen($groupData['price']));
        return $dalResourceGroup->createResourceGroup($groupData);
    }

    /**
     * 获取料扩展信息
     * @param $resourceId
     * @return bool|mixed|null|string
     */
    public function getResourceExtraInfo($resourceId){
        $dalResourceExtra = new DALResourceExtra($this->_appSetting);
        $redisKey = RESOURCE_EXTRA_INFO . $resourceId;
        $resourceExtraInfo = $this->_redisModel->redisGet($redisKey, true);
        if(empty($resource_info)){
            $resourceExtraInfo = $dalResourceExtra->getResourceExtraInfo($resourceId);
            $this->_redisModel->redisSet($redisKey, $resourceExtraInfo);
        }
        //料浏览数量
        $redisKey = RESOURCE_VIEW;
        $view_num = (int)$this->_redisModel->redisGetHashList($redisKey,$resourceId);
        $resourceExtraInfo['view_num']=$view_num+$resourceExtraInfo['view_num'];

        //通过比赛判定红黑单
        $resourceScheduleList = $this->getResourceScheduleList($resourceId);
        $bet_status = $this->getBetStatus($resourceScheduleList);
        if(empty($resourceScheduleList)){
            $bet_status=$resourceExtraInfo['bet_status'];
        }
        //如果有手动判的 已手动判的为准
        if ($resourceExtraInfo['bet_status']) {
            $bet_status=$resourceExtraInfo['bet_status'];
        }
        $resourceExtraInfo['bet_status']=$bet_status;
        return $resourceExtraInfo;
    }

    /**
     * 获取料合买信息
     * @param $resourceId
     * @param $fields
     * @return array | bool
     */
    public function getResourceGroupInfo($resourceId, $isYuan = true) {
        $dalResourceGroup = new DALResourceGroup($this->_appSetting);
        $redisKey = RESOURCE_GROUP_INFO . $resourceId;
        $resourceGroupInfo = $this->_redisModel->redisGet($redisKey, true);
        if (empty($resourceGroupInfo)) {
            $resourceGroupInfo = $dalResourceGroup->getResourceGroupInfo($resourceId);
            $this->_redisModel->redisSet($redisKey, $resourceGroupInfo);
        }
        if (isset($resourceGroupInfo['price']) && $isYuan) {
            $resourceGroupInfo['price'] = $this->ncPriceFen2Yuan($resourceGroupInfo['price']);
            $resourceGroupInfo['price_int'] = $this->ncPriceFen2YuanInt($resourceGroupInfo['price']);
        }
        return $resourceGroupInfo;
    }

    /**
     * 设置用户浏览量至redis
     * @param $resourceId
     */
    public function setResourceViewToRedis($resourceId){
        $redisKey = RESOURCE_VIEW;
        return $this->_redisModel->redisHincrby($redisKey, $resourceId, 1);
    }

    /**
     * 设置用户浏览量至mysql
     */
    public function setResourceViewToMysql(){
        $redisKey = RESOURCE_VIEW;
        $resourceIds = $this->_redisModel->redisHkeys($redisKey);
        if(!empty($resourceIds)){
            $dalResourceExtra = new DALResourceExtra($this->_appSetting);
            foreach($resourceIds as $resourceId){
                //获取浏览量
                $viewNum = $this->_redisModel->redisGetHashList($redisKey, $resourceId);
                //清空redis浏览量
                $this->_redisModel->redisHdel($redisKey, $resourceId);
                $this->setResourceExtraIncOrDec($resourceId, ['view_num' => '+'.$viewNum]);
            }
        }
    }

    /**
     * 创建料内容详情信息
     * @param $resourceDetail
     * @return int
     */
    public function createResourceDetail($resourceDetail){
        $dalResourceDetail = new DALResourceDetail($this->_appSetting);
        return $dalResourceDetail->createResourceDetail($resourceDetail);
    }

    /**
     * 获取料内容详情信息
     * @param $resourceId
     * @return bool|mixed|null|string
     */
    public function getResourceDetailList($resourceId){
        $dalResourceDetail = new DALResourceDetail($this->_appSetting);
        $redisKey = RESOURCE_DETAIL_INFO . $resourceId;
        $resourceDetailList = $this->_redisModel->redisGet($redisKey, true);
        if(empty($resourceDetailList)){
            $resourceDetailList = $dalResourceDetail->getResourceDetailList($resourceId);
            $this->_redisModel->redisSet($redisKey, $resourceDetailList);
        }
        return $resourceDetailList;
    }

    /**
     * 创建料附件数据
     * @param $resourceStatic
     * @return int
     */
    public function createResourceStatic($resourceStatic){
        $dalResourceStatic = new DALResourceStatic($this->_appSetting);
        return $dalResourceStatic->createResourceStatic($resourceStatic);
    }

    /**
     * 获取料附件数据列表
     * @param     $resourceId
     * @param int $detailId
     * @return array|bool|mixed|null|string
     */
    public function getResourceStaticList($resourceId, $detailId = 0){
        $dalResourceStatic = new DALResourceStatic($this->_appSetting);
        $redisKey = RESOURCE_STATIC_INFO . $resourceId . ':' . $detailId;
        $resourceStaticList = $this->_redisModel->redisGet($redisKey, true);
        if(empty($resourceStaticList)){
            $resourceStaticList = $dalResourceStatic->getResourceStaticList($resourceId, $detailId);
            $this->_redisModel->redisSet($redisKey, $resourceStaticList);
        }
        return $resourceStaticList;
    }

    /**
     * 新建一个料关联赛事
     * @param $resourceSchedule
     * @return int
     */
    public function createResourceSchedule($resourceSchedule){
        $dalResourceSchedule = new DALResourceSchedule($this->_appSetting);
        return $dalResourceSchedule->createResourceSchedule($resourceSchedule);
    }

    /**
     * 获取料关联赛事数据列表
     * @param     $resourceId
     * @param int $detailId
     * @return array
     */
    public function getResourceScheduleList($resourceId, $detailId = 0){
        $dalResourceSchedule = new DALResourceSchedule($this->_appSetting);
        $redisKey = RESOURCE_SCHEDULE_INFO . $resourceId . ':' . $detailId;
        //$resourceScheduleList = $this->_redisModel->redisGet($redisKey, true);
        //if(empty($resourceScheduleList)){
        $resourceScheduleList = $dalResourceSchedule->getResourceScheduleList($resourceId, $detailId);
        //$this->_redisModel->redisSet($redisKey, $resourceScheduleList);
        //}
        $scheduleList = [];
        if(!empty($resourceScheduleList)){
            $wdl = '';
            $matchModel = new MatchModel();
            foreach($resourceScheduleList as $k => $v){
                //获取赛事详情
                $scheduleInfo = $matchModel->getScheduleInfo($v['schedule_id'], $v['type']);
                if (empty($scheduleInfo)) {
                    continue;
                }
                $formatScheduleTime = $this->formatScheduleTime($scheduleInfo['schedule_time']);
                $scheduleInfo = array_merge($scheduleInfo, $formatScheduleTime);
                $scheduleInfo['id']=$v['id'];
                $scheduleInfo['lottery_type']=$v['lottery_type'];
                $scheduleInfo['lottery_id']=$v['lottery_id'];
                $scheduleInfo['h']=$v['h'];
                $scheduleInfo['w']=$v['w'];
                $scheduleInfo['d']=$v['d'];
                if ($v['type'] == 2) {
                    unset($scheduleInfo['d']);
                    $scheduleInfo['play_method_text'] = $this->play_method[$v['d']];
                    $scheduleInfo['play_method'] = $v['d'];
                }
                $scheduleInfo['l']=$v['l'];
                $scheduleInfo['recommend']=$v['recommend']?$v['recommend']:'';
                if (isset($scheduleInfo['recommend']) && !empty($scheduleInfo['recommend'])) {
                    $recs = explode(',', $scheduleInfo['recommend']);
                    foreach ($recs as $i) {
                        $wdl .= $wdl ? '/' . $v[$i] : $v[$i];
                    }
                }
                $scheduleInfo['lottery_result']=$v['lottery_result'];
                $scheduleInfo['bet_status']=$v['bet_status'];
                $scheduleList[] = $scheduleInfo;
            }
        }
        if (!empty($scheduleList)) {
            $scheduleList[0]['wdl'] = $wdl;
        }
        $matchs = $dalResourceSchedule->getResourceMatchInfo($resourceId);
        if ($matchs) {
            foreach ($matchs as $v) {
                $tmp['match_type'] = $v['type'];
                $tmp['master_team'] = $v['host_name'];
                $tmp['guest_team'] = $v['guest_name'];
                $tmp['league_name'] = $v['league_name'];
                $tmp['is_signle'] = 0;    //以前的数据全部默认不是单关
                $tmp['is_jc'] = 0;   //以前数据全部默认按“所有”处理
                $tmp['is_bd'] = 0;   //以前数据全部默认按“所有”处理
                $tmp['schedule_time'] = strtotime($v['date']);
                $tmp['match_type_icon'] = $this->_appSetting->getConstantSetting('STATIC_URL') . 'match_type/' . $tmp['match_type'] . '.png';
                $times = $this->formatScheduleTime($tmp['schedule_time']);
                $tmp = array_merge($tmp, $times);
                $scheduleList[] = $tmp;
            }
        }
        return $scheduleList;
    }

    /**
     * 获取料列表
     * @param     $expertId
     * @param int $source
     * @param     $page
     * @param     $pagesize
     * @return array
     */
    public function getResourceListByExpertId($expertId, $source = 1, $page, $pagesize){
        $start = ($page - 1) * $pagesize;
        $redisKey = '';
        switch($source){
            case 1:
                //用户访问获取
                $redisKey = RESOURCE_LIST . $GLOBALS['display'] . ':' . $expertId;
                break;
            case 2:
                //专家访问获取
                $redisKey = RESOURCE_EXPERT_LIST . $GLOBALS['display'] . ':' . $expertId;
                break;
            case 3:
                //后台访问获取
                break;
        }

//        $resourceIds = $this->_redisModel->redisZRange($redisKey, $start, ($page * $pagesize) - 1);
        //根据分值范围获取redis数据
        $resourceIds = !empty($redisKey) ? $this->_redisModel->redisZRangeByScore($redisKey, $start, $start + $pagesize - 1) : [];

        if(empty($resourceIds) || count($resourceIds) < $pagesize){
            //获取mysql数据
            $dalResource = new DALResource($this->_appSetting);
            $resourceIdList = $dalResource->getResourceIdListByExpertId($expertId, $source, $start, $pagesize);

            $resourceIds = [];
            if(!empty($resourceIdList)){
                foreach($resourceIdList as $key => $val){
                    //相关数据入redis
                    $resourceIds[] = $resourceId = $val['resource_id'];
                    !empty($redisKey) && $this->_redisModel->redisZAdd($redisKey, $key + $start, $resourceId);
                }
            }
        }

        $resourceList = [];
        if(!empty($resourceIds)){
            foreach($resourceIds as $key => $resourceId){
                $resourceList[$key] = $this->getResourceBriefInfo($resourceId);
                if($source == 1 || $source = 3){
                    $resourceScheduleList = $this->getResourceScheduleList($resourceId);
                    $resourceList[$key]['schedule'] = $resourceScheduleList;
                }
            }
        }
        return $resourceList;
    }

    public function getResourceListByExpertId2($expertId, $source = 1, $finished = null, $page = 0, $pagesize = 0){
        $start = 0;
        ($pagesize != 0) && $start = ($page - 1) * $pagesize;

        /*$redisModel = new RedisModel('resource');
        $redisKey = $this->getExpertResourceKey($expertId, $source, $finished, $GLOBALS['display']);
        $update = 0;
        // 缓存的分页数
        $redisPageSize = $redisModel->redisGetHashList($redisKey, 'pagesize');
        if (is_null($redisPageSize) || $redisPageSize === false || $redisPageSize != $pagesize) {
            $redisManageModel = new RedisManageModel('resource');
            $redisManageModel->delList($redisKey);
            $update = 1;
        } else {
            $resourceIds = $redisModel->redisGetHashList($redisKey, 'page_' . $page, true);
            if (!$resourceIds) {
                $update = 1;
            }
        }*/
        $update = 1;
        if ($update === 1) {
            $condition = '`expert_id` = ' . $expertId;
            switch ($source) {
                case 1:
                    //用户访问获取
                    $condition .= " AND `resource_status` = 1";
                    break;
                case 2:
                    //专家访问获取
                    $condition .= " AND `resource_status` < 4";
                    break;
                case 3:
                    //后台访问获取
                    break;
            }

            switch($GLOBALS['display']){
                case 1:
                    $condition .= " AND `wx_display` = 1";
                    break;
                case 2:
                    $condition .= " AND `bd_display` = 1";
                    break;
            }

            if (!is_null($finished)) {
                $condition .= " AND `is_schedule_over` in($finished,2) ";
            }

            //获取mysql数据
            $dalResource = new DALResource($this->_appSetting);
            $resourceIdList = $dalResource->getResourceIdList2($condition, 'resource_id', '`is_schedule_over` ASC, `resource_status` ASC, `release_time` DESC', $start, $pagesize);

            $resourceIds = [];
            if (!empty($resourceIdList)) {
                foreach ($resourceIdList as $key => $val) {
                    $resourceIds[] = $resourceId = $val['resource_id'];
                }
            }
            //$redisModel->redisSetHashList($redisKey, 'pagesize', $pagesize);
            //$redisModel->redisSetHashList($redisKey, 'page_' . $page, $resourceIds);
        }

        $resourceList = [];
        if(!empty($resourceIds)){
            foreach($resourceIds as $key => $resourceId){
                $resourceList[$key] = $this->getResourceBriefInfo($resourceId);
                if(in_array($resourceList[$key]['is_schedule_over'],[2,3])){
                    $resourceList[$key]['is_schedule_over']=0;
                }
                if($source == 1 || $source = 3){
                    $resourceScheduleList = $this->getResourceScheduleList($resourceId);
                    $resourceList[$key]['schedule'] = $resourceScheduleList;
                    $bet_status=$this->getBetStatus($resourceScheduleList);
                    if($resourceList[$key]['bet_status']!=0){
                        $bet_status=$resourceList[$key]['bet_status'];
                    }
                    $resourceList[$key]['bet_status'] =$bet_status;
                }
                if ($resourceList[$key]['is_groupbuy'] == 1) {
                    $resourceList[$key]['group'] = $this->getResourceGroupInfo($resourceId);
                }
            }
        }
        return $resourceList;
    }

    public function getExpertResourceList($expertId, $platform = 1, $source = 1, $finished = null, $page = 1, $pagesize = 0){
        $start = ($page - 1) * $pagesize;

        $condition = '`expert_id` = ' . $expertId;
        switch ($source) {
            case 1:
                $condition .= " AND `resource_status` = 1";
                break;
            case 2:
                $condition .= " AND `resource_status` < 4";
                break;
        }

        $platform_field = ($platform == 1) ? 'bd_display' : 'wx_display';
        $condition .= " AND `$platform_field` = 1";

        if (!is_null($finished)) {
            $condition .= ' AND `is_schedule_over` = ' . $finished;
        }

        $dalResource = new DALResource($this->_appSetting);
        $resourceIdList = $dalResource->getResourceIdList2($condition, 'resource_id', '`is_schedule_over` ASC, `resource_status` ASC, `release_time` DESC', $start, $pagesize);

        $resourceList = [];
        if(!empty($resourceIdList)){
            foreach($resourceIdList as $key => $resource){
                $resourceList[$key] = $this->getResourceBriefInfo($resource['resource_id']);

                if($source == 1 || $source = 3){
                    $resourceScheduleList = $this->getResourceScheduleList($resource['resource_id']);
                    $resourceList[$key]['schedule'] = $resourceScheduleList;
                    //$resourceList[$key]['bet_status'] = $this->getBetStatus($resourceScheduleList);

                    $bet_status=$this->getBetStatus($resourceScheduleList);
                    if($resourceList[$key]['bet_status']!=0){
                        $bet_status=$resourceList[$key]['bet_status'];
                    }
                    $resourceList[$key]['bet_status'] =$bet_status;
                }
                if ($resourceList[$key]['is_groupbuy'] == 1) {
                    $resourceList[$key]['group'] = $this->getResourceGroupInfo($resource['resource_id']);
                }
                if ($resourceList[$key]['is_free']) {
                    $surfaces = $this->getResourceStaticList($resource['resource_id']);
                    $surface = $surfaces[0]['url'] ?: '';
                    $resourceList[$key]['surface'] = $surface;
                }
            }
        }
        return $resourceList;
    }

    /**
     * @param $expertId
     * @param $source 1 => 用户, 2 => 专家
     * @param $finished 0 => 未完赛, 1 => 已完赛
     * @return string
     */
    public function getExpertResourceKey($expertId, $source, $finished) {
        return RESOURCE_EXPERT_LIST_V2 . $expertId . ':' . $source . ':' . $finished;
    }

    /**
     * 获取料列表(后台)
     * @param $query
     * @param $page
     * @param $pagesize
     * @return array
     */
    public function getResourceList($query ,$page, $pagesize,$order=[]){
        $start = ($page - 1) * $pagesize;
        //获取mysql数据
        $dalResource = new DALResource($this->_appSetting);
        $resourceIdList = $dalResource->getResourceIdList($query, $start, $pagesize,$order);

        $resourceIds = [];
        if(!empty($resourceIdList)){
            foreach($resourceIdList as $key => $val){
                //相关数据入redis
                $resourceIds[] = $resourceId = $val['resource_id'];
                !empty($redisKey) && $this->_redisModel->redisZAdd($redisKey, $key + $start, $resourceId);
            }
        }
        $resourceList = [];
        if(!empty($resourceIds)){
            foreach($resourceIds as $key => $resourceId){
                $resourceList[$key] = $this->getResourceBriefInfo($resourceId);
                $resourceScheduleList = $this->getResourceScheduleList($resourceId);
                $resourceList[$key]['schedule'] = $resourceScheduleList;
                if (!$resourceList[$key]['bet_status']) {
                    $bet_status = $this->getBetStatus($resourceScheduleList);
                    $resourceList[$key]['bet_status'] = $bet_status;
                }
                $resourceList[$key]['is_placement'] = $this->transPlatform($resourceList[$key], 'placement');
                $resourceList[$key]['display_platform'] = $this->transPlatform($resourceList[$key], 'display');
                if ($resourceList[$key]['is_groupbuy'] == 1) {
                    $resourceGroup = $this->getResourceGroupInfo($resourceId);
                    $resourceList[$key]['group'] = $resourceGroup;
                }
                $resourceList[$key]['people_view_num']=$this->getViewRecord($resourceId);
            }
        }
        return $resourceList;
    }

    public function syncData() {
        $dalResourceSchedule = new DALResourceSchedule($this->_appSetting);
        $relations = $dalResourceSchedule->syncData();
        var_dump($relations);die;
    }

    /*
     * 获取方案人浏览总数
     */
    public function getViewRecord($resourceId){
        $dalResource = new DALResource($this->_appSetting);
        $data=$dalResource->getViewRecord($resourceId);
        return $data['count']?$data['count']:0;
    }


    /**
     * 根据专家ID获取最近连红次数
     * @param $expertId
     * @return mixed
     */
    public function getContinuityRedNumByExpertId($expertId){
        $redisKey = RESOURCE_CONTINUITY_RED_NUM . $expertId;
        $continuityRedNum = $this->_redisModel->redisGet($redisKey);
        if($continuityRedNum === null){
            $dalResource = new DALResource($this->_appSetting);
            $continuityRedNum = $dalResource->getContinuityRedNumByExpertId($expertId);
            $this->_redisModel->redisSet($redisKey, $continuityRedNum);
        }
        return $continuityRedNum;
    }

    /**
     * 获取专家料总数
     * @param      $expertId
     * @param null $is_schedule_over
     * @return bool|mixed|null|string
     */
    public function getResourceTotalByExpertId($expertId, $is_schedule_over = null){
        $redisKey = RESOURCE_EXPERT_TOTAL . $expertId . ':' . $is_schedule_over;
        $resourceTotal = $this->_redisModel->redisGet($redisKey, true);
        if($resourceTotal === null){
            $dalResource = new DALResource($this->_appSetting);
            $resourceTotal = $dalResource->getResourceTotalByExpertId($expertId, $is_schedule_over);
            $this->_redisModel->redisSet($redisKey, $resourceTotal);
        }
        return $resourceTotal;
    }

    /**
     * 获取料列表总数(后台)
     * @param $query
     * @return mixed
     */
    public function resourceListCount($query){
        $dalResource = new DALResource($this->_appSetting);
        return $dalResource->getResourceListCount($query);
    }

    /**
     * 更新料扩展信息(后台用)
     * @param $resourceId
     * @param $params
     */
    public function updateResourceExtra($resourceId, $params){
        $dalResourceExtra = new DALResourceExtra($this->_appSetting);
        $dalResourceExtra->updateResourceExtra($resourceId, $params);
        $redisManageModel = new RedisKeyManageModel('resource');
        $redisManageModel->delResourceKey($resourceId);
    }


    /**
     * 删除料内容信息（后台）
     * @param $resourceId
     * @return int
     */
    public function deleteResourceDetail($resourceId){
        $dalResourceDetail = new DALResourceDetail($this->_appSetting);
        return $dalResourceDetail->deleteResourceDetail($resourceId);
    }

    /**
     * 删除料赛事关联信息（后台）
     * @param $resourceId
     * @return int
     */
    public function deleteResourceSchedule($resourceId){
        $dalResourceSchedule = new DALResourceSchedule($this->_appSetting);
        return $dalResourceSchedule->deleteResourceSchedule($resourceId);
    }

    /**
     * 删除料附件数据（后台）
     * @param $resourceId
     * @return int
     */
    public function deleteResourceStatic($resourceId){
        $dalResourceStatic = new DALResourceStatic($this->_appSetting);
        return $dalResourceStatic->deleteResourceStatic($resourceId);
    }

    /**
     * 更新料主体信息（后台）
     * @param      $resourceId
     * @param      $params
     * @param bool $isNotice
     * @return int
     */
    public function updateResource($resourceId, $params, $isNotice = false) {
        $dalResource = new DALResource($this->_appSetting);
        $res = $dalResource->updateResource($resourceId, $params);

        $redisManageModel = new RedisKeyManageModel('resource');
        $redisManageModel->delResourceKey($resourceId);

        if($isNotice){
            //进入资源更新通知粉丝列表
            $this->_redisModel->redisLpush(RESOURCE_UPDATE_NOTICE_LIST, $resourceId);
        }

        return $res;
    }

    /**
     * 获取料的比赛类型，不中退款料统计使用（后台统计用）
     * @param $resourceId
     * @return mixed
     */
    public function getResourceMatchType($resourceId){
        $dalResourceSchedule = new DALResourceSchedule($this->_appSetting);
        return $dalResourceSchedule->getResourceMatchType($resourceId);
    }

    /**
     * 获取未完赛料(定时程序)
     * @param $start
     * @return mixed
     */
    public function getNotOverResource($start){
        $dalResource = new DALResource($this->_appSetting);
        return $dalResource->getNotOverResource($start);
    }

    /**
     * 获取专家有效料的最后发布时间(定时程序)
     * @param $expertId
     * @return mixed
     */
    public function getLastResourceReleaseTime($expertId){
        $dalResource = new DALResource($this->_appSetting);
        return $dalResource->getLastResourceReleaseTime($expertId);
    }

    /**
     * 设置退款料
     * @param $resourceId
     * @param $refundType
     */
    public function setRefundResource($resourceId, $refundType = 2) {
        $dalResourceRefund = new DALResourceRefund($this->_appSetting);
        $params['resource_id'] = $resourceId;
        $params['create_time'] = time();
        $params['status'] = 0;
        $params['refund_type'] = $refundType;
        $dalResourceRefund->newRefund($params);
    }

    /**
     * 更新料退款信息
     * @param $resourceId
     * @param $params
     * @return int
     */
    public function updateRefundResource($resourceId, $params) {
        $dalResourceRefund = new DALResourceRefund($this->_appSetting);
        return $dalResourceRefund->updateRefund($resourceId, $params);
    }


    /**
     * 获取一个退款料id
     * @return mixed
     */
    public function getRefundResourceId() {
        $dalResourceRefund = new DALResourceRefund($this->_appSetting);
        return $dalResourceRefund->getRefundResourceId();
    }

    /**
     * 获取料扩展信息列表接口
     * @param string $condition
     * @param string $fields
     * @param int $start
     * @param int $limit
     * @return array|bool
     */
    public function getResourceExtraList($condition = '', $fields = '', $start = 0, $limit = 0) {
        $dalResourceExtra = new DALResourceExtra($this->_appSetting);
        return $dalResourceExtra->lists($condition, $fields, $start, $limit);
    }

    /**
     * 销量加上定时程序销量
     * @param $resource
     */
    public function addCronSoldNum(&$resource) {
        if (isset($resource['sold_num'])) {
            $resource['sold_num'] += isset($resource['cron_sold_num']) ? $resource['cron_sold_num'] : 0;
            unset($resource['cron_sold_num']);
        }
    }

    /**
     * 获取资源列表
     */
    public function lists($condtion = '', $fields = '', $order = '', $other = [], $start = 0, $limit = 0) {
        $dalResource = new DALResource($this->_appSetting);
        return $dalResource->lists($condtion, $fields, $order, $other, $start, $limit);
    }

    /**
     * 微信百度平台字段分开操作
     * @param $resourceId
     * @param $isPlacement
     * @return bool|int
     */
    public function setPlacement($resourceId, $isPlacement) {
        $upData = $this->transPlatform($isPlacement, 'placement');
        if (!$upData) {
            return false;
        }
        $upData['modify_time'] = time();
        $dalResource = new DALResource($this->_appSetting);
        $redisModel = new RedisKeyManageModel('resource');
        $redisModel->delResourceKey($resourceId);
        return $dalResource->updateResource($resourceId, $upData);
    }

    /**
     * 置顶字段数据库存储和显示的转换
     * @param $data
     * @param string $field
     * @return string
     */
    public function transPlatform(&$data, $field) {
        if (is_array($data)) {  // 存储内容转输出内容
            return (string)$data['wx_' . $field] . (string)$data['bd_' . $field];
        } else {
            $data = sprintf('%02d', $data);
            $arr['wx_' . $field] = $data[0];
            $arr['bd_' . $field] = $data[1];
            return $arr;
        }
    }

    /**
     * 获取合买料列表
     * @param string $condition
     * @param array $other
     * @param int $start_item
     * @param int $pagesize
     * @return array|bool
     */
    public function groupControlList($condition = '', $other = [], $start_item = 0, $pagesize = 0) {
        $dalResource = new DALResource($this->_appSetting);
        $other['join'] = [['hl_resource_group', 'hl_resource.resource_id = hl_resource_group.resource_id']];
        $resourceList = $dalResource->lists($condition, 'hl_resource.resource_id', 'status asc, create_time desc', $other, $start_item, $pagesize);
        foreach ($resourceList as &$item) {
            $resourceInfo = $this->getResourceInfo($item['resource_id']);
            $resourceExtraInfo = $this->getResourceExtraInfo($item['resource_id']);
            $resourceGroup = $this->getResourceGroupInfo($item['resource_id']);
            $item['title'] = $resourceInfo['title'];
            $item['group_status'] = $resourceGroup['status'];
            $item['group_price'] = $resourceGroup['price'];
            $item['group_num'] = $resourceGroup['num'];
            $item['sold_num'] = $resourceExtraInfo['sold_num'];
            $item['show_sold_num'] = $resourceExtraInfo['sold_num'] + $resourceExtraInfo['cron_sold_num'];
            $item['create_time'] = date('Y-m-d H:i', $resourceInfo['create_time']);
            $item['schedule_time'] = date('Y-m-d H:i', $resourceExtraInfo['schedule_time']);
            $operable = $this->checkGroupOperable($resourceInfo, $resourceGroup);
            $item['stop_cron_able'] = ($this->getSoldCronStatus($item['resource_id']) === true && $operable) ? 1 : 0;  // 0: 停止按钮不可用 1:停止按钮可用
            $item['cron_sold_inc'] = $operable ? 1 : 0;
        }
        return $resourceList;
    }

    public function groupList($condition = '', $fields = '', $order = '', $other = [], $start = 0, $limit = 0) {
        $dalResourceGroup = new DALResourceGroup($this->_appSetting);
        return $dalResourceGroup->lists($condition, $fields, $order, $other, $start, $limit);
    }

    /**
     * 获取显示销量脚本某个料队列中的信息
     * @param $resourceId
     * @return bool
     */
    public function getSoldCronStatus($resourceId) {
        $dalResource = new DALResource($this->_appSetting);
        $cronInfo = $dalResource->getSoldCronInfo($resourceId);
        return empty($cronInfo) ? false : true;
    }

    /**
     * 停止某个料显示销量脚本
     * @param $resourceId
     * @return bool
     */
    public function stopSoldCron($resourceId) {
        $redisKey = RESOURCE_CRONSOLDNUM_DATA;
        $redisModel = new RedisModel('resource');
        $redisModel->redisHdel($redisKey, $resourceId);
        return true;
    }

    /**
     * 显示销量+1
     * @param $resourceExtraInfo
     * @param $resourceGroupInfo
     * @return bool
     */
    public function cronSoldInc(&$resourceExtraInfo, &$resourceGroupInfo) {
        $cronSoldNum = $resourceExtraInfo['cron_sold_num'] + 1;
        $this->updateResourceExtra($resourceExtraInfo['resource_id'], ['cron_sold_num' => $cronSoldNum]);

        $soldNum = $cronSoldNum + $resourceExtraInfo['sold_num'];

        if ($soldNum >= $resourceGroupInfo['num']) { // 合买成功
            $this->groupSuccess($resourceExtraInfo['resource_id']);
        }
        return true;
    }

    /**
     * 合买成功操作
     * @param $resourceId
     * @return bool
     */
    public function groupSuccess($resourceId) {
        // 修改料合买状态为成功
        $this->updateResourceGroup($resourceId, ['status' => 1, 'over_time' => time()]);

        // 添加到合买成功通知队列
        $redisModel = new RedisModel('resource');
        $redisKey = RESOURCE_GROUP_NOTICE_SUCCESS;
        $redisModel->redisRpush($redisKey, $resourceId);

        // 显示销量增加停止
        $this->stopSoldCron($resourceId);

        return true;
    }

    /**
     * 更新料的合买信息
     * @param $resourceId
     * @param $upData
     */
    public function updateResourceGroup($resourceId, $upData) {
        $dalResourceExtra = new DALResourceGroup($this->_appSetting);
        $dalResourceExtra->updateResourceGroup(['resource_id' => $resourceId], $upData);
        $redisModel = new RedisModel('resource');
        $redisKey = RESOURCE_GROUP_INFO . $resourceId;
        $redisModel->redisDel($redisKey);
    }


    /**
     * 验证合买料是否可以进行流程控制操作
     * @param $resourceInfo
     * @param $resourceGroupInfo
     * @return bool
     */
    public function checkGroupOperable(&$resourceInfo, &$resourceGroupInfo) {
        // 判断料是否为正常状态
        if ($resourceInfo['resource_status'] != 1) {
            return false;
        }
        // 判断料是否为合买料
        if ($resourceInfo['is_groupbuy'] != 1) {
            return false;
        }
        // 判断合买是否结束
        if ($resourceGroupInfo['status'] != 0) {
            return false;
        }

        return true;
    }

    public function getResourceListV2($condition = array(), $fields = array(), $offset = 0, $limit = 0, $orderBy = array()) {
        $dalResource = new DALResource($this->_appSetting);
        return $dalResource->getResourceListV2($condition, $fields, $offset, $limit, $orderBy);
    }

    public function getFreeResourcesCount($platform = 1) {
        $dalResource = new DALResource($this->_appSetting);
        $countInfo = $dalResource->getFreeResourcesCount($platform);
        return $countInfo['count'];
    }

    public function getResourceListByMatch($start = 0, $pagesize = 20, $platform = 1, $match_num = 0, $match_type = 1) {
        $dalResource = new DALResource($this->_appSetting);
        return $dalResource->getResourceListByMatch($start, $pagesize, $platform, $match_num, $match_type);
    }

    public function setAttentStatus($resource_id, $user_id, $status) {
        $dalResource = new DALResource($this->_appSetting);
        $attentionInfo = $dalResource->getAttentionInfo(['resource_id' => $resource_id, 'user_id' => $user_id]);
        if ($attentionInfo) {
            //update
            if ($status != $attentionInfo['status']) {
                $dalResource->updateAttentionInfo(['status' => $status, 'utime' => date('Y-m-d H:i:s', time())], ['resource_id' => $resource_id, 'user_id' => $user_id]);
            }
        } else {
            //new
            if ($status) {
                $data = ['resource_id' => $resource_id, 'user_id' => $user_id, 'status' => $status, 'ctime' => date('Y-m-d H:i:s', time()), 'utime' => date('Y-m-d H:i:s', time())];
                $dalResource->createAttention($data);
            }
        }
        return true;
    }

    public function getAttentionList($condition = [], $fields = [], $page = 1, $pagesize = 20, $orderBy = []) {
        $dalResource = new DALResource($this->_appSetting);
        $offset = ($page - 1) * $pagesize;
        return $dalResource->getAttentionList($condition, $fields, $offset, $pagesize, $orderBy);
    }

    public function getAttentionCount($condition = []) {
        $dalResource = new DALResource($this->_appSetting);
        return $dalResource->getAttentionCount($condition);
    }

    public function getResourceScheduleInfo($condition = []) {
        $dalResourceSchedule = new DALResourceSchedule($this->_appSetting);
        return $dalResourceSchedule->getResourceScheduleInfo($condition);
    }

    /**
     *
     * 返回值：0未判完,1红单,2走单,3黑单,5二中一,6三中一,7三中二
     */
    public function getBetStatus($resourceScheduleList, $resource_id = 0) {
        $scheduleBetList = array_column($resourceScheduleList, 'bet_status');
        if (empty($resourceScheduleList) || empty($scheduleBetList)) {
            if (empty($resource_id)) {
                return 0;
            }
            $dal_resource_extra = new DALResourceExtra($this->_appSetting);
            $extra_info =$dal_resource_extra->getResourceExtraInfo($resource_id);
            $bet_status = $extra_info['bet_status'];
            return $bet_status;
        }
        $bet_status = 0;
        if (!in_array(0, $scheduleBetList)) {
            $total = count($scheduleBetList);
            $bet_count = array_count_values($scheduleBetList);
            $red_count = $black_count = $balance_count = 0;
            foreach($bet_count as $bet_key => $count) {
                if (in_array($bet_key, [1,4,5,6])) {
                    $red_count += $count;
                } else if (in_array($bet_key, [3,7])) {
                    $black_count += $count;
                } else {
                    $balance_count += $count;
                }
            }

            if ($red_count == 0) {
                $bet_status = ($black_count != 0) ? 3 : 2;
            } else {
                $bet_status = 1;
                if ($total == 2) {
                    $bet_status = ($red_count == $total) ? 1 : 5;
                } else if ($total == 3) {
                    $bet_status = ($red_count == $total) ? 1 : ($red_count == 1) ? 6 : 7;
                }
            }
        } else {
            if (!empty($resource_id)) {
                $dal_resource_extra = new DALResourceExtra($this->_appSetting);
                $extra_info =$dal_resource_extra->getResourceExtraInfo($resource_id);
                $bet_status = $extra_info['bet_status'];
            }
        }
        return $bet_status;
    }

    /*
     * 根据专家 获取某个时间段得 发布得料得数量
     * $expert_id
     * $start 开始时间
     * $end 结束时间
     */
    public function getTodayResourceCount($expertId,$start=0,$end=0){
        $dalResource = new DALResource($this->_appSetting);
        return $dalResource->getTodayResourceCount($expertId,$start,$end);
    }

    public function getResourceSchedules($condition = [], $fields = [], $offset = 0, $limit = 0, $orderBy = []) {
        $dalResourceSchedule = new DALResourceSchedule($this->_appSetting);
        return $dalResourceSchedule->getResourceSchedules($condition, $fields, $offset, $limit, $orderBy);
    }
}
