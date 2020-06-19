<?php
/**
 * 专家处理模块
 * User: WangHui
 * Date: 2018/10/10
 * Time: 10:10
 */

namespace QK\HaoLiao\Model;


use QK\HaoLiao\Common\StringHandler;
use QK\HaoLiao\DAL\DALResource;
use QK\HaoLiao\DAL\DALUserExpert;
use QK\HaoLiao\DAL\DALUserExpertExtra;
use QK\HaoLiao\DAL\DALUserExpertPresentAccount;
use QK\HaoLiao\DAL\DALUserExpertSubAccount;
use QK\HaoLiao\DAL\DALUserFollowExpert;

class ExpertModel extends BaseModel {

    private $_dalResource;
    private $_dalUserExpert;
    private $_dalUserExpertExtra;
    private $_dalUserExpertSubAccount;
    private $_dalUserExpertPresentAccount;
    private $_resourceModel;
    private $_expertExtraModel;
    private $_betRecordModel;
    private $_redisExpertModel;

    public function __construct() {
        parent::__construct();
        $this->_dalResource = new DALResource($this->_appSetting);
        $this->_dalUserExpert = new DALUserExpert($this->_appSetting);
        $this->_dalUserExpertExtra = new DALUserExpertExtra($this->_appSetting);
        $this->_dalUserExpertSubAccount = new DALUserExpertSubAccount($this->_appSetting);
        $this->_dalUserExpertPresentAccount = new DALUserExpertPresentAccount($this->_appSetting);
        $this->_resourceModel = new ResourceModel();
        $this->_expertExtraModel = new ExpertExtraModel();
        $this->_betRecordModel = new BetRecordModel();
        $this->_redisExpertModel = new RedisModel('expert');
    }

    /**
     * 检查用户身份
     * @param $uid
     * @return mixed
     */
    public function checkIdentity($uid) {
        $userModel = new UserModel();
        $userInfo = $userModel->getUserInfo($uid);
        return $userInfo['identity'];
    }

    /**
     * 获取专家主账号
     * @param $uid
     * @return mixed
     */
    public function getExpertInfoBySubAccount($uid) {
        return $subAccountInfo = $this->_dalUserExpertSubAccount->getBindInfo($uid);
    }

    public function getExpertByCondition($condition = []) {
        return $this->_dalUserExpert->getExpertByCondition($condition);
    }

    /**
     * 新建一个专家
     * @param $params
     */
    public function newExpert($params) {
        $expertParams['expert_id'] = intval($params['expert_id']);
        $expertParams['user_id'] = intval($params['user_id']);
        $expertParams['phone'] = StringHandler::newInstance()->stringExecute($params['phone']);
        $expertParams['expert_name'] = StringHandler::newInstance()->stringExecute($params['expert_name']);
        $expertParams['real_name'] = StringHandler::newInstance()->stringExecute($params['real_name']);
        $expertParams['idcard_number'] = StringHandler::newInstance()->stringExecute($params['idcard_number']);
        $expertParams['headimgurl'] = StringHandler::newInstance()->stringExecute($params['headimgurl']);
        $expertParams['desc'] = $params['desc'];
        $expertParams['platform'] = $params['platform'];
        $expertParams['identity_desc'] = $params['identity_desc'];
        $expertParams['tag'] = $params['tag'];
        $expertParams['expert_type'] = $params['expert_type'];
        $expertParams['create_time'] = time();
        $expertParams['sort'] = ($this->_dalUserExpert->getMaxSort()) + 1;
        $expertParams['expert_status'] = 0;
        //主表
        $expert_id = $this->_dalUserExpert->newExpert($expertParams);
        //附表
        $expertExtraParams['expert_id'] = $expert_id;
        $this->_dalUserExpertExtra->newExpertExtra($expertExtraParams);
        //提款账号表
        $expertPresentAccountParams = [];
        $expertPresentAccountParams['expert_id'] = $expert_id;
        $expertPresentAccountParams['type'] = 1;
        $expertPresentAccountParams['account'] = StringHandler::newInstance()->stringExecute($params['alipay_number']);
        $expertPresentAccountParams['bank'] = "支付宝";
        $expertPresentAccountParams['is_default'] = 0;
        $expertPresentAccountParams['account_status'] = 1;
        $expertPresentAccountParams['create_time'] = time();
        $this->_dalUserExpertPresentAccount->newExpertPresentAccount($expertPresentAccountParams);

        $expertPresentAccountParams = [];
        $expertPresentAccountParams['expert_id'] = $expert_id;
        $expertPresentAccountParams['type'] = 2;
        $expertPresentAccountParams['account'] = StringHandler::newInstance()->stringExecute($params['bank_number']);
        $expertPresentAccountParams['bank'] = StringHandler::newInstance()->stringExecute($params['bank']);
        $expertPresentAccountParams['is_default'] = 0;
        $expertPresentAccountParams['account_status'] = 1;
        $expertPresentAccountParams['create_time'] = time();
        $this->_dalUserExpertPresentAccount->newExpertPresentAccount($expertPresentAccountParams);
        //更新用户表用户身份
        $userModel = new UserModel();
        $userUpdate['identity'] = 3;
        $userModel->updateUser($params['user_id'], $userUpdate);
    }

    /**
     * 更新专家申请信息
     * @param $expertId
     * @param $params
     */
    public function updateExpertApply($expertId, $params) {
        $expertParams['phone'] = StringHandler::newInstance()->stringExecute($params['phone']);
        $expertParams['expert_name'] = StringHandler::newInstance()->stringExecute($params['expert_name']);
        $expertParams['real_name'] = StringHandler::newInstance()->stringExecute($params['real_name']);
        $expertParams['idcard_number'] = StringHandler::newInstance()->stringExecute($params['idcard_number']);
        $expertParams['headimgurl'] = StringHandler::newInstance()->stringExecute($params['headimgurl']);
        $expertParams['modify_time'] = time();
        $expertParams['expert_status'] = 0;
        //更新主表
        $this->updateExpert($expertId, $expertParams);

        //更新提款账号表
        $expertPresentAccountModel = new ExpertPresentAccountModel();
        $aliPayAccountParams = [];
        $aliPayAccountParams['account'] = StringHandler::newInstance()->stringExecute($params['alipay_number']);
        $aliPayAccountParams['bank'] = "支付宝";
        $aliPayAccountParams['is_default'] = 0;
        $aliPayAccountParams['account_status'] = 1;
        $aliPayAccountParams['create_time'] = time();
        $expertPresentAccountModel->updateExpertPresentAccount($expertId, 1, $aliPayAccountParams);

        $brankAccountParams = [];
        $brankAccountParams['account'] = StringHandler::newInstance()->stringExecute($params['bank_number']);
        $brankAccountParams['bank'] = StringHandler::newInstance()->stringExecute($params['bank']);
        $brankAccountParams['is_default'] = 0;
        $brankAccountParams['account_status'] = 1;
        $brankAccountParams['create_time'] = time();
        $expertPresentAccountModel->updateExpertPresentAccount($expertId, 2, $brankAccountParams);
    }

    /**
     * 更新专家信息
     * @param $expertId
     * @param $data
     */
    public function updateExpert($expertId, $data) {
        $data['modify_time'] = time();
        $this->_dalUserExpert->updateExpert($expertId, $data);

        $redisManage = new RedisKeyManageModel('resource');
        $redisManage->delExpertKey($expertId);

        $redisManage = new RedisKeyManageModel('expert');
        return $redisManage->delExpertKey($expertId);
    }

