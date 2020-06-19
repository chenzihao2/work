<?php
/**
 * 订单模块
 * User: WangHui
 * Date: 2018/10/12
 * Time: 下午2:31
 */

namespace QK\HaoLiao\Model;


use QK\HaoLiao\DAL\BaseDAL;
use QK\HaoLiao\DAL\DALOrder;
use QK\HaoLiao\DAL\DALOrderResourceExtra;
use QK\HaoLiao\DAL\DALOrderSubscribeExtra;
use QK\HaoLiao\DAL\DALOrderVcExtra;
use QK\HaoLiao\DAL\DALNews;
use QK\HaoLiao\DAL\DALVideo;
use QK\HaoLiao\Common\NumberHandler;
use QK\HaoLiao\Common\PayParams;
use QK\HaoLiao\Common\CommonHandler;
use QK\HaoLiao\Model\CouponModel;

class OrderModel extends BaseModel {
    private $_redisModel;
    private $_dalOrder;

    public function __construct() {
        parent::__construct();
        $this->_redisModel = new RedisModel("order");
        $this->_dalOrder = new DALOrder($this->_appSetting);
    }

    /**
     * 创建一个新订单
     * @param $orderData
     * @return bool
     */
    public function unifiedOrder($orderData) {
        $dalOrder = new DALOrder($this->_appSetting);
        return $dalOrder->unifiedOrder($orderData);
    }

    /**
     * 创建订单扩展信息
     * @param $orderExtra
     * @param $orderType
     * @return bool|int
     */
    public function unifiedOrderExtra($orderExtra, $orderType) {
        switch ($orderType) {
            case 1:
                //订阅专家订单
                $dalOrderSubscribeExtra = new DALOrderSubscribeExtra($this->_appSetting);
                return $dalOrderSubscribeExtra->unifiedOrderSubscribeExtra($orderExtra);
                break;
            case 2:
                //购买料订单
                $dalOrderResourceExtra = new DALOrderResourceExtra($this->_appSetting);
                return $dalOrderResourceExtra->unifiedOrderResourceExtra($orderExtra);
                break;
            case 100:
                // 虚拟币购买
                $dalOrderVcExtra = new DALOrderVcExtra($this->_appSetting);
                return $dalOrderVcExtra->unifiedOrderVcExtra($orderExtra);
            default:
                return false;
                break;
        }
    }

    /**
     * 获取订单列表（后台）
     * @param $where
     * @param $page
     * @param $pagesize
     * @return array|bool
     */
    public function getOrderList($where, $page, $pagesize) {
        $start = ($page - 1) * $pagesize;
//        $res['total'] = $this->_dalOrder->getOrderTotal($where);
//        $orderList = $this->_dalOrder->getOrderList($where, $start, $pagesize);

        $res['total'] = $this->_dalOrder->getOrderTotalNew($where);
        $orderList = $this->_dalOrder->getOrderListNew($where, $start, $pagesize);
        if (!empty($orderList)) {
            foreach ($orderList as $key => $val) {
                $orderInfo = $this->getOrderInfo($val['order_num']);
                $expertModel = new ExpertModel();
                $expertInfo = $expertModel->getExpertInfo($orderInfo['expert_id']);
                $distId = $orderInfo['dist_id'];
                $orderInfo['dist_name'] = '--';
                if($distId){
                    $distModel = new DistModel();
                    $distInfo = $distModel->getDistInfo($orderInfo['dist_id']);
                    $orderInfo['dist_name'] = $distInfo['dist_name'];
                }
                $orderInfo['order_param_info'] = [];

                $orderList[$key] = [];
                $orderList[$key]['resource_id'] = $orderInfo['resource_id'];
                $orderList[$key]['expert_name'] = $expertInfo['expert_name'];
                $orderList[$key]['expert_id'] = $orderInfo['expert_id'];
                $orderList[$key]['nick_name'] = $orderInfo['nick_name'];
                $orderList[$key]['user_id'] = $orderInfo['user_id'];
                $orderList[$key]['order_source'] = $orderInfo['order_source'];
                $orderList[$key]['order_status'] = $orderInfo['order_status'];
                $orderList[$key]['buy_time'] = $orderInfo['buy_time'];
                $orderList[$key]['refund_time'] = empty($orderInfo['refund_time']) ? '' : $orderInfo['refund_time'];
                $orderList[$key]['order_num'] = $orderInfo['order_num'];
                $orderList[$key]['payment_method'] = $orderInfo['payment_method'];
                $orderList[$key]['order_amount'] = $val['order_amount'] ? bcdiv($val['order_amount'], 100) : 0;
                $orderList[$key]['discount_amount'] = $val['discount_amount'] ? bcdiv($val['discount_amount'], 100) : 0;
                $orderList[$key]['pay_amount'] = $val['pay_amount'] ? bcdiv($val['pay_amount'], 100) : 0;
                $orderList[$key]['platform'] = $val['platform'];
                $orderList[$key]['channel'] = $val['channel'];
                if ($orderInfo['order_type'] == 2) {
                    $resourceModel = new ResourceModel();
                    $resourceInfo = $resourceModel->getResourceDetailedInfo($orderInfo['resource_id']);
                    $orderList[$key]['resource_name'] = $resourceInfo['title'];
                    $orderList[$key]['resource_type'] = $resourceInfo['resource_type'];
                    $orderList[$key]['price'] = $resourceInfo['price'];
                    $orderList[$key]['bet_status'] = $resourceInfo['bet_status'];
					$orderList[$key]['order_type'] = $orderInfo['order_type'];
                }
				if(in_array($orderInfo['order_type'],[3,4,5,6])){
                    $orderList[$key]['order_type'] = $orderInfo['order_type'];
                    $orderList[$key]['resource_type'] = 0;
                    $orderList[$key]['resource_id'] = $orderInfo['order_param'];
                    $orderList[$key]['price'] = intval($orderInfo['pay_amount']);
					$orderList[$key]['resource_name']=$this->getOrderName($orderInfo['order_type'],$orderInfo['order_param']);
                }

            }
        }
        $res['list'] = $orderList;
        return $res;
    }
	
