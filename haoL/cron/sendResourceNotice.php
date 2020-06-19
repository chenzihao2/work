<?php
/**
 * 通知关注专家
 * User: YangChao
 * Date: 2018/11/21
 */

require(__DIR__ . "/cron.php");

use QK\WSF\Settings\AppSetting;
use QK\HaoLiao\Model\RedisModel;
use QK\HaoLiao\Model\ResourceModel;
use QK\HaoLiao\Model\ExpertModel;
use QK\HaoLiao\DAL\DALUserFollowExpert;
use QK\HaoLiao\DAL\DALUserFollowSchedule;
use QK\HaoLiao\DAL\DALUserSubscribe;
use QK\HaoLiao\Model\UserModel;
use QK\WeChat\WeChatToken;
use QK\HaoLiao\Common\WeChatParams;
use QK\WeChat\WeChatSendMessage;
use QK\HaoLiao\DAL\DALOrder;
use QK\HaoLiao\DAL\DALResource;

// 新发布通知
$resourceNoticeList = RESOURCE_NOTICE_LIST;

$_appSetting = AppSetting::newInstance(AppRoot);

//$weChatId = $GLOBALS['weChatId'] = $argv[1];
//TODO 默认走配置中的默认id
$weChatId = $GLOBALS['weChatId'] = $_appSetting->getConstantSetting('DEFAULT_WECHATID');

$resourceRedisModel = new RedisModel('resource');
$resourceModel = new ResourceModel();
$userModel = new UserModel();
$expertModel = new ExpertModel();
$weChatParams = new WeChatParams();
$dalUserFollowExpert = new DALUserFollowExpert($_appSetting);
$dalUserSubscribe = new DALUserSubscribe($_appSetting);
$dalUserFollowSchedule = new DALUserFollowSchedule($_appSetting);
$dalOrder = new DALOrder($_appSetting);
$dalResource = new DALResource($_appSetting);

$appSetting = AppSetting::newInstance(APP_ROOT);

// 获取微信token
$accessToken = weChatToken($weChatId);
$weChatConfig = $weChatParams->getNewWeChatParams('', $weChatId);
$appId = $weChatConfig['id'];
$appKey = $weChatConfig['appKey'];

$weChatSendMessage = new WeChatSendMessage($appId, $appKey, $accessToken);

$startTime = time() - 300;
$endTime = time();
$limitedResourceList = $dalResource->getLimitedResourceList($startTime, $endTime);
foreach($limitedResourceList as $key => $val){
    // 获取料信息
    $resourceInfo = $val;
    $resourceId = $resourceInfo['resource_id'];

    // 购买过料的用户列表
    $orderList =  $dalOrder->getResourceOrder($resourceId);

    // 模版内容
    $messageData = array();
    $messageData['first'] = [
        'value' => '您购买的临场方案已更新！',
        'color' => '#2f84f2'
    ];
    //料标题
    $messageData['keyword1'] = [
        'value' => $resourceInfo['title'],
    ];
    //时间
    $messageData['keyword2'] = [
        'value' => date("Y-m-d H:i", $resourceInfo['limited_time']),
    ];
    $messageData['remark'] = [
        'value' => "点击查看详情。"
    ];

    // 生产环境模版ID
    $templateId = "U8MnFmK2ZHX0JjWutQ6pH-pQuE2hgud3ntUcsuicZD8";

    // 开发环境模版ID
    // $templateId = "e4yWU7WcR3-F20BrE_mV1Imc5a2pRN_nuooLpA4Ir9w";

    // $url = $appSetting->getConstantSetting("DOMAIN_CUSTOMER") . "#/pages/content/index?rid=" . $resourceId;
    $url = $appSetting->getConstantSetting("DOMAIN_CUSTOMER") . "#/content?rid=" . $resourceId;
    sendMsg($weChatId, $templateId, $orderList, $messageData, $url, 1);
}