    /**
     * 获取专家信息
     * @param $expertId
     * @return bool|mixed|null|string
     */
    public function getExpertInfo($expertId) {
        $redisModel = new RedisModel('expert');
        $redisKey = EXPERT_INFO . $expertId;
        $expectInfo = $redisModel->redisGet($redisKey, true);
        if (empty($expectInfo)) {
            $expectInfo = $this->_dalUserExpert->getExpertInfo($expertId);
            $expectInfo['tag'] = empty($expectInfo['tag']) ? [] : explode(',', $expectInfo['tag']);

            $redisModel->redisSet($redisKey, $expectInfo);
        }
        $redisKey2=EXPERT_L_RED.$expertId;

        $lately_red=$redisModel->redisGet($redisKey2, true);//近几中几
        if(!$lately_red){
            $lately_red=[];
            //$lately_red=$this->getExpertMaximum($expertId,$expectInfo['platform']);//统计近几中几
        }
        $expectInfo['lately_red']=$lately_red;
        //$expectInfo['max_red_num']=$this->maxRedNum($expertId,$expectInfo['platform']);//连红

        return $expectInfo;
    }


    /**
     * 根据手机号获取专家信息
     * @param $phone
     * @return mixed
     */
    public function getExpertInfoByPhone($phone){
        return $this->_dalUserExpert->getExpertInfoByPhone($phone);
    }


    /**
     * 获取推荐专家TOP
     * @return array|bool|mixed|null|string
     */
    public function getExpertRecommendTop($limit, $platform = 1, $is_new = 0) {
        $redisModel = new RedisModel('expert');
        $redisKey = EXPERT_RECOMMOND_TOP. $platform;
        $RecommendTop = $redisModel->redisGet($redisKey, true);
        if (empty($RecommodTop) || (!empty($RecommodTop) && !isset($RecommodTop[0]['max_bet_record']))) {
            $RecommendTop = $this->getExpertRecommendTopFromStable($limit, $platform, $is_new);
            $redisModel->redisSet($redisKey, $RecommendTop);
        }
        if (!empty($RecommendTop)) {
            $expertExtraModel = new ExpertExtraModel();
            foreach ($RecommendTop as $key => $val) {
                //获取专家信息
                $expertId = $val['expert_id'];
                $expertInfo = $this->getExpertInfo($expertId);
                $RecommendTop[$key]['expert_id'] = $expertId;
                $RecommendTop[$key]['expert_name'] = $expertInfo['expert_name'];
                $RecommendTop[$key]['headimgurl'] = $expertInfo['headimgurl'];
                $expertExtraInfo = $expertExtraModel->getExpertExtraInfo($expertId);
                $RecommendTop[$key]['continuity_red_num'] = intval($expertExtraInfo['red_num']);
            }
        }
        return $RecommendTop;
    }
    private function getExpertRecommendTopFromStable($limit, $platform = 1, $is_new = 0) {
        $dalUserExpert = new DALUserExpert($this->_appSetting);
        $recommendTop = $dalUserExpert->getExpertRecommendTop($limit, $platform);
        if ($recommendTop) {
            $extraList = [];
            $expert_ids = array_column($recommendTop, 'expert_id');
            $dalUserExpertExtra = new DALUserExpertExtra($this->_appSetting);
            $expertsExtra = $dalUserExpertExtra->listsExtra('expert_id in (' . implode(',', $expert_ids) . ')', 'expert_id, max_bet_record, max_bet_record_v2');
            foreach ($expertsExtra as &$extra) {
                $extraList[$extra['expert_id']] = $extra;
            }
            foreach ($recommendTop as &$expert) {
                if($is_new) {
                    isset($extraList[$expert['expert_id']]) && isset($extraList[$expert['expert_id']]['max_bet_record_v2']) && $expert['max_bet_record'] = $extraList[$expert['expert_id']]['max_bet_record_v2'];
                } else {
                    isset($extraList[$expert['expert_id']]) && isset($extraList[$expert['expert_id']]['max_bet_record']) && $expert['max_bet_record'] = $extraList[$expert['expert_id']]['max_bet_record'];
                }
            }
        }
        return $recommendTop;
    }

    /**
     * 获取专家列表
     * @param int $start
     * @param int $page
     * @param int $size
     * @param array $removeExpertIds
     * @return mixed
     */
    public function getExpertList($start = 0, $page = 1, $size = 0, $removeExpertIds = [], $orderBy, $platform = 0) {
        $redisModel = new RedisModel('expert');
        $redisKey = EXPERT_LIST . $platform . $orderBy;

        $expertList = [];
        $needExpertNum = 0;
        $resourceModel = new ResourceModel();
        $betRecordModel = new BetRecordModel();
        while (true) {
            $expertIdArr = $redisModel->redisZRangeByScore($redisKey, $start, $start);
            if (empty($expertIdArr)) {
                $dalUserExpert = new DALUserExpert($this->_appSetting);
                $expertId = $dalUserExpert->getExpertList($start, 1, $orderBy, $platform);
                if (!$expertId) {
                    break;
                }
                $redisModel->redisZAdd($redisKey, $start, $expertId);
            } else {
                $expertId = $expertIdArr[0];
            }
            if ($needExpertNum == $size) {
                break;
            }

            if (!in_array($expertId, $removeExpertIds)) {
                $expertInfo = $this->getExpertInfo($expertId);
                unset($expertInfo['phone']);
                unset($expertInfo['idcard_number']);
                unset($expertInfo['real_name']);
                $expertInfo['resource_num'] = $resourceModel->getResourceTotalByExpertId($expertId, 0);
                $expertInfo['combat_gains_ten'] = $betRecordModel->nearTenScore($expertId);
                $expertInfo['number'] = $start;
                $expertList[] = $expertInfo;
                $needExpertNum++;
            }
            $start++;
        }
        return $expertList;
    }



    /*
     *命中率大于等于70 的热门专家--新增
     */

    public function hotExpertList($userId, $orderBy = 1, $count, $page = 4, $limit = 0, $platform = 2){

//        $orderBy=1;
//        $page=1;
//        $limit=200;
        $redisKey =EXPERT_HOT_1 . date('Y-m-d').'';

        $redisModel = new RedisModel('expert');
        $redisKeyIsExists = $redisModel->redisKeys($redisKey);
        $redisManageModel = new RedisManageModel('expert');

        $expertIds = $redisManageModel->getList($redisKey, $orderBy, $page, $limit);
        $expertIds=false;
        //获取 命中率大于等于70得专家
        if($expertIds==false){
            $lists=$this->_dalUserExpert->hotList(70,$platform);

//            $lists=$this->_dalUserExpert->hotgetMyExpertListist(70);
            $expertIds = array_column($lists, 'expert_id');
            $redisManageModel->setList($redisKey, $orderBy, $page, $limit, $expertIds);
        }
        $expertIdArr=[];

        if($expertIds&&count($expertIds)>=$count){
            $expertId_index=array_rand($expertIds,$count);
            foreach($expertId_index as $v){
                $expertIdArr[]=$expertIds[$v];
            }
        }else{
            //热门专家数量小于$page 不用随机
            $expertIdArr=$expertIds;
        }

        $lists = [];

        //$userSubscribeModel = new UserSubscribeModel();
        //专家列表信息
        foreach ($expertIdArr as $expertId) {
            $lists[] = $this->expertInfo($userId,$expertId);
        }
        return $lists;
    }