	/*
     * 获取资源名称
     */
    public function getOrderName($order_type,$order_param=0){
        switch ($order_type){
            case 3:
                //文章
                $DALOrder=new DALNews($this->_appSetting);
                $newInfo=$DALOrder->getNewsInfo($order_param);
                $name=$newInfo['title'];
                break;
            case 4:
                //视频
                $DALVideo=new DALVideo($this->_appSetting);
                $videoInfo=$DALVideo->getVideoInfo($order_param);
                $name=$videoInfo['title'];
                break;

            case 5:
                $soccerModel = new SoccerModel();
                $soccerInfo = $soccerModel->nowInfo($order_param);
                $name = "【足球】-" . $soccerInfo['date'] . "-【" . $soccerInfo['league_short_name'] . "】" . $soccerInfo['guest_team_name'] . "VS" . $soccerInfo['host_team_name'];
                break;
            case 6:
                $basketballModel = new BasketballModel();
                $basketballInfo = $basketballModel->matchInfo($order_param);
                $name = "【篮球】-" . $basketballInfo['date'] . "-【" . $basketballInfo['league_short_name'] . "】" . $basketballInfo['guest_team_name'] . "VS" . $basketballInfo['host_team_name'];
                break;

        }

        return $name;
    }

    /**
     * 获取订单列表（后台）
     * @param $where
     * @param $page
     * @param $pagesize
     * @return array|bool
     */
    public function getVcBuyOrderList($where, $page, $pagesize) {
        $start = ($page - 1) * $pagesize;
        $res['total'] = $this->_dalOrder->getOrderTotal($where);
        $orderList = $this->_dalOrder->getOrderList($where, $start, $pagesize);
        if (!empty($orderList)) {
            foreach ($orderList as $key => $val) {
                $orderInfo = $this->getOrderInfo($val['order_num']);
                $orderList[$key] = [];
                $orderList[$key]['user_id'] = $orderInfo['user_id'];
                $orderList[$key]['nick_name'] = $orderInfo['nick_name'];
                $orderList[$key]['pay_amount'] = $orderInfo['pay_amount'];
                $orderList[$key]['vc_amount'] = $orderInfo['order_param'];
                $orderList[$key]['order_status'] = $orderInfo['order_status'];
                $orderList[$key]['order_source'] = $orderInfo['order_source'];
                $orderList[$key]['payment_method'] = $orderInfo['payment_method'];
                $orderList[$key]['buy_time'] = $orderInfo['buy_time'];
                $orderList[$key]['order_num'] = $orderInfo['order_num'];
                $orderList[$key]['channel_order_num'] = empty($orderInfo['channel_order_num']) ? '' : $orderInfo['channel_order_num'];
                $orderList[$key]['platform'] = $val['platform'];
                $orderList[$key]['channel'] = $val['channel'];

            }
        }
        $res['list'] = $orderList;
        return $res;
    }

    /**
     * 获取专家成功订单
     * @param $expertId
     * @param $orderType
     * @param $page
     * @param $size
     * @return array
     */
    public function getExpertOrder($expertId, $orderType, $page, $size) {
        $start = ($page - 1) * $size;
        $redisKey = ORDER_EXPERT_LIST . $expertId . ":" . $orderType;
        //根据分值范围获取redis数据
        $orderNumberList = $this->_redisModel->redisZRangeByScore($redisKey, $start, $start + $size - 1);
        if (empty($orderNumberList)) {
            //获取mysql数据
            $orderList = $this->_dalOrder->getExpertOrderList($expertId, $orderType, $page, $size);
            $orderNumberList = [];
            if (!empty($orderList)) {
                foreach ($orderList as $key => $val) {
                    //相关数据入redis
                    $orderNumberList[] = $orderNum = $val['order_num'];
                    $this->_redisModel->redisZAdd($redisKey, $key + $start, $orderNum);
                }
            }
        }
        $result = [];
        if (!empty($orderNumberList)) {
            foreach ($orderNumberList as $orderNum) {
                //根据订单号获取订单详情
                $orderInfo = $this->getOrderInfo($orderNum);
                if ($orderInfo['order_type'] == 2) {
                    $resource = new ResourceModel();
                    $resourceInfo = $resource->getResourceInfo($orderInfo['resource_id']);
                    $title = $resourceInfo['title'];
                } else {
                    $title = "";
                }
                $orderInfo['resource_title'] = $title;
                $result[] = $orderInfo;
            }
        }

        return $result;


    }

    /**
     * 获取料售卖订单记录
     * @param $resourceId
     * @param $page
     * @param $pagesize
     * @return array
     */
    public function getResourceOrderList($resourceId, $page, $pagesize) {
        $start = ($page - 1) * $pagesize;
        $redisKey = ORDER_RESOURCE_LIST . $resourceId;
        //根据分值范围获取redis数据
        $orderNums = $this->_redisModel->redisZRangeByScore($redisKey, $start, $start + $pagesize - 1);
        if (empty($orderNums)) {
            //获取mysql数据
            $orderNumsList = $this->_dalOrder->getOrderNumsByResourceId($resourceId, $start, $pagesize);
            $orderNums = [];
            if (!empty($orderNumsList)) {
                foreach ($orderNumsList as $key => $val) {
                    //相关数据入redis
                    $orderNums[] = $orderNum = $val['order_num'];
                    $this->_redisModel->redisZAdd($redisKey, $key + $start, $orderNum);
                }
            }
        }
        $orderList = [];
        if (!empty($orderNums)) {
            foreach ($orderNums as $orderNum) {
                //根据订单号获取订单详情
                $orderList[] = $this->getOrderInfo($orderNum);
            }
        }
        return $orderList;
    }

