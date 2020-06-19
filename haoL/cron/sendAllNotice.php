<?php
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

//TODO 默认走配置中的默认id
$weChatId = $GLOBALS['weChatId'] = $_appSetting->getConstantSetting('DEFAULT_WECHATID');

$userModel = new UserModel();
$expertModel = new ExpertModel();
$weChatParams = new WeChatParams();
$dalUserSubscribe = new DALUserSubscribe($_appSetting);

$appSetting = AppSetting::newInstance(APP_ROOT);
// 获取微信token
$accessToken = weChatToken($weChatId);
$weChatConfig = $weChatParams->getNewWeChatParams('', $weChatId);
$appId = $weChatConfig['id'];
$appKey = $weChatConfig['appKey'];

$weChatSendMessage = new WeChatSendMessage($appId, $appKey, $accessToken);

// 模版内容
$messageData = array();
$messageData['first'] = [
  'value' => '【好料精选】国庆特惠活动上线啦',
  'color' => '#2f84f2'
];
//料标题
$messageData['keyword1'] = [
  'value' => '庆祝伟大祖国70岁生日，好料国庆特惠，原168元专家精推，现一律88元，祝粉丝们国庆开森爆红收米米~',
];
//时间
$messageData['keyword2'] = [
  'value' => '2019-08-30 17:15',
];
$messageData['remark'] = [
  'value' => "点击查看详情。"
];

//$templateId = "U8MnFmK2ZHX0JjWutQ6pH-pQuE2hgud3ntUcsuicZD8";
$templateId = "8yFI2l9z-xnNXWXsx3luZnDo-w4HNv3TuXRcJLgXGGo";
//$url = $appSetting->getConstantSetting("DOMAIN_CUSTOMER") . "#/expertdetail?expert_id=24979";
//$url = "https://hl-static.haoliao188.com/news/201909/30/news_1569831177.html?title=好消息！好料十一黄金周一律特价送红单！&create_time=1569831177&views=2&platform=2&ce=customer.haoliao188.com";
$url = "https://customer.haoliao188.com/#/content?rid=9954&share=1";
$userList = array();
sendMsg($weChatId, $templateId, $userList, $messageData, $url, 1);
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

    $userList = $userModel->getWechatSubUsers();
    foreach ($userList as $key => $val) {
        $userId = $val['user_id'];
		if ($userId == 25231) {
			// 获取用户微信信息
			$userOpenId = $val['openid'];
			if (empty($userOpenId)) {
				//未关注此公众号的用户不发送消息
				continue;
			}
			var_dump($userId);
			$weChatSendMessage->sendMessage($userOpenId, $templateId, $messageData, $url);
		}
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