    //专家详细信息--新增
    public function expertInfo($userId,$expertId){
        $expertExtraModel = new ExpertExtraModel();
        $userFollowModel = new UserFollowModel();
        $expertBase = $this->getExpertInfo($expertId);
        $expertExtra = $expertExtraModel->getExpertExtraInfo($expertId);
        $isFollowExpert = $isSubscribeExpert = 0;
        if($userId){
            //检查用户是否关注专家
            $isFollowExpert = (int)$userFollowModel->checkFollowExpertStatus($userId, $expertId);
        }
        $maxBetRecord = $expertExtra['max_bet_record_v2'];
        $info=array(
            'expert_id' => $expertBase['expert_id'],
            'expert_name' => $expertBase['expert_name'],
            'headimgurl' => $expertBase['headimgurl'],
            'identity_desc' => $expertBase['identity_desc'],
            'tag' => $expertBase['tag'],
            'profit_rate' => $expertExtra['profit_rate'],
            'profit_all' => $expertExtra['profit_all'],
            'publish_resource_num' => $expertExtra['publish_resource_num'],
            'max_bet_record' => $maxBetRecord,
            'max_red_num' => $expertExtra['max_red_num'],
            'is_follow_expert' => $isFollowExpert,
            'is_subscribe_expert' => $isSubscribeExpert,
        );
        return $info;
    }











    /**
     * 全部专家列表
     * @param string $condition
     * @param int $orderBy
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function allExpertList($userId, $orderBy = 1, $day = 3, $page = 1, $limit = 0, $platform = 0) {
        $start = ($page - 1) * $limit;
        $redisModel = new RedisModel('expert');
        $redisManageModel = new RedisManageModel('expert');
        $expertIds = [];
        if ($orderBy == 3) { // 命中率
            switch ($day) {
                case '3':
                    $redisKey = EXPERT_BET_3 . date('Y-m-d'). ':' .$platform;
                    break;
                case '7':
                    $redisKey = EXPERT_BET_7 . date('Y-m-d'). ':' .$platform;
                    break;
                case '30':
                    $redisKey = EXPERT_BET_30 . date('Y-m-d'). ':' .$platform;
                    break;
                default:
                    return false;
            }
            $redisKeyIsExists = $redisModel->redisKeys($redisKey);
            if (empty($redisKeyIsExists)) {
                $this->dayExpertBetRecord();
            }
            $expertIds = $redisManageModel->getListBySort($redisKey, $start, $limit);
        } else {
            $redisKey = EXPERT_ALL_LIST;
            $expertIds = $redisManageModel->getList($redisKey, $orderBy, $page, $limit);
            if ($expertIds === false) {
                $order = '';
                $condition = "`expert_status` = 1";
                if($platform > 0) {
                    $condition .= " AND `platform` in (0, $platform)";
                } else {
                    $condition .= " AND `platform` = $platform";
                }
                switch ($orderBy) {
                    case 2:  // 盈利率
                        $order .= 'profit_rate desc, expert_id asc';
                        $expertIds = $this->_dalUserExpert->lists($condition, 'hl_user_expert.expert_id', $order, ['join' => [['hl_user_expert_extra', 'hl_user_expert_extra.expert_id = hl_user_expert.expert_id']]], $start, $limit);
                        break;
                    default:  // 1 => 默认排序
                        if ($limit < 10) {
                            return false;
                        }
                        $orderBy = "1";

                        $search_fileds = 'is_placement, expert_id';
                        if($platform == 1) {
                            $order .= 'is_placement desc, expert_id asc';
                        }else if($platform == 2) {
                            $search_fileds = 'is_wx_placement as is_placement, expert_id';
                            $order .= 'is_wx_placement desc, expert_id asc';
                        }
                        $expertIds = $this->_dalUserExpert->lists($condition, $search_fileds, $order, [], $start, $limit);
                        $lasttopcount = 0;
                        for ($i = 9; $i >= 0; $i--) {
                            if ($lasttopcount == 0 && $expertIds[$i]['is_placement'] != 0) {
                                $lasttopcount = $i;
                            }
                            if ($i <= $lasttopcount && $i > intval($lasttopcount / 2)) {
                                $dy = $lasttopcount - $i;
                                $tmp = $expertIds[$i];
                                $expertIds[$i] = $expertIds[$dy];
                                $expertIds[$dy] = $tmp;
                            }
                        }
                }
                $expertIds = array_column($expertIds, 'expert_id');
                $redisManageModel->setList($redisKey, $orderBy, $page, $limit, $expertIds);
            }
        }
        $lists = [];
        $expertExtraModel = new ExpertExtraModel();
        $userFollowModel = new UserFollowModel();
        //$userSubscribeModel = new UserSubscribeModel();
        foreach ($expertIds as $expertId) {
            $expertBase = $this->getExpertInfo($expertId);
            $expertExtra = $expertExtraModel->getExpertExtraInfo($expertId);
            $isFollowExpert = $isSubscribeExpert = 0;
            if($userId){
                //检查用户是否关注专家
                $isFollowExpert = (int)$userFollowModel->checkFollowExpertStatus($userId, $expertId);
                //检查用户是否订阅
                //$isSubscribeExpert = (int)$userSubscribeModel->checkUserSubscribeExpert($userId, $expertId);
            }
            if ($orderBy == 3) {
                $redisModel = new RedisModel('expert');
                $maxBetRecord = $redisModel->redisZScore($redisKey, $expertId);
                if (empty($maxBetRecord)){
                    $maxBetRecord = 0;
                } else {
                    $maxBetRecord = intval($maxBetRecord);
                }
            } else {
                $maxBetRecord = $expertExtra['max_bet_record'];
            }
            $lists[] = array(
                'expert_id' => $expertBase['expert_id'],
                'expert_name' => $expertBase['expert_name'],
                'headimgurl' => $expertBase['headimgurl'],
                'identity_desc' => $expertBase['identity_desc'],
                'tag' => $expertBase['tag'],
                'profit_rate' => $expertExtra['profit_rate'],
                'publish_resource_num' => $expertExtra['publish_resource_num'],
                'max_bet_record' => $maxBetRecord,
                'max_red_num' => $expertExtra['max_red_num'],
                'is_follow_expert' => $isFollowExpert,
                'is_subscribe_expert' => $isSubscribeExpert,
            );
        }
        return $lists;
    }

    /**
     * 统计全部专家命中率
     */
    public function dayExpertBetRecord() {
        // 获取专家列表
        $expertList = $this->expertList();

        foreach ($expertList as $expert) {
            $expertId = $expert['expert_id'];
            $expertInfo = $this->getExpertInfo($expertId);
           /* if ($expertInfo['platform'] == 0) {
                $platform = 2;
            } else {
                $platform = $expertInfo['platform'];
            }*/
            $platform = $expertInfo['platform'];
            $betRecord = $this->calBetRecord($expertId, $platform);

            $upExtraData['max_bet_record'] = $betRecord;
            // 更新命中率
            /*
                $betRecord = $this->updateBetRecord($expert['expert_id']);
                $upExtraData['max_bet_record'] = $betRecord['max_bet_record'];
            $this->_expertExtraModel->updateExtra($expert['expert_id'], $upExtraData);
            */

        }
    }

    /*
        * 只用来 单独跑 9、11场数据
        */
    public function dayExpertBetRecordV2() {
        // 获取专家列表
        $expertList = $this->expertList();
        foreach ($expertList as $expert) {
            $expertId = $expert['expert_id'];
            $expertInfo = $this->getExpertInfo($expertId);
            if ($expertInfo['platform'] == 0) {
                $platform = 2;
            } else {

                $platform = $expertInfo['platform'];
            }
            $this->calBetRecordV2($expertId, $platform);
        }
    }