    /**
     * 获取料售卖条数
     * @param $resourceId
     * @return array|bool|mixed|null|string
     */
    public function getResourceSoldNum($resourceId) {
        $redisKey = ORDER_RESOURCE_SOLD_NUM . $resourceId;
        $soldNum = $this->_redisModel->redisGet($redisKey);
        if (!$soldNum) {
            $soldNum = $this->_dalOrder->getSoldNumByResourceId($resourceId);
            $this->_redisModel->redisSet($redisKey, $soldNum);
        }
        return (int)$soldNum;
    }


    /**
     * 获取料售卖总金额
     * @param $resourceId
     * @return array|bool|mixed|null|string
     */
    public function getResourceSoldMoney($resourceId) {
        $redisKey = ORDER_RESOURCE_SOLD_MONEY . $resourceId;
        $soldMoney = $this->_redisModel->redisGet($redisKey);
        if (!$soldMoney) {
            $soldMoney = $this->_dalOrder->getSoldMoneyByResourceId($resourceId);
            $this->_redisModel->redisSet($redisKey, $soldMoney);
        }
        $soldMoney = $this->ncPriceFen2Yuan($soldMoney);
        return $soldMoney;
    }

    /**
     * 根据订单号获取订单详情
     * @param $orderNum
     * @return array|bool|mixed|null|string
     */
    public function getOrderInfo($orderNum) {
        //获取订单主要信息
        $redisKey = ORDER_INFO . $orderNum;
        $orderInfo = $this->_redisModel->redisGet($redisKey, true);
        if (empty($orderInfo)) {
            $orderInfo = $this->_dalOrder->getOrderInfo($orderNum);
            $this->_redisModel->redisSet($redisKey, $orderInfo);
        }

        //获取订单扩展信息
        $extraRedisKey = ORDER_EXTRA_INFO . $orderNum;
        $orderExtraInfo = $this->_redisModel->redisGet($extraRedisKey, true);
        if (empty($orderExtraInfo)) {
            $orderType = $orderInfo['order_type'];
            switch ($orderType) {
                case 1:
                    //订阅订单
                    $dalOrderSubscribeExtra = new DALOrderSubscribeExtra($this->_appSetting);
                    $orderExtraInfo = $dalOrderSubscribeExtra->getOrderSubscribeExtraInfo($orderNum);
                    break;
                case 2:
                    //料订单
                    $dalOrderResourceExtra = new DALOrderResourceExtra($this->_appSetting);
                    $orderExtraInfo = $dalOrderResourceExtra->getOrderResourceExtraInfo($orderNum);
                    break;
                case 100:
                    // 虚拟币订单
                    $dalOrderResourceExtra = new DALOrderVcExtra($this->_appSetting);
                    $orderExtraInfo = $dalOrderResourceExtra->getOrderVcExtraInfo($orderNum);
                    break;
                default:
                    $orderExtraInfo = [];
                    break;
            }
            $this->_redisModel->redisSet($extraRedisKey, $orderExtraInfo);
        }
        $orderInfo = array_merge($orderInfo, $orderExtraInfo);
        $orderInfo['order_amount'] = $this->ncPriceFen2Yuan($orderInfo['order_amount']);
        $orderInfo['discount_amount'] = $this->ncPriceFen2Yuan($orderInfo['discount_amount']);
        $orderInfo['pay_amount'] = $this->ncPriceFen2Yuan($orderInfo['pay_amount']);
        $orderInfo['buy_time'] = $this->friendlyDate($orderInfo['buy_time'], 'mdhis');
        $userModel = new UserModel();
        $userInfo = $userModel->getUserInfo($orderInfo['user_id']);
        $orderInfo['nick_name'] = $userInfo['nick_name'];
        $orderInfo['sex'] = $userInfo['sex'];
        $orderInfo['headimgurl'] = $userInfo['headimgurl'];
        if ($orderInfo['order_type'] == 100) {  // 虚拟币
            $orderInfo['order_param'] = $this->ncPriceFen2Yuan($orderInfo['order_param']);
        }
        return $orderInfo;
    }

    /**
     * 检查用户是否购买过此料
     * @param $userId
     * @param $resourceId
     * @return bool
     */
    public function checkUserIsBuyResource($userId, $resourceId) {
        if (!$userId || !$resourceId) {
            return false;
        }

        $dalOrder = new DALOrder($this->_appSetting);
        $orderInfo = $dalOrder->getResourceOrderByUserId($userId, $resourceId);
        if (!empty($orderInfo)) {
            return true;
        }
        return false;
    }