while ($resourceId = $resourceRedisModel->redisRpop($resourceNoticeList)) {
    // 获取料信息
    $resourceInfo = $resourceModel->getResourceInfo($resourceId);
    // 料类型
    $resourcePre = '';
    if ($resourceInfo['is_groupbuy'] == 1) {
        $resourcePre = '合买';
    }
    // 专家ID
    $expertId = $resourceInfo['expert_id'];
    $expertInfo = $expertModel->getExpertInfo($expertId);

    //订阅此专家的用户列表
    $expertSubscriptionList = $dalUserSubscribe->getExpertSubscribeList($expertId);
    // 模版内容
    $messageData = array();
    $messageData['first'] = [
        'value' => '【' . $expertInfo['expert_name'] .'】有新' . $resourcePre . '方案上线！',
        'color' => '#2f84f2'
    ];
    //料标题
    $messageData['keyword1'] = [
        'value' => $resourceInfo['title'],
    ];
    //发布时间
    $messageData['keyword2'] = [
        'value' => date("Y-m-d H:i", $resourceInfo['release_time']),
    ];
    $messageData['remark'] = [
        'value' => "点击查看详情。"
    ];

    // 生产环境模版ID
    //$templateId = "U8MnFmK2ZHX0JjWutQ6pH-pQuE2hgud3ntUcsuicZD8";
    $templateId = "8yFI2l9z-xnNXWXsx3luZnDo-w4HNv3TuXRcJLgXGGo";
    // 开发环境模版ID
    // $templateId = "e4yWU7WcR3-F20BrE_mV1Imc5a2pRN_nuooLpA4Ir9w";

    // $url = $appSetting->getConstantSetting("DOMAIN_CUSTOMER") . "#/pages/content/index?rid=" . $resourceId;
    $url = $appSetting->getConstantSetting("DOMAIN_CUSTOMER") . "#/content?rid=" . $resourceId.'&share=1';
    sendMsg($weChatId, $templateId, $expertSubscriptionList, $messageData, $url, 1);

    // 获取关注专家的用户列表
    $expertFollowList = $dalUserFollowExpert->getExpertFollowList($expertId);
    //关注列表去除订阅用户
    $subscriptionList = [];
    foreach ($expertSubscriptionList as $key => $val) {
        $subscriptionList[] = $val['user_id'];
    }
    foreach ($expertFollowList as $key => $val) {
        if (in_array($val['user_id'], $subscriptionList)) {
            unset($expertFollowList[$key]);
        }
    }
    // 模版内容
    $messageData = array();
    $messageData['first'] = [
        'value' => '【' . $expertInfo['expert_name'] .'】有新' . $resourcePre . '方案上线！',
        'color' => '#2f84f2'
    ];
    //料标题
    $messageData['keyword1'] = [
        'value' => $resourceInfo['title'],
    ];
    //发布时间
    $messageData['keyword2'] = [
        'value' => date("Y-m-d H:i", $resourceInfo['release_time']),
    ];
    $messageData['remark'] = [
        'value' => "如果不想收到通知，点击进入页面，取消关注作者即可。"
    ];

    // 生产环境模版ID
    //$templateId = "U8MnFmK2ZHX0JjWutQ6pH-pQuE2hgud3ntUcsuicZD8";//该模板被封
    $templateId = "8yFI2l9z-xnNXWXsx3luZnDo-w4HNv3TuXRcJLgXGGo";
    // 开发环境模版ID
    // $templateId = "e4yWU7WcR3-F20BrE_mV1Imc5a2pRN_nuooLpA4Ir9w";

    // $url = $appSetting->getConstantSetting("DOMAIN_CUSTOMER") . "#/pages/content/index?rid=" . $resourceId;
    $url = $appSetting->getConstantSetting("DOMAIN_CUSTOMER") . "#/content?rid=" . $resourceId.'&share=1';
    sendMsg($weChatId, $templateId, $expertFollowList, $messageData, $url, 1);

    // 获取料关联赛事
    /*
    $scheduleList = $resourceModel->getResourceScheduleList($resourceId);
    foreach ($scheduleList as $key => $val) {
        $scheduleId = $val['schedule_id'];
        $scheduleFollowList = $dalUserFollowSchedule->getScheduleFollowList($scheduleId);
        // 模版内容
        $messageData = array();
        $messageData['first'] = [
            'value' => "您关注的赛事：" . $val['schedule_date'] . " " . $val['schedule_hour'] . "  " . $val['league_name'] . "  " . $val['master_team'] . "vs" . $val['guest_team'] . " 有了新的专家分析，请您及时查看。", 'color' => '#008fff'
        ];
        //信息详情
        $messageData['keyword1'] = [
            'value' => $resourceInfo['title'], 'color' => '#ff0000'
        ];
        //信息详情
        $messageData['keyword2'] = [
            'value' => $expertInfo['expert_name'],
        ];
        //发布时间
        $messageData['remark'] = [
            'value' => "作品方案已经圆满完成！本消息通知模板每日最多发送3次，若您仍觉得打扰，可取消关注。"
        ];

        $url = $appSetting->getConstantSetting("DOMAIN_CUSTOMER") . "#/pages/content/index?rid=" . $resourceInfo['resource_id'];
        $templateId = "VkYhxoXw4oUdiYb-zEKphQZLxT6kwBNCj50kmsyKvT4";
        sendMsg($weChatId, $templateId, $scheduleFollowList, $messageData, $url, 2);
    }
    */
}