    /**
     * 更新专家统计信息
     */
    public function updateStatInfo($expertId) {
        //$expertModel = new ExpertModel();
        $expertInfo = $this->getExpertInfo($expertId);
        if (empty($expertInfo['expert_id'])) {
            return false;
        }
        // 更新网站统计相关缓存
        $redisModel = new RedisModel('betRecord');
        $betRecordDateInfo = BETRECORD_DATE_INFO;
        $betRecordDateInfoKeys = $redisModel->redisKeys($betRecordDateInfo);
        $redisModel->redisDel($betRecordDateInfoKeys);
        $betRecordDateInfoDesc = BETRECORD_DATE_INFO_DESC;
        $betRecordDateInfoDescKeys = $redisModel->redisKeys($betRecordDateInfoDesc);
        $redisModel->redisDel($betRecordDateInfoDescKeys);

        // 更新盈利率
        $profitInfo = $this->resetProfitRate($expertId);
        if ($profitInfo) {
            $upExtraData['profit_rate'] = $profitInfo['profitRate'];
            $upExtraData['profit_all'] = $profitInfo['profitAll'];
            $upExtraData['profit_resource_num'] = $profitInfo['profitResourceNum'];
        }

        // 重新计算近30日的红黑单
        $this->_betRecordModel->resetBetRecordMonthStatByExpertId($expertId);
        //将30日命中率更改成了近11场命中率
        if ($expertInfo['platform'] == 0) {
            $platform = 2;
        } else {
            $platform = $expertInfo['platform'];
        }
        $betRecord = $this->calBetRecord($expertId, $platform);
        //$betRecord = $this->updateBetRecord($expertId);
        $upExtraData['max_bet_record'] = $betRecord;

        //重新计算连红和最大连红
        $platform_key = ($platform == 1) ? 'bd_display' : 'wx_display';
        $condition = ['expert_id' => $expertId, $platform_key => 1, 'resource_status' => 1];
        if ($expertInfo['platform'] == 0) {
                unset($condition[$platform_key]);
        }
        $orderBy = ['release_time' => 'asc'];
        $resourceList = $this->_dalResource->getAllResources($condition, [], 0, 0, $orderBy);
        $red_num = 0;
        $max_red_num = 0;
        foreach($resourceList as $resource) {
            $resourceScheduleList = $this->_resourceModel->getResourceScheduleList($resource['resource_id']);
            $all_bet_status = $this->_resourceModel->getBetStatus($resourceScheduleList);
            if($all_bet_status == 1) {
                $red_num += 1;
                if($max_red_num < $red_num){
                    $max_red_num = $red_num;
                }
            } else {
                $red_num = 0;
            }
        }
        $upExtraData['red_num'] = $red_num;
        $upExtraData['max_red_num'] = $max_red_num;

        $this->_expertExtraModel->updateExtra($expertId, $upExtraData);
        return true;
    }


    /*
	*重置所有专家盈利率
	*
	*/

    public function resetAllExpert(){
        //获取专家列表
        $expertList = $this->expertList();
        foreach ($expertList as $expert) {
            $expertId = $expert['expert_id'];
            if ($expert['platform'] == 0) {
                $platform = 2;
            } else {
                $platform = $expert['platform'];
            }
            // 更新盈利率
            $profitInfo = $this->resetProfitRate($expertId,$platform);

            if ($profitInfo) {
                #$upExtraData['profit_rate'] = $profitInfo['profitRate'];
                //$upExtraData['profit_rate'] = $profitInfo['profitAll'];
                $upExtraData['profit_all'] = $profitInfo['profitAll'];
                $upExtraData['profit_resource_num'] = $profitInfo['profitResourceNum'];
                $this->_expertExtraModel->updateExtra($expertId, $upExtraData);
            }

        }
    }



    /**
     * 重制专家盈利率
     * @param $expertId
     * @return array
     */
    public function resetProfitRate($expertId,$platform=2) {
        // 盈利率
        $profitAll = 0;
        $profitRate = 0;
        $resourceNum = 0;
        /*
        $resourceList = $this->_resourceModel->lists('expert_id = ' . $expertId . ' AND resource_status = 1 AND bet_status <> 0', '', '', ['join' => [['hl_resource_extra', 'hl_resource_extra.resource_id = hl_resource.resource_id']]]);
        $condition = ['expert_id' => $expertId, 'resource_status' => 1, 'hl_resource_schedule.bet_status' => ['<>', 0]];
        $fields = ['hl_resource_schedule.*'];
        $join = ['LEFT JOIN', ['hl_resource_schedule', 'resource_id', 'resource_id']];
        $scheduleList = $this->_dalResource->getResourceSchedules($condition, $fields, 0, 0, [], $join);
        */
        $platform_field = ($platform == 1) ? 'bd_display' : 'wx_display';
        //计算上一周内的盈利率
        $week_start=mktime(0,0,0,date('m'),date('d')-date('w')+1-7,date('Y'));
        $week_end=mktime(23,59,59,date('m'),date('d')-date('w')+7-7,date('Y'));
        $resourceList = $this->_resourceModel->lists($platform_field.'=1 AND expert_id = ' . $expertId . ' AND resource_status = 1 AND bet_status <> 0'.' AND release_time>='.$week_start.' AND release_time<='.$week_end, '', '', ['join' => [['hl_resource_extra', 'hl_resource_extra.resource_id = hl_resource.resource_id']]]);

        //获取所有方案赛事
        $condition ="a.expert_id=$expertId AND a.release_time>=$week_start AND a.release_time<=$week_end AND a.resource_status=1 AND a.$platform_field=1   AND (hl_resource_schedule.bet_status<>0 or e.bet_status <> 0)";
        $fields ='hl_resource_schedule.*';
        $scheduleList = $this->_dalResource->getResourceSchedulesV2($condition, $fields);

        $schedule_list = [];
        $soccerModel = new SoccerModel();
        foreach($scheduleList as $k => $schedule) {
            if (!empty($schedule['manual_bet_status'])) {
                $scheduleList[$k]['bet_status'] = $schedule['manual_bet_status'];
            }
            $schedule = $scheduleList[$k];
            $recommend_list = explode(',', $schedule['recommend']);
            $main_recommend = $recommend_list[0];
            $extra_recommend = count($recommend_list) == 2 ? $recommend_list[1] : '';
            $odds = $schedule[$main_recommend];
            if ($schedule['bet_status'] == 4 || $schedule['bet_status'] == 6) {
                $odds = $schedule[$extra_recommend];
            }
            if ($schedule['lottery_type'] == 2) {
                $lotteryInfo = $soccerModel->findLotteryById($schedule['lottery_id']);
                $odds = ($schedule['bet_status'] == 4 || $schedule['bet_status'] == 6) ? $lotteryInfo[$extra_recommend] : $lotteryInfo[$main_recommend];
            }

            $schedule_list[] = ['bet_status' => $schedule['bet_status'], 'odds' => $odds];
        }

        $resourceList = array_merge($resourceList, $schedule_list);

        foreach ($resourceList as $source) {
            if (intval($source['odds']) >= 1) {
                switch ($source['bet_status']) {
                    case 1:  // 红单
                        $profit = floatval($source['odds']) - 1;  // 收益 - 本金
                        break;
                    case 2:  // 走单
                        $profit = 0;
                        break;
                    case 3:  // 黑单
                        $profit = -1;
                        break;
                    case 4:
                        $profit = floatval($source['odds']) - 1;  //副推红单
                        break;
                    case 5:
                    case 6:
                        $profit = (floatval($source['odds']) - 1)/2;   //主推，副推半红单
                        break;
                    case 7:
                        $profit = -0.5;   //半黑单
                        break;

                }

                if (isset($profit)) {
                    $profitAll += $profit;
                    $resourceNum++;
                }
            }
        }

        if ($resourceNum > 0) {
            $profitAll = $profitAll * 100;
            $profitRate = ceil($profitAll / $resourceNum);
        }

        return ['profitAll' => $profitAll, 'profitRate' => $profitRate, 'profitResourceNum' => $resourceNum];
    }