    /**
     * 获取用户订单料信息
     * @param     $userId
     * @param     $page
     * @param     $size
     * @param int $orderType
     * @return array
     */
    public function userOrderResourceList($userId, $page, $size, $orderType = 2) {
        $start = ($page - 1) * $size;
        $redisKey = ORDER_USER_LIST . $userId . ":" . $orderType;
        //根据分值范围获取redis数据
        $orderNumberList = $this->_redisModel->redisZRangeByScore($redisKey, $start, $start + $size - 1);
        if (empty($orderNumberList)) {
          //获取mysql数据
          //UPDATE: UUID获取用户信息
          $condition = array('order_status' =>['in', '(1, 3)'], 'order_type' => $orderType);
          $userModel = new UserModel();
          $userInfo = $userModel->getUserInfo($userId);
          if($userInfo['uuid']) {
            $users = $userModel->getUsersByUUid($userInfo['uuid']);
            $uids = implode(', ', array_column($users, 'user_id'));
            $condition['user_id'] = ['in', "($uids)"];
          }else {
            $condition['user_id'] = $userId;
          }

          $fields = array('order_id', 'order_num');
          $orderBy = array('buy_time' => 'desc');
            $orderList = $this->_dalOrder->getOrderListV2($condition, $fields, ($page - 1) * $size, $size, $orderBy);
            $orderNumberList = [];
            if (!empty($orderList)) {
                foreach ($orderList as $key => $val) {
                    //相关数据入redis
                    $orderNumberList[] = $orderNum = $val['order_num'];
                    $this->_redisModel->redisZAdd($redisKey, $key + $start, $orderNum);
                }
            }
        }
        $resourceList = [];
        $resourceModel = new ResourceModel();
        if (!empty($orderNumberList)) {
            $expertModel = new ExpertModel();
            foreach ($orderNumberList as $key => $orderNum) {
                //根据订单号获取订单详情
                $orderInfo = $this->getOrderInfo($orderNum);
                $expertInfo = $expertModel->getExpertInfo($orderInfo['expert_id']);
                $resourceId = $orderInfo['order_param'];
                $resourceList[$key] = $resourceModel->getResourceBriefInfo($resourceId);
                $resourceList[$key]['sold_num'] += $resourceList[$key]['cron_sold_num'];
                $resourceList[$key]['expert_id'] = $orderInfo['expert_id'];
                $resourceList[$key]['expert_name'] = $expertInfo['expert_name'];
                $resourceList[$key]['headimgurl'] = $expertInfo['headimgurl'];
                $resourceList[$key]['buy_time'] = $orderInfo['buy_time'];
                $resourceScheduleList = $resourceModel->getResourceScheduleList($resourceId);
                $resourceList[$key]['schedule'] = $resourceScheduleList;
                $resourceList[$key]['bet_status'] = $resourceModel->getBetStatus($resourceScheduleList);
                if ($resourceList[$key]['is_groupbuy'] == 1) {
                    $resourceList[$key]['group'] = $resourceModel->getResourceGroupInfo($resourceId);
                }
            }
        }
        return $resourceList;
    }

    public function saveOrder(&$orderData, &$orderExtraData, $orderType) {

        $dalBase = new BaseDAL($this->_appSetting);
        $dalBase->beginTrans();

        //下单入库处理
        $orderId = $this->unifiedOrder($orderData);
        if(!$orderId){
            $dalBase->rollBack();
            return false;
        }
        
        if (!empty($orderExtraData)) {
          $orderExtraData['order_id'] = $orderId;
          $res = $this->unifiedOrderExtra($orderExtraData, $orderType);
          if(!$res){
            $dalBase->rollBack();
            return false;
          }
        }

        $dalBase->commit();

        return true;
    }

    /**
     * 订单回调成功处理
     * @param     $orderNum
     * @param     $channelOrderNum
     * @param int $channelUserId
     * @return bool
     */
    public function successOrder($orderNum, $channelOrderNum = '', $channelUserId = 0) {
        //获取订单信息详情
        $orderInfo = $this->getOrderInfo($orderNum);

        $res = $this->successOrderTrains($orderInfo, $channelOrderNum, $channelUserId);

        if ($res === false) {
            return false;
        }

        // 完成相关订单
        if (!empty($orderInfo['related_order_num'])) {
            $relatedOrderInfo = $this->getOrderInfo($orderInfo['related_order_num']);
            $this->successOrderTrains($relatedOrderInfo);
        }

        return $res;
    }