// 更新通知
$resourceUpdateNoticeList = RESOURCE_UPDATE_NOTICE_LIST;
while ($resourceId = $resourceRedisModel->redisRpop($resourceUpdateNoticeList)) {
    // 获取料信息
    $resourceInfo = $resourceModel->getResourceInfo($resourceId);
    // 专家ID
    $expertId = $resourceInfo['expert_id'];
    $expertInfo = $expertModel->getExpertInfo($expertId);

    // 购买过料的用户列表
    $orderList =  $dalOrder->getResourceOrder($resourceId);

    // 模版内容
    $messageData = array();
    $messageData['first'] = [
        'value' => '您购买【' . $expertInfo['expert_name'] .'】的方案已更新！',
        'color' => '#2f84f2'
    ];
    //料标题
    $messageData['keyword1'] = [
        'value' => $resourceInfo['title'],
    ];
    //发布时间
    $messageData['keyword2'] = [
        'value' => date("Y-m-d H:i", $resourceInfo['modify_time']),
    ];
    $messageData['remark'] = [
        'value' => "点击查看详情。"
    ];

    // 生产环境模版ID
    $templateId = "U8MnFmK2ZHX0JjWutQ6pH-pQuE2hgud3ntUcsuicZD8";

    // 开发环境模版ID
    // $templateId = "e4yWU7WcR3-F20BrE_mV1Imc5a2pRN_nuooLpA4Ir9w";

    // $url = $appSetting->getConstantSetting("DOMAIN_CUSTOMER") . "#/pages/content/index?rid=" . $resourceId;
    $url = $appSetting->getConstantSetting("DOMAIN_CUSTOMER") . "#/content?rid=" . $resourceId;
    sendMsg($weChatId, $templateId, $orderList, $messageData, $url, 1);

}

/**
 * 发送合买成功信息
 */