    /*
     * 2019/11/29 备份
     */
    public function resetProfitRate_bak($expertId) {
        // 盈利率
        $profitAll = 0;
        $profitRate = 0;
        $resourceNum = 0;

        $resourceList = $this->_resourceModel->lists('expert_id = ' . $expertId . ' AND resource_status = 1 AND bet_status <> 0', '', '', ['join' => [['hl_resource_extra', 'hl_resource_extra.resource_id = hl_resource.resource_id']]]);
        $condition = ['expert_id' => $expertId, 'resource_status' => 1, 'hl_resource_schedule.bet_status' => ['<>', 0]];
        $fields = ['hl_resource_schedule.*'];
        $join = ['LEFT JOIN', ['hl_resource_schedule', 'resource_id', 'resource_id']];
        $scheduleList = $this->_dalResource->getResourceSchedules($condition, $fields, 0, 0, [], $join);



        $schedule_list = [];
        $soccerModel = new SoccerModel();
        foreach($scheduleList as $schedule) {
            $recommend_list = explode(',', $schedule['recommend']);
            $main_recommend = $recommend_list[0];
            $extra_recommend = count($recommend_list) == 2 ? $recommend_list[1] : '';

            $odds = $schedule[$main_recommend];
            if ($schedule['bet_status'] == 4 || $schedule['bet_status'] == 6) {
                $odds = $schedule[$extra_recommend];
            }

            if ($schedule['lottery_type'] == 2) {
                $lotteryInfo = $soccerModel->findLotteryById($schedule['lottery_id']);
                $odds = ($schedule['bet_status'] == 4 || $schedule['bet_status'] == 6) ? $lotteryInfo[$extra_recommend] : $lotteryInfo[$main_recommend];
            }

            $schedule_list[] = ['bet_status' => $schedule['bet_status'], 'odds' => $odds];
        }

        $resourceList = array_merge($resourceList, $schedule_list);
        foreach ($resourceList as $source) {
            if (intval($source['odds']) >= 1) {
                switch ($source['bet_status']) {
                    case 1:  // 红单
                        $profit = floatval($source['odds']) - 1;  // 收益 - 本金
                        break;
                    case 2:  // 走单
                        $profit = 0;
                        break;
                    case 3:  // 黑单
                        $profit = -1;
                        break;
                    case 4:
                        $profit = floatval($source['odds']) - 1;  //副推红单
                        break;
                    case 5:
                    case 6:
                        $profit = (floatval($source['odds']) - 1)/2;   //主推，副推半红单
                        break;
                    case 7:
                        $profit = -0.5;   //半黑单
                        break;

                }
                if (isset($profit)) {
                    $profitAll += $profit;
                    $resourceNum++;
                }
            }
        }

        if ($resourceNum > 0) {
            $profitAll = $profitAll * 100;
            $profitRate = ceil($profitAll / $resourceNum);
        }
        return ['profitAll' => $profitAll, 'profitRate' => $profitRate, 'profitResourceNum' => $resourceNum];
    }

    /**
     * 更新命中率
     * @param $expertId
     * @return array
     */
    public function updateBetRecord($expertId) {
        $maxBetRecord = 0;

        //获取专家近30日战绩
        $betRecordList = $this->_betRecordModel->getBetRecordMonthStatByExpertId($expertId);

        //计算1,3,5,7,15,30日胜率
        $dataPercents = $this->_betRecordModel->statisticsWinningV2($betRecordList);

        foreach($dataPercents as $k => $v){
            if($v['winning'] > $maxBetRecord){
                $maxBetRecord = $v['winning'];
            }
        }

        $timeOut = 2 * 24 * 3600; //过期时间两天
        $today = date('Y-m-d');
        $maxPos = 11;
        $maxId = pow(10, $maxPos);

        $expertInfo = $this->getExpertInfo($expertId);
        $expertExtraModel = new ExpertExtraModel();
        $expertExtraInfo = $expertExtraModel->getExpertExtraInfo($expertId);

        if ($expertInfo['platform'] == 0) {
            $platform = [1, 2];
        } else {
            $platform = [$expertInfo['platform']];
        }

        $hasResource = empty($expertExtraInfo['publish_resource_num']) ? 0 : 1;

        foreach ($platform as $item) {
            // 三天命中率集合
            $this->_redisExpertModel->redisZAdd(EXPERT_BET_3 . $today . ':' . $item, $dataPercents[1]['winning'] . '.' . $hasResource . ($maxId - $expertId), $expertId, $timeOut);
            // 七天命中率集合
            $this->_redisExpertModel->redisZAdd(EXPERT_BET_7 . $today . ':' . $item, $dataPercents[3]['winning'] . '.' . $hasResource . ($maxId - $expertId), $expertId, $timeOut);
            // 三十天命中率集合
            $this->_redisExpertModel->redisZAdd(EXPERT_BET_30 . $today . ':' . $item,$dataPercents[5]['winning'] . '.' . $hasResource . ($maxId - $expertId), $expertId, $timeOut);
        }

        return ['max_bet_record' => $maxBetRecord];
    }

    /**
     * 2019-11-20 20:54
     * 专家列表-新增
     * @return array|bool
     */
    public function getNewExpertList($platform=0) {
        $dalUserExpert = new DALUserExpert($this->_appSetting);
        $expertList = $dalUserExpert->getExpertList(0, 0, 1, $platform );
        foreach ($expertList as $key => $expertId) {
            $expertInfo = $this->getExpertInfo($expertId['expert_id']);
            $expertList[$key]['expert_name'] = $expertInfo['expert_name'];
            $expertList[$key]['platform'] = $expertInfo['platform'];
        }
        return $expertList;
    }



    /**
     * 全部专家列表(后台)
     * @return array|bool
     */
    public function expertList() {
        $dalUserExpert = new DALUserExpert($this->_appSetting);
        $expertList = $dalUserExpert->expertList();
        foreach ($expertList as $key => $expertId) {
            $expertInfo = $this->getExpertInfo($expertId['expert_id']);
            $expertList[$key]['expert_name'] = $expertInfo['expert_name'];
            $expertList[$key]['platform'] = $expertInfo['platform'];
        }
        return $expertList;
    }