    public function successOrderTrains($orderInfo, $channelOrderNum = '', $channelUserId = 0) {

        $baseDAL = new BaseDAL($this->_appSetting);
        // 开启事物
        $baseDAL->beginTrans();

        $orderNum = $orderInfo['order_num'];

        // 如果付款方式为虚拟币
        if ($orderInfo['payment_method'] == 100) {
            $payAmount = $this->ncPriceYuan2Fen($orderInfo['pay_amount']);
            $userId = $orderInfo['user_id'];

            $userModel = new UserModel();
            // 扣除用户虚拟币余额
            $payVcInfo = $userModel->userVcChange($userId, 2, $payAmount, $orderInfo['order_id'], 20);
            if ($payVcInfo === false) {
                $baseDAL->rollBack();
                return false;
            }
            $channelOrderNum = $payVcInfo['vcRecordId'];
            if ($orderInfo['coupon_id']) {
                $couponModel = new CouponModel();
                $coupon_id = $orderInfo['coupon_id'];
                $coupon_res = $couponModel->changeUserCouponStatus($coupon_id, 1);
                if (!$coupon_res) {
                    $baseDAL->rollBack();
                    return false;
                }
            }
        }

        $res = $this->_dalOrder->updateOrder($orderNum, ['channel_order_num' => $channelOrderNum, 'channel_user_id' => $channelUserId, 'order_status' => 1, 'notify_time' => time()]);
        if (!$res) {
            $baseDAL->rollBack();
            return false;
        }

        if ($orderInfo['order_type'] == 100){  // 虚拟币下单
            //清除缓存信息
            //订单主要信息
            $redisKey[] = ORDER_INFO . $orderNum;

            $this->vcOrderSuccess($orderInfo);
        } else {  // 其他订单
            $userId = $orderInfo['user_id'];
            $expertId = $orderInfo['expert_id'];
            $distId = $orderInfo['dist_id'];
            $orderParam = $orderInfo['order_param'];
            $orderType = $orderInfo['order_type'];
            $payAmount = $this->ncPriceYuan2Fen($orderInfo['pay_amount']);

            //获取专家分成比例/金额
            $expertRateModel = new ExpertRateModel();
            $expertRateInfo = $expertRateModel->getExpertRate($expertId);
            $expertRate = $expertRateInfo['rate'];
            $payAmountExpert = $this->ncPriceCalculate($payAmount, '*', $expertRate, 0);

            //清除缓存信息
            //订单主要信息
            $redisKey[] = ORDER_INFO . $orderNum;
            //用户订单列表
            $userModel = new UserModel();
            $userInfo = $userModel->getUserInfo($userId);
            if($userInfo['uuid']) {
              $users = $userModel->getUsersByUUid($userInfo['uuid']);
              $uids = array_column($users, 'user_id');
              foreach($uids as $uid) {
                $redisKey[] = ORDER_USER_LIST . $uid . ":" . $orderType;
              }
            } else {
              $redisKey[] = ORDER_USER_LIST . $userId . ":" . $orderType;
            }
            //专家订单列表
            $redisKey[] = ORDER_EXPERT_LIST . $expertId . ":" . $orderType;
            //专家订单列表(全部)
            $redisKey[] = ORDER_EXPERT_LIST . $expertId . ":0";

            $userExtraIncOrDec = [];

            // 如果用户并非虚拟币购买
            if ($orderInfo['order_type'] != 100) {
                // 增加用户已支付金额
                $userExtraIncOrDec['pay_amount'] = '+' . $payAmountExpert;
            }

            // todo 专家和分销部分专家端上线后需要调整
              switch ($orderType) {
                case 1:
                    //订阅订单
                    //已订阅专家数
                    $userExtraIncOrDec['subscribe_num'] = '+1';
                    //增加用户订阅记录
                    $userSubscribeModel = new UserSubscribeModel();
                    $startTime = $orderInfo['start_time'];
                    $endTime = $orderInfo['end_time'];
                    $userSubscribeModel->setUserSubscribe($userId, $orderParam, $payAmount, $startTime, $endTime);
                    //增加专家冻结金额和订阅人数
                    $expertExtraIncOrDec = ['freezing' => '+' . $payAmountExpert, 'subscribe_num' => '+1'];
                    break;
                case 2:
                    //料购买订单
                    $resourceId = $orderInfo['order_param'];
                    $redisKey[] = ORDER_RESOURCE_SOLD_MONEY . $resourceId;
                    $redisKey[] = ORDER_RESOURCE_LIST . $resourceId;
                    //增加用户已支付金额和已订阅专家数
                    $resourceType = $orderInfo['resource_type'];
                    //增加专家已销售料总数
                    $expertExtraIncOrDec = ['sold_resource_num' => '+1'];
                    //增加收入，判断料类型
                    switch ($resourceType) {
                        case 1:
                            //普通料订单
                            //增加专家总收入
                            $expertExtraIncOrDec['income'] = "+" . $payAmountExpert;
                            //增加专家可提现
                            $expertExtraIncOrDec['balance'] = "+" . $payAmountExpert;
                            //增加金额变更记录
                            $expertMoneyChangeModel = new ExpertMoneyChangeModel();
                            $expertMoneyChangeModel->setMoneyChange($userId, $expertId, 1, 1, $payAmount);

                            //获取分销商分成比例/金额
                            if ($distId) {
                                $distRateModel = new DistRateModel();
                                $distRateInfo = $distRateModel->getDistRateInfo($distId);
                                $distRate = $distRateInfo['rate'];
                                $payAmountDist = $this->ncPriceCalculate($payAmount, '*', $distRate, 0);
                                //增加分销商金额
                                $distIncOrDec['income'] = "+" . $payAmountDist;
                                $distIncOrDec['balance'] = "+" . $payAmountDist;
                                $distExtraModel = new DistExtraModel();
                                $distExtraModel->setDistExtraIncOrDec($distId, $distIncOrDec);
                                //增加金额变更记录
                                $distMoneyChangeModel = new DistMoneyChangeModel();
                                $distMoneyChangeModel->setMoneyChange($userId, $distId, 1, 1, $payAmount);
                            }

                            break;
                        case 2:
                            //不中退款订单
                            //增加专家冻结金额
                            $expertExtraIncOrDec['freezing'] = "+" . $payAmountExpert;
                            break;
						
                        default:
                            $baseDAL->rollBack();
                            return false;
                            break;
                    }

                    //增加单个料的售出数量
                    $resourceModel = new ResourceModel();
                    $resourceModel->setResourceExtraIncOrDec($resourceId, ['sold_num' => '+1']);

                    // 如果为合买料订单
                    if (!empty($orderInfo['group_id'])) {
                        // 如果是成团最后一单
                        $resourceExtraInfo = $resourceModel->getResourceExtraInfo($resourceId);
                        $resourceModel->addCronSoldNum($resourceExtraInfo);
                        $resourceGroupInfo = $resourceModel->getResourceGroupInfo($resourceId);
                        if ($resourceExtraInfo['sold_num'] >= $resourceGroupInfo['num']) {
                            // 合买成功处理
                            $resourceModel->groupSuccess($resourceId);
                        }
                    }
                    break;
				case 3:
				case 4:
				case 5:
				case 6:
							
						break;
                default:
                    $baseDAL->rollBack();
                    return false;
                    break;
            }

            if (!empty($userExtraIncOrDec)) {
                //增加用户已支付金额
                $userModel = new UserModel();
                $userModel->setUserExtraIncOrDec($userId, $userExtraIncOrDec);
            }
			if($expertId){
				//增加专家冻结金额和订阅人数
				$expertExtraModel = new ExpertExtraModel();
				$expertExtraModel->setExpertExtraIncOrDec($expertId, $expertExtraIncOrDec);
			}
        }

        $this->_redisModel->redisDel($redisKey);

        // 提交事务
        $baseDAL->commit();
        return true;
    }

    public function vcOrderSuccess(&$orderInfo) {
        $userId = $orderInfo['user_id'];

        $userModel = new UserModel();
        $userModel->userVcChange($userId, 1, $this->ncPriceYuan2Fen($orderInfo['order_param']), $orderInfo['order_id'], 10);

        // 增加用户已支付金额
       // $userModel->setUserExtraIncOrDec($userId, ['pay_amount' => '+' . $orderInfo['pay_amount']]);

        return true;
    }

    /**
     * 获取购买过某个料的订单（退款定时程序,分销商红单分成用）
     * @param $resourceId
     * @return array|bool
     */
    public function getResourceOrder($resourceId) {
        return $this->_dalOrder->getResourceOrder($resourceId);
    }

    /**
     * 获取购买过某个料的合买订单
     * @param $resourceId
     * @return array|bool
     */
    public function getResourceGroupOrder($resourceId) {
        return $this->_dalOrder->getResourceGroupOrder($resourceId);
    }