$groupNoticeSuccess =  RESOURCE_GROUP_NOTICE_SUCCESS;
while ($resourceId = $resourceRedisModel->redisRpop($groupNoticeSuccess)) {
    // 获取料信息
    $resourceInfo = $resourceModel->getResourceInfo($resourceId);
    // 专家ID
    $expertId = $resourceInfo['expert_id'];
    $expertInfo = $expertModel->getExpertInfo($expertId);
    // 获取合买信息
    $resourceGroupInfo = $resourceModel->getResourceGroupInfo($resourceId);

    // 购买过料的用户列表
    $orderList =  $dalOrder->getResourceOrder($resourceId);

    // 模版内容
    $messageData = array();
    $messageData['first'] = [
        'value' => '您购买【' . $expertInfo['expert_name'] .'】的合买方案已开单！',
        'color' => '#2f84f2'
    ];
    //料标题
    $messageData['keyword1'] = [
        'value' => $resourceInfo['title'],
    ];
    //发布时间
    $messageData['keyword2'] = [
        'value' => date("Y-m-d H:i", $resourceGroupInfo['over_time']),
    ];
    $messageData['remark'] = [
        'value' => "点击查看详情。"
    ];

    // 生产环境模版ID
    $templateId = "U8MnFmK2ZHX0JjWutQ6pH-pQuE2hgud3ntUcsuicZD8";

    // 开发环境模版ID
    // $templateId = "e4yWU7WcR3-F20BrE_mV1Imc5a2pRN_nuooLpA4Ir9w";

    $url = $appSetting->getConstantSetting("DOMAIN_CUSTOMER") . "#/content?rid=" . $resourceId;
    // $url = $appSetting->getConstantSetting("DOMAIN_CUSTOMER") . "#/pages/content/index?rid=" . $resourceId;
    sendMsg($weChatId, $templateId, $orderList, $messageData, $url, 1);

}

/**
 * 发送模版消息
 * @param $weChatId
 * @param $templateId
 * @param $userList
 * @param $messageData
 * @param $url
 */
function sendMsg($weChatId, $templateId, $userList, $messageData, $url) {
    $userModel = new UserModel();
    $weChatParams = new WeChatParams();
    $accessToken = weChatToken($weChatId);
    $weChatConfig = $weChatParams->getNewWeChatParams('', $weChatId);
    $appId = $weChatConfig['id'];
    $appKey = $weChatConfig['appKey'];
    $weChatSendMessage = new WeChatSendMessage($appId, $appKey, $accessToken);
    foreach ($userList as $key => $val) {
        $userId = $val['user_id'];
        // 获取用户微信信息
        $userWeChatInfo = $userModel->getUserWeChatInfo($userId);
        $userOpenId = $userWeChatInfo['openid'];
        if (empty($userOpenId)) {
            //未关注此公众号的用户不发送消息
            continue;
        }
        $weChatSendMessage->sendMessage($userOpenId, $templateId, $messageData, $url);

        //发送次数检查
        /*
        if ($type == 1) {
            $redisKey = WECHAT_NOTICE_EXPERT . $userId;
        } elseif ($type == 2) {
            $redisKey = WECHAT_NOTICE_MATCH . $userId;
        }
        $redisModel = new RedisModel('wechat');
        $times = $redisModel->redisGet($redisKey);
        if ($times < 3) {
            $weChatSendMessage->sendMessage($userOpenId, $templateId, $messageData, $url);
        }
        $redisModel->redisIncr($redisKey);
        $timestamp = strtotime(date("Y-m-d 23:59:59", time()));
        $redisModel->redisExpireAt($redisKey, $timestamp);
        */
    }
}

/**
 * 微信AccessToken获取
 * @param $weChatId
 * @return bool|mixed|null|string
 */
function weChatToken($weChatId) {
    $accessTokenRedisKey = "Access_Token_" . $weChatId;
    $redisModel = new RedisModel('wechat');
    $accessToken = $redisModel->redisGet($accessTokenRedisKey);
    if (empty($accessToken)) {
        $weChatParam = new WeChatParams();
        $weChatParams = $weChatParam->getNewWeChatParams('', $weChatId);
        $appId = $weChatParams['id'];
        $appKey = $weChatParams['appKey'];
        $token = new WeChatToken($appId, $appKey);
        $tokenInfo = $token->getToken();
        if (array_key_exists('code', $tokenInfo)) {
            return false;
        } else {
            $redisModel->redisSet($accessTokenRedisKey, $tokenInfo['access_token'], 7150);
            $accessToken = $tokenInfo['access_token'];
        }
    }
    return $accessToken;
}