    /**
     * 管理后台专家管理，专家列表（后台）
     * @param $query
     * @param $page
     * @param $size
     * @return array
     */
    public function getManageExpertList($query, $page, $size) {
        $betRecordModel = new BetRecordModel();
        $expertExtraModel = new ExpertExtraModel();
        $expertSubscribeModel = new ExpertSubscribeModel();
        $dalUserExpert = new DALUserExpert($this->_appSetting);
        $list = $dalUserExpert->getExpertListConsole($query, $page, $size);
        $resultExpertList = [];
        foreach ($list as $expert) {
            $expertId = $expert['expert_id'];
            $expertExtraInfo = $expertExtraModel->getExpertExtraInfo($expertId);
            $executeExpertInfo = [];
            $executeExpertInfo['expert_id'] = $expertId;
            $executeExpertInfo['platform'] = $expert['platform'];
            $executeExpertInfo['expert_name'] = $expert['expert_name'];
            $executeExpertInfo['real_name'] = $expert['real_name'];
            $executeExpertInfo['headimgurl'] =  $expert['headimgurl'];
            $executeExpertInfo['push_resource_time'] = $expert['push_resource_time'];
            $executeExpertInfo['subscribe_num'] = $expertExtraInfo['subscribe_num'];
            $executeExpertInfo['follow_num'] = $expertExtraInfo['follow_num'];
            $executeExpertInfo['combat_gains_ten'] = $betRecordModel->nearTenScore($expertId);
            $expertSubscribeInfo = $expertSubscribeModel->getExpertSubscribeByDays($expertId, 30);
            $executeExpertInfo['subscribe_price'] = $expertSubscribeInfo['subscribe_price'];
            $executeExpertInfo['expert_status'] = $expert['expert_status'];
            $executeExpertInfo['is_recommend'] = $expert['is_recommend'];
            $executeExpertInfo['is_placement'] = $expert['is_placement'];
            $executeExpertInfo['is_wx_recommend'] = $expert['is_wx_recommend'];
            $executeExpertInfo['is_wx_placement'] = $expert['is_wx_placement'];
            $executeExpertInfo['expert_type'] = $expert['expert_type'];
            $resultExpertList[] = $executeExpertInfo;
        }

        $result['list'] = $resultExpertList;
        $result['total'] = $this->_dalUserExpert->getExpertTotal($query);
        return $result;
    }

    /**
     * 专家排序（后台）
     * @param $expertId
     * @param $type
     * @return string
     */
    public function expertSort($expertId, $type) {
        //上移
        $info = $this->getExpertInfo($expertId);
        $redisManage = new RedisKeyManageModel('expert');
        $sort = $info['sort'];
        if ($type == 1) {
            //获取当前卡片id
            $minSort = $this->_dalUserExpert->getMinSort();
            if ($sort == $minSort) {
                return "5004";
            } else {
                $newSortInfo = $this->_dalUserExpert->getLeftOnlineSort($sort);
            }
        } else {
            //下移
            $maxSort = $this->_dalUserExpert->getMaxSort();
            if ($sort == $maxSort) {
                return "5005";
            } else {
                $newSortInfo = $this->_dalUserExpert->getRightOnlineSort($sort);
            }
        }

        $newSort = $newSortInfo['sort'];
        $newSortExpertId = $newSortInfo['expert_id'];
        $this->_dalUserExpert->updateSort($newSort, $sort);
        $this->_dalUserExpert->updateSortById($expertId, $newSort);
        $redisManage->delExpertKey($expertId);
        $redisManage->delExpertKey($newSortExpertId);
        return "200";
    }

    /**
     * 获取推荐位置专家id（后台）
     * @param $type
     * @return mixed
     */
    public function getRecommendExpertId($type, $platform = 1) {
        return $this->_dalUserExpert->getRecommendExpertId($type, $platform);
    }

    /**
     * 获取置顶位置专家id（后台）
     * @param $placement
     * @return mixed
     */
    public function getPlacementExpertId($placement, $platform = 1){
        return $this->_dalUserExpert->getPlacementExpertId($placement, $platform);
    }

    public function lists($condition = "", $fields = "", $order = "", $start = 0, $limit = 0) {
        return $this->_dalUserExpert->lists($condition, $fields, $order, [], $start, $limit);
    }

    //获取专家列表
    public function newExpertListV2($condition = array(), $fields = array(), $offset = 0, $limit = 0, $orderBy = array()) {
        return $this->_dalUserExpert->newExpertList($condition, $fields, $offset, $limit, $orderBy);
    }

    public function calBetRecord($expertId, $platform = 1) {
        $timeOut = 2 * 24 * 3600; //过期时间两天
        //$index_group = [3, 5, 7, 9, 11];
        $index_group = [2, 3, 5, 7,11];

        $expertInfo = $this->getExpertInfo($expertId);
        $expertExtraModel = new ExpertExtraModel();
        $expertExtraInfo = $expertExtraModel->getExpertExtraInfo($expertId);

        $maxBetRecord = 0;
        $betRecordList = $this->_dalResource->getExpertScheduleBetRecordList($expertId, 11, $platform);     //获取近11场已结束比赛的红黑状态
        //兼容旧数据
        //如果不够11场比赛，则获取（11 - count($betRecordList)）老数据按料判单的数据
        //if (count($betRecordList) < 11) {
        //    $old_betRecordList = $this->_dalResource->getExpertBetRecordList($expertId, 11 - count($betRecordList), $platform);

        //    //状态判定
        //    $resourceModel = new ResourceModel();
        //    foreach($old_betRecordList as &$val){
        //        $resourceExtraInfo = $resourceModel->getResourceExtraInfo($val['resource_id']);
        //        //通过比赛判定红黑单
        //        $resourceScheduleList = $resourceModel->getResourceScheduleList($val['resource_id']);
        //        $bet_status = $resourceModel->getBetStatus($resourceScheduleList);
        //        if(empty($resourceScheduleList)){
        //            $bet_status=$resourceExtraInfo['bet_status'];
        //        }
        //        //如果有手动判的 已手动判的为准
        //        if ($resourceExtraInfo['bet_status']) {
        //            $bet_status=$resourceExtraInfo['bet_status'];
        //        }
        //        $val['bet_status']=$bet_status;
        //    }


        //    $betRecordList = array_merge($betRecordList, $old_betRecordList);
        //}





        $betStatus = array_column($betRecordList, 'bet_status');
        $totalBet = count($betStatus);
        $maxBetRecord = 0;
        foreach($index_group as $index) {
            $totalIndex = ($totalBet >= $index) ? $index : $totalBet;
            $all_betRecord = array_slice($betStatus, 0, $index);
            $values_count = array_count_values($all_betRecord);


            $correctNum = (isset($values_count[1])) ? $values_count[1] : 0;
            //增加 次推红，半红
            $secondNum = (isset($values_count[4])) ? $values_count[4] : 0;//次推红
            $halfNum = (isset($values_count[5])) ? $values_count[5] : 0;//半红
            $correctNum+=$secondNum+$halfNum;

            $betRecord = intval(round($correctNum / $totalIndex, 2) * 100);

            /*if(isset($values_count[1])) {
              $betRecord = intval(round($values_count[1] / $totalIndex, 2) * 100);
            }*/
            $betRecordScore = 0;    //区分已发料和未发料，未发过料的排序滞后
            if (!empty($expertExtraInfo['publish_resource_num'])) {
                $betRecordScore = ($betRecord == 0) ? 1 : $betRecord * 10 + 1;
            }

            if($expertInfo['platform'] > 0) {
                $this->_redisExpertModel->redisZAdd(EXPERT_BET_RECORD . $index . ':' . $platform, $betRecordScore, $expertId, $timeOut);
            } else {
                $this->_redisExpertModel->redisZAdd(EXPERT_BET_RECORD . $index . ':1', $betRecordScore, $expertId, $timeOut);
                $this->_redisExpertModel->redisZAdd(EXPERT_BET_RECORD . $index . ':2', $betRecordScore, $expertId, $timeOut);
            }
            //获取最大命中率
            if($betRecord > $maxBetRecord && $index!=11) {
                $maxBetRecord = $betRecord;
            }
        }
        $upExtraData['max_bet_record'] = $upExtraData['max_bet_record_v2'] = $maxBetRecord;
        $this->_expertExtraModel->updateExtra($expertId, $upExtraData);
        return $maxBetRecord;
    }