    /**
     * 更新订单信息
     * @param $orderNum
     * @param $params
     * @return int
     */
    public function updateOrder($orderNum, $params) {
        $res = $this->_dalOrder->updateOrder($orderNum, $params);
        $this->_redisModel->redisDel(ORDER_INFO . $orderNum);
        return $res;
    }

    /**
     * 获取料的所有成功订单金额(按照分成比例计算专家应得金额)
     * @param $resourceId
     * @param $expertId
     * @return mixed
     */
    public function getResourceAmount($resourceId,$expertId) {
        $payAmount =  $this->_dalOrder->getResourceAmount($resourceId);
        //获取专家分成比例/金额
        $expertRateModel = new ExpertRateModel();
        $expertRateInfo = $expertRateModel->getExpertRate($expertId);
        $expertRate = $expertRateInfo['rate'];
        $payAmountExpert = $this->ncPriceCalculate($payAmount, '*',  $expertRate, 0);
        return $payAmountExpert;
    }

    /**
     * 计算分销商应得金额
     * @param $distId
     * @param $payAmount
     * @return string
     */
    public function distAmount($distId,$payAmount) {
        $distRateModel = new DistRateModel();
        $distRateInfo = $distRateModel->getDistRateInfo($distId);
        $distRate = $distRateInfo['rate'];
        return $this->ncPriceCalculate($payAmount, '*',  $distRate, 0);

    }

    public function resourceOrder($userId, $orderType, $paymentMethod, $tradeType, $orderParam, $payReturnUrl, $coupon_id = 0, $platform=0, $channel=0) {

        $payModel = new PayModel($userId, $orderType, $paymentMethod, $tradeType, $payReturnUrl);

        $initRes = $payModel->initPay();
        if ($initRes['status_code'] !== 200) {
            return $initRes;
        }

        $userInfo = $payModel->getUserInfo();

        $orderData = [];
        $orderData['order_num'] = $orderNum = $updateString = NumberHandler::newInstance()->getOrderNumber();
        $orderData['wechat_id'] = $GLOBALS['weChatId'];
        $orderData['order_type'] = $orderType;
        $orderData['payment_method'] = $paymentMethod;
        $orderData['trade_type'] = $tradeType;
        $orderData['payment_channel'] = $payModel->getpaymentChannel();
        $orderData['appid'] = $payModel->getAppId();
        $orderData['user_id'] = $userId;
        $orderData['order_source'] = CommonHandler::newInstance()->getPlatform($userInfo['platform']);
        $orderData['dist_id'] = $userInfo['dist_id'] ? $userInfo['dist_id'] : 0;
        $orderData['platform'] = $platform;
        $orderData['channel'] = $channel;

        $userModel = $payModel->getUserModel();

        //判断订单类型
        switch($orderType){
            case 1:
                //订阅专家订单
                $expertId = $orderParam;
                //获取专家信息
                $expertModel = new ExpertModel();
                $expertInfo = $expertModel->getExpertInfo($expertId);
                if(empty($expertInfo) || $expertInfo['status'] != 1){
                    return $this->retError(1301);
                }

                if($userId == $expertInfo['user_id']){
                    return $this->retError(3004);
                }

                //检查用户是否已订阅
                $userSubscribeModel = new UserSubscribeModel();
                $userIsSubscribe = $userSubscribeModel->checkUserSubscribeExpert($userId, $expertId);
                if($userIsSubscribe){
                    return $this->retError(3001);
                }

                //获取专家30日订阅价格
                $expertSubscribeModel = new ExpertSubscribeModel();
                $expertSubscribe = $expertSubscribeModel->getExpertSubscribeByDays($expertId, 30, false);
                $expertSubscribePrice = $expertSubscribe['subscribe_price'];

                //组装订单数据
                $orderData['order_amount'] = $expertSubscribePrice;
                $orderData['discount_amount'] = 0;
                $orderData['pay_amount'] = $price = $expertSubscribePrice;
                $orderData['expert_id'] = $expertId;
                $orderData['order_param'] = $orderParam;
                $orderData['buy_time'] = time();

                $orderExtra = [];
                $orderExtra['order_num'] = $orderNum;
                $orderExtra['expert_id'] = $expertId;
                $orderExtra['start_time'] = time();
                $orderExtra['end_time'] = strtotime(date('Y-m-d', strtotime('+31 day')));

                break;
            case 2:
                //购买料订单
                $resourceId = $orderParam;

                //获取料信息
                $resourceModel = new ResourceModel();
                $resourceInfo = $resourceModel->getResourceBriefInfo($resourceId, false);
                if(empty($resourceInfo)){
                    return $this->retError(2001);
                }

                $expertId = $resourceInfo['expert_id'];
                if($userId == $expertInfo['user_id']){
                    return $this->retError(3005);
                }

                if($resourceInfo['resource_type'] == 2 && $resourceInfo['bet_status']){
                    return $this->retError(3009);
                }

                //检查用户是否购买过此料
                //$userIsBuyResource = $this->checkUserIsBuyResource($userId, $resourceId);
                $userIsBuyResource = $this->checkUserBuyV2($userId, $resourceId);
                if($userIsBuyResource){
                    return $this->retError(3006);
                }

                // 如果是合买订单
                if ($resourceInfo['is_groupbuy'] == 1) {
                    $resourceGroupInfo = $resourceModel->getResourceGroupInfo($resourceId, false);
                    $nowtime = time();
                    // 如果料状态不为合买中，或者当前时间已经超过合买截止时间则不能继续购买
                    switch ($resourceGroupInfo['status']) {
                        case '1':
                            return $this->retError(3010);
                            break;
                        case '2':
                            return $this->retError(3011);
                            break;
                        default:
                            if ($resourceGroupInfo['status'] != 0) {
                                return $this->retError(3011);
                            }
                            if ($resourceGroupInfo['limit_time'] <= $nowtime) {
                                return $this->retError(3011);
                            }

                    }
                    $orderData['group_id'] = $resourceGroupInfo['group_id'];
                    $resourcePrice = $resourceGroupInfo['price'];  // 合买订单价格
                } else {
                    $resourcePrice = $resourceInfo['price'];  // 普通订单价格
                }
                //组装订单数据
                $orderData['order_amount'] = $resourcePrice;
                $orderData['discount_amount'] = 0;
                $orderData['pay_amount'] = $price = $resourcePrice;
                $orderData['expert_id'] = $expertId;
                $orderData['order_param'] = $orderParam;
                $orderData['buy_time'] = time();

                //优惠券
                if ($coupon_id) {
                    $couponModel = new CouponModel();
                    $coupon_info = $couponModel->getUserCouponInfo($coupon_id);
                    if ($coupon_info) {
                        $check = 1;
                        if ($coupon_info['type'] == 1) {
                            if ($coupon_info['condition'] > $price) {
                                $check = 0; 
                            }
                        }
                        if ($coupon_info['type'] == 0) {
                            if ($coupon_info['discount'] > $price) {
                                $coupon_info['discount'] = $price;
                            }
                        }
                        if ($check) {
                            $orderData['discount_amount'] = $coupon_info['discount'];
                            $orderData['coupon_id'] = $coupon_id;
                            $orderData['pay_amount'] = $price = $resourcePrice - $coupon_info['discount'];
                        }
                    }
                }

                $resourceType = $resourceInfo['resource_type'];
                $orderExtra = [];
                $orderExtra['order_num'] = $orderNum;
                $orderExtra['resource_id'] = $resourceId;
                $orderExtra['resource_type'] = $resourceType;

                break;
			case 3:
			case 4:
                //购买文章&视频
                $id = $orderParam;
                //获取信息
                if($orderType==3){
                    $NewsModel = new NewsModel();
                    $Info = $NewsModel->getNewsInfo($id);
                }
                if($orderType==4){
                    $VideoModel = new VideoModel();
                    $Info = $VideoModel->getVideoInfo($id);
                }
                if(empty($Info) || $Info['status'] != 1){
                    return $this->retError(2001,'信息不存在');
                }
                //检查是否已购买
                $res=$this->_dalOrder->getOrderByUserId($userId,$id,$orderType);
                if($res){
                    return $this->retError(3007);
                }
                $resourcePrice=$this->ncPriceYuan2Fen($Info['money']);//订单金额
                //组装订单数据
                $orderData['order_amount'] =$resourcePrice;
                $orderData['discount_amount'] = 0;//优惠金额
                $orderData['pay_amount'] = $price =$resourcePrice;//实付金额
                $orderData['order_param'] = $orderParam;
                $orderData['buy_time'] = time();

                $orderExtra = [];
                break;
            case 5:
            case 6:
              $match_type = ($orderType == 5) ? 1 : 2;
              $matchModel = new MatchModel();
              $matchInfo = $matchModel->getMatchInformation($orderParam, $match_type);
              if(empty($matchInfo) || $matchInfo['status'] == 0){
                return $this->retError(2001);
              }

              //检查用户是否购买过
              $is_buy = $this->checkIsBuy($userId, $orderParam, $orderType);
              if($is_buy){
                return $this->retError(3006);
              }

              //组装订单数据
              $orderData['order_amount'] = $matchInfo['price'];
              $orderData['discount_amount'] = 0;
              $orderData['pay_amount'] = $price = $matchInfo['price'];
              $orderData['order_param'] = $orderParam;
              $orderData['buy_time'] = time();

              $orderExtra = [];
              break;
            default:
                return $this->retError(3002);
                break;
        }

        $res = $this->saveOrder($orderData, $orderExtra, $orderType);
        if ($res !== true) {
            return $this->retError(3007);
        }

        $payParams = new PayParams();
        $goodsName = $payParams->getVest();  // 只有虚拟币支付用户应该看不到
        $payRes = $payModel->pay($goodsName, $orderNum, $price);

        // 如果为虚拟币支付检查余额是否够用
        if ($paymentMethod == 100) {
            $checkRes = $userModel->checkVcBalanceIsEnough($userId, $price);
            if ($checkRes === true) {
                // 调用支付成功
                $res = $this->successOrder($orderNum);
                if ($res === true) {
                    $payRes['data']['orderStatus'] = 1;     // 支付成功
                }
            }
        }

        return $payRes;
    }