    public function calBetRecordV2($expertId, $platform = 1) {
        $timeOut = 2 * 24 * 3600; //过期时间两天
        //$index_group = [3, 5, 7, 9, 11];
        $index_group = [9, 11];

        $expertInfo = $this->getExpertInfo($expertId);
        $expertExtraModel = new ExpertExtraModel();
        $expertExtraInfo = $expertExtraModel->getExpertExtraInfo($expertId);

        $maxBetRecord = 0;
        $betRecordList = $this->_dalResource->getExpertScheduleBetRecordList($expertId, 11, $platform);     //获取近11场已结束比赛的红黑状态
        //兼容旧数据
        //如果不够11场比赛，则获取（11 - count($betRecordList)）老数据按料判单的数据
        //if (count($betRecordList) < 11) {
        //    $old_betRecordList = $this->_dalResource->getExpertBetRecordList($expertId, 11 - count($betRecordList), $platform);
        //    $betRecordList = array_merge($betRecordList, $old_betRecordList);
        //}

        ////状态判定
        //$resourceModel = new ResourceModel();
        //foreach($betRecordList as &$val){
        //    $resourceExtraInfo = $resourceModel->getResourceExtraInfo($val['resource_id']);
        //    //通过比赛判定红黑单
        //    $resourceScheduleList = $resourceModel->getResourceScheduleList($val['resource_id']);
        //    $bet_status = $resourceModel->getBetStatus($resourceScheduleList);
        //    if(empty($resourceScheduleList)){
        //        $bet_status=$resourceExtraInfo['bet_status'];
        //    }
        //    //如果有手动判的 已手动判的为准
        //    if ($resourceExtraInfo['bet_status']) {
        //        $bet_status=$resourceExtraInfo['bet_status'];
        //    }
        //    $val['bet_status']=$bet_status;
        //}



        $betStatus = array_column($betRecordList, 'bet_status');
        $totalBet = count($betStatus);
        foreach($index_group as $index) {
            $totalIndex = ($totalBet >= $index) ? $index : $totalBet;
            $all_betRecord = array_slice($betStatus, 0, $index);
            $betRecord = 0;
            $values_count = array_count_values($all_betRecord);
            if(isset($values_count[1])) {
                $betRecord = intval(round($values_count[1] / $totalIndex, 2) * 100);
            }
            $betRecordScore = 0;    //区分已发料和未发料，未发过料的排序滞后
            if (!empty($expertExtraInfo['publish_resource_num'])) {
                $betRecordScore = ($betRecord == 0) ? 1 : $betRecord * 10 + 1;
            }

            if($expertInfo['platform'] > 0) {
                $this->_redisExpertModel->redisZAdd(EXPERT_BET_RECORD . $index . ':' . $platform, $betRecordScore, $expertId, $timeOut);
            } else {
                $this->_redisExpertModel->redisZAdd(EXPERT_BET_RECORD . $index . ':1', $betRecordScore, $expertId, $timeOut);
                $this->_redisExpertModel->redisZAdd(EXPERT_BET_RECORD . $index . ':2', $betRecordScore, $expertId, $timeOut);
            }
            //获取最大命中率
            if($betRecord > $maxBetRecord) {
                $maxBetRecord = $betRecord;
            }
        }
        $upExtraData['max_bet_record'] = $upExtraData['max_bet_record_v2'] = $maxBetRecord;
        //$this->_expertExtraModel->updateExtra($expertId, $upExtraData);
        return $maxBetRecord;
    }








    public function calBetRecordStatList($expertId, $platform = 2) {
        $timeOut = 2 * 24 * 3600; //过期时间两天
        //$index_group = [2, 3, 5, 7, 9, 11];
        $index_group = [2, 3, 5, 7];

        $expertInfo = $this->getExpertInfo($expertId);

        $maxBetRecord = 0;
        $betRecordList = $this->_dalResource->getExpertScheduleBetRecordList($expertId, 11, $platform);
        //$betRecordList = $this->_dalResource->getExpertBetRecordList($expertId, 11, $platform);     //获取近11场已结束料的红黑状态
        //兼容旧数据
        //如果不够11场比赛，则获取（11 - count($betRecordList)）老数据按料判单的数据
        //if (count($betRecordList) < 11) {
        //    $resource_id='';
        //    if($betRecordList){
        //        $resource_idArr = array_column($betRecordList, 'resource_id');
        //        $resource_id=implode(',',$resource_idArr);
        //    }
        //    $old_betRecordList = $this->_dalResource->getExpertBetRecordList($expertId, 11 - count($betRecordList), $platform,$resource_id);
        //    $betRecordList = array_merge($betRecordList, $old_betRecordList);
        //}
        //状态判定
        //$resourceModel = new ResourceModel();
        //foreach($betRecordList as $k => $val){
            //$resourceExtraInfo = $resourceModel->getResourceExtraInfo($val['resource_id']);
            //通过比赛判定红黑单
            //if (empty($val['manual_bet_status'])) {
            //    $resourceScheduleList = $resourceModel->getResourceScheduleList($val['resource_id']);
            //    $bet_status = $resourceModel->getBetStatus($resourceScheduleList);
            //    $betRecordList[$k]['bet_status'] = $bet_status;
            //}
            //if(empty($resourceScheduleList)){
            //    $bet_status=$resourceExtraInfo['bet_status'];
            //}
            ////如果有手动判的 已手动判的为准
            //if ($resourceExtraInfo['bet_status']) {
            //    $bet_status=$resourceExtraInfo['bet_status'];
            //}
        //}

        $betStatus = array_column($betRecordList, 'bet_status');
        $totalBet = count($betStatus);

        $res = [];
        foreach($index_group as $index) {
            $totalIndex = ($totalBet >= $index) ? $index : $totalBet;
            $all_betRecord = array_slice($betStatus, 0, $index);
            $values_count = array_count_values($all_betRecord);

            $correctNum = (isset($values_count[1])) ? $values_count[1] : 0;
            //$balanceNum = (isset($values_count[2])) ? $values_count[2] : 0;
            //$mistakenNum = (isset($values_count[3])) ? $values_count[3] : 0;
            $betRecord = intval(round($correctNum / $totalIndex, 2) * 100);

            $res[] = array(
                'round' => $index,
                'red' => $correctNum,
                'total' => $totalIndex,
                'winning' => $betRecord
            );
        }
        return $res;
    }