    public function checkIsBuy($userId, $order_param, $order_type) {
        if (!$userId || !$order_param) {
            return false;
        }
        $dalOrder = new DALOrder($this->_appSetting);
        $condition = array('order_type' => $order_type,'order_param' => $order_param, 'order_status' => ['in', '(1, 3)']);

        $userModel = new UserModel();
        $userInfo = $userModel->getUserInfo($userId);
        if($userInfo['uuid']) {
          $users = $userModel->getUsersByUUid($userInfo['uuid']);
          $uids = implode(', ', array_column($users, 'user_id'));
          $condition['user_id'] = ['in', "($uids)"];
        }else {
          $condition['user_id'] = $userId;
        }

        $orderInfo = $dalOrder->getOrderByCondition($condition, array());
        if (!empty($orderInfo)) {
            return true;
        }
        return false;
    }

    public function vcOrder($userId, $paymentMethod, $tradeType, $vcBuyConfigId, $payReturnUrl, $relatedOrderNum = '', $platform=0, $channel=0,$source=0) {

        $payModel = new PayModel($userId, 100, $paymentMethod, $tradeType, $payReturnUrl);

        if ($paymentMethod == 100) {
            return $this->retError(-1, '不能使用余额购买');
        }

        $initRes = $payModel->initPay();
        if ($initRes['status_code'] !== 200) {
            return $initRes;
        }
        $userInfo = $payModel->getUserInfo();

        // 订单类型
        $orderType = 100;

        // 充值信息获取
        $vcModel = new VcModel();
        if($source==1){
            $vcBuyConfigInfo=$vcModel->vcBuyAmountById($vcBuyConfigId);
            $vcBuyConfigEnable=true;
            if(!$vcBuyConfigInfo){
                $vcBuyConfigEnable=false;
            }
        }else{
            $vcBuyConfigInfo = $vcModel->vcBuyConfigDetailById($vcBuyConfigId, false);
            $vcBuyConfigEnable = $vcModel->checkVcBuyConfigOk($vcBuyConfigInfo);
        }


        if ($vcBuyConfigEnable !== true) {
            return $this->retError(-1, '充值信息错误');
        }

        $vcAmount = $vcBuyConfigInfo['vc'] + $vcBuyConfigInfo['gift_vc'];

        //组装订单数据
        $orderData = [];
        $orderData['order_num'] = $orderNum = $updateString = NumberHandler::newInstance()->getOrderNumber();
        $orderData['wechat_id'] = $GLOBALS['weChatId'];
        $orderData['order_type'] = $orderType;
        $orderData['payment_method'] = $paymentMethod;
        $orderData['trade_type'] = $tradeType;
        $orderData['payment_channel'] = $payModel->getPaymentChannel();
        $orderData['appid'] = $payModel->getAppId();
        $orderData['user_id'] = $userId;
        $orderData['order_source'] = CommonHandler::newInstance()->getPlatform($userInfo['platform']);

        $orderData['order_amount'] = $vcBuyConfigInfo['money'];
        $orderData['discount_amount'] = 0;
        $orderData['pay_amount'] = $price = $vcBuyConfigInfo['money'];
        $orderData['order_param'] = $vcAmount;
        $orderData['buy_time'] = time();
        $orderData['platform'] = $platform;
        $orderData['channel'] = $channel;
        if ($paymentMethod == 4) {  // 苹果支付记录对应苹果产品ID
            $orderData['channel_product_id'] = $vcBuyConfigInfo['apple_product_id'];
        }

        // 虚拟币订单扩展表保存
        $orderExtraData = [];
        $orderExtraData['order_num'] = $orderNum;
        $orderExtraData['buy_amount'] = $vcBuyConfigInfo['vc'];
        $orderExtraData['gift_amount'] = $vcBuyConfigInfo['gift_vc'];
        $orderExtraData['related_order_num'] = $relatedOrderNum;

        $res = $this->saveOrder($orderData, $orderExtraData, $orderType);
        if ($res !== true) {
            return $this->retError(3007);
        }

        if (in_array($paymentMethod, [4]) || in_array($tradeType, [3, 5, 6])) {  // 如果为 app 支付
            $appName = $this->_appSetting->getSettingByPath('constant:APP_NAME');
            $goodsName = $appName . '-料豆充值';
        } else {
            $payParams = new PayParams();
            $goodsName = $payParams->getVest();
        }
        $payRes = $payModel->pay($goodsName, $orderNum, $price);

        return $payRes;
    }