    public function calBetRecordStatListV2($expertId, $platform = 2) {
        $timeOut = 2 * 24 * 3600; //过期时间两天
        $index_group = [2, 3, 5, 7];

        $expertInfo = $this->getExpertInfo($expertId);

        $maxBetRecord = 0;
        $betRecordList = $this->_dalResource->getExpertScheduleBetRecordList($expertId, 11, $platform);
        //$betRecordList = $this->_dalResource->getExpertBetRecordList($expertId, 11, $platform);     //获取近11场已结束料的红黑状态
        //兼容旧数据
        //如果不够11场比赛，则获取（11 - count($betRecordList)）老数据按料判单的数据
        //if (count($betRecordList) < 11) {
        //    $resource_id='';
        //    if($betRecordList){
        //        $resource_idArr = array_column($betRecordList, 'resource_id');
        //        $resource_id=implode(',',$resource_idArr);
        //    }
        //    $old_betRecordList = $this->_dalResource->getExpertBetRecordList($expertId, 11 - count($betRecordList), $platform,$resource_id);

        //    //状态判定
        //    $resourceModel = new ResourceModel();
        //    foreach($old_betRecordList as &$val){
        //        $resourceExtraInfo = $resourceModel->getResourceExtraInfo($val['resource_id']);
        //        $val['bet_status']=$resourceExtraInfo['bet_status'];
        //    }

        //    $betRecordList = array_merge($betRecordList, $old_betRecordList);
        //}



        $betStatus = array_column($betRecordList, 'bet_status');
        $totalBet = count($betStatus);

        $res = [];
        foreach($index_group as $index) {
            $totalIndex = ($totalBet >= $index) ? $index : $totalBet;
            $all_betRecord = array_slice($betStatus, 0, $index);
            $values_count = array_count_values($all_betRecord);




            $correctNum = (isset($values_count[1])) ? $values_count[1] : 0;//主推红

            //增加 次推红，半红
            $secondNum = (isset($values_count[4])) ? $values_count[4] : 0;//次推红
            $halfNum = (isset($values_count[5])) ? $values_count[5] : 0;//半红
            $correctNum+=$secondNum+$halfNum;

            //$balanceNum = (isset($values_count[2])) ? $values_count[2] : 0;
            //$mistakenNum = (isset($values_count[3])) ? $values_count[3] : 0;
            $betRecord = intval(round($correctNum / $totalIndex, 2) * 100);

            $res[] = array(
                'round' => $index,
                'red' => $correctNum,
                'total' => $totalIndex,
                'winning' => $betRecord
            );
        }

        return $res;
    }
    public function expertInfoV2($expertId) {
        $expectInfo = $this->_dalUserExpert->getExpertInfoV2($expertId);
        $expectInfo['tag'] = empty($expectInfo['tag']) ? [] : explode(',', $expectInfo['tag']);
        $redisModel = new RedisModel('expert');
        $redisKey2=EXPERT_L_RED.$expertId;
        $lately_red=$redisModel->redisGet($redisKey2, true);//近几中几
        if(!$lately_red){
            $lately_red=[];
            //$lately_red=$this->getExpertMaximum($expertId,$expectInfo['platform']);//统计近几中几
        }
        $expectInfo['lately_red']=$lately_red;
        $expectInfo['max_red_num']=$this->maxRedNum($expertId,$expectInfo['platform']);//连红

        return $expectInfo;
    }

    public function listsV2($condition, $search_fileds = array(), $order = array(), $join = array(), $start = 0, $pagesize = 20) {
        return $this->_dalUserExpert->lists($condition, $search_fileds, $order, $join, $start, $pagesize);
    }

    public function getRecommandExpertList($user_id = 0, $start = 0, $page = 1, $size = 5, $removeExpertIds = [], $orderBy = 2, $platform = 0) {
        //$redisModel = new RedisModel('expert');
        //$redisKey = EXPERT_LIST . $platform . $orderBy;

        $expertList = [];
        $needExpertNum = 0;
        $resourceModel = new ResourceModel();
        $betRecordModel = new BetRecordModel();
        $userFollowModel = new UserFollowModel();
        while (true) {
            //$expertIdArr = $redisModel->redisZRangeByScore($redisKey, $start, $start);
            //if (empty($expertIdArr)) {
            $dalUserExpert = new DALUserExpert($this->_appSetting);
            $expertId = $dalUserExpert->getExpertList($start, 1, $orderBy, $platform);
            if (!$expertId) {
                break;
            }
            //    $redisModel->redisZAdd($redisKey, $start, $expertId);
            //} else {
            //    $expertId = $expertIdArr[0];
            //}
            if ($needExpertNum == $size) {
                break;
            }

            if (!in_array($expertId, $removeExpertIds)) {
                $combat_gains_ten = [];
                $combat_gains_ten = $betRecordModel->nearTenScore($expertId, $platform);
                if ($combat_gains_ten['red'] >= 7) {
                    $expertInfo = $this->getExpertInfo($expertId);
                    unset($expertInfo['phone']);
                    unset($expertInfo['idcard_number']);
                    unset($expertInfo['real_name']);
                    $expertInfo['resource_num'] = $resourceModel->getResourceTotalByExpertId($expertId, 0);
                    $expertInfo['number'] = $start;
                    $expertInfo['combat_gains_ten'] = $combat_gains_ten;
                    $isFollowExpert = (int)$userFollowModel->checkFollowExpertStatus($user_id, $expertId);
                    if (!$isFollowExpert) {
                        $expertList[] = $expertInfo;
                        $needExpertNum++;
                    }
                }
            }
            $start++;
        }
        if ($needExpertNum >= 1) {
            return $expertList;
        } else {
            return [];
        }
    }

    public function getFollowUserByExpert($expert_id) {
        $user_expert_dal = new DALUserFollowExpert($this->_appSetting);
        $user_attent_info = $user_expert_dal->getExpertFollowList($expert_id);
        if ($user_attent_info) {
            $user_ids  = array_column($user_attent_info, 'user_id');
            return $user_ids;
        } else {
            return false;
        }
    }

    /*
     * 定时计算
     */

    /*
    * 专家近几中几
    * 显示命中率折线图中最高命中率对应的近x中x  ，最高命中率低于60%不展示
    */
    public function getExpertMaximum($expertId,$platform=2){
        $redisModel = new RedisModel('expert');
        $redisKey=EXPERT_L_RED.$expertId;
        $arr=[];
        $winningBetRecord=$this->calBetRecordStatListV2($expertId, $platform);
        $last_names = array_column($winningBetRecord,'winning');
        array_multisort($last_names,SORT_DESC,$winningBetRecord);
        $data=$winningBetRecord[0];
        foreach($winningBetRecord as $v){
            if($v['winning']>=$data['winning']&&$data['round']!=$v['round']){
                $data=$v;
            }
        }
        if($data['winning']>=60){
            $arr=[$data['round'],$data['red']];
        }

        $redisModel->redisSet($redisKey, $arr);
        return $arr;
    }

    /*
     * 专家连红数
     */
    public function maxRedNum($expertId,$platform=2){
        $expertExtraModel = new ExpertExtraModel();
        $dataExtra = $expertExtraModel->getExpertExtraInfo($expertId);
        //重新计算连红和最大连红
        $platform_key = ($platform == 1) ? 'bd_display' : 'wx_display';
        $condition = ['expert_id' => $expertId, $platform_key => 1, 'resource_status' => 1];
        $orderBy = ['release_time' => 'desc'];
        $resourceList = $this->_dalResource->getAllResources($condition, [], 0, 20, $orderBy);

        $red_num = 0;
        foreach($resourceList as $resource) {
            $all_bet_status = $this->_resourceModel->getResourceExtraInfo($resource['resource_id']);
            if(in_array($all_bet_status['bet_status'],[1,5,6])) {
                $red_num += 1;
            } else {
                break;
            }
        }
        if($red_num<3&&isset($dataExtra['max_red_num'])){
            $red_num=0;
            if($dataExtra['max_red_num']>2){
                $red_num='历史'.intval($dataExtra['max_red_num']);
            }
        }
        $red_num=(string)$red_num;
        return $red_num;
    }
}