    public function applePayVerify($receipt, $transactionId) {

        // if (!($state === 'Purchased' || $state === 'Restored')) {
        //     return $this->retError('-1', '支付失败');
        // }

        // 获取订单信息
        $orderInfo = $this->getOrderInfo($transactionId);

        // 验证产品ID是否对应
        // if ($orderInfo['channel_product_id'] != $productId) {
        //     return $this->retError(-1, '支付产品无法对应');
        // }

        $resSandboxStatus = 21007;

        // 苹果正式环境验证
        $res = $this->sendAppleVerify($receipt);

        if ($res['status'] == $resSandboxStatus) {
            // 苹果沙盒环境验证
            $res = $this->sendAppleVerify($receipt, true);
        }

        if ($res['status'] != 0) {  // 验证失败
            return $this->retError(-1, 'apple return status: ' . $res['status']);
        }

        // 验证订单价格是否正确
        if (isset($res['receipt']['in_app'])) {
            $resProductId = $res['in_app']['product_id'];
        } else {
            $resProductId = $res['product_id'];
        }

        if ($orderInfo['channel_product_id'] != $resProductId) {
            $this->retError(-1, '支付产品无法对应');
        }

        return $this->retSuccess();
    }

    public function sendAppleVerify($receipt, $isSandbox = false) {

        $sandboxUrl = 'https://sandbox.itunes.apple.com/verifyReceipt';
        $formalUrl = 'https://buy.itunes.apple.com/verifyReceipt';

        if ($isSandbox) {
            $url = $sandboxUrl;
        } else {
            $url = $formalUrl;
        }

        $receipt = str_replace(["\n", "\r"], "", $receipt);
        $data = '{"receipt-data":"' . $receipt . '"}';

        $res = CommonHandler::newInstance()->httpPostRequest($url, $data);
        $res = json_decode($res, true);

        return $res;
    }


    public function checkUserBuyV2($userId, $resourceId) {
        if (!$userId || !$resourceId) {
            return false;
        }
        $dalOrder = new DALOrder($this->_appSetting);
        $condition = array('order_param' => $resourceId, 'order_status' => ['in', '(1, 3)']);

        $userModel = new UserModel();
        $userInfo = $userModel->getUserInfo($userId);
        if($userInfo['uuid']) {
          $users = $userModel->getUsersByUUid($userInfo['uuid']);
          $uids = implode(', ', array_column($users, 'user_id'));
          $condition['user_id'] = ['in', "($uids)"];
        }else {
          $condition['user_id'] = $userId;
        }

        $orderInfo = $dalOrder->getOrderByCondition($condition, array());
        if (!empty($orderInfo)) {
            return true;
        }
        return false;
    }

    public function orderByVcRefund($order) {
        $userModel = new UserModel();
        $vcRecordList = $userModel->getVcRecordByExt($order['order_id']);

        foreach ($vcRecordList as $one) {
            $userModel->userVcChange($one['user_id'], 1, $one['vc_amount'], $one['ext_params'], 11);
        }
        return true;
    }

    public function getUserPayAmount($user_id) {
        $dalOrder = new DALOrder($this->_appSetting);
        return $dalOrder->getUserAmount($user_id);
    }

}
