<?php
require(__DIR__ . "/cron.php");

use QK\HaoLiao\Common\CommonHandler;
use QK\WSF\Settings\AppSetting;
use QK\HaoLiao\Common\BaiDuParams;
use QK\HaoLiao\Model\ResourceModel;

//获取ID为5881和5853的料
$resourceModel = new ResourceModel();
$condition = array(
  'expert_id' => ['in', '(5881, 5853)'],
  'resource_status' => 1,
  'is_schedule_over' => 1
);
$orderBy = array('release_time' => 'desc');
$resourceList = $resourceModel->getResourceListV2($condition, array('resource_id'), 0, 0, $orderBy);
if(!empty($resourceList)) {
  $accessToken = getAccessToken();
  foreach($resourceList as $resource) {
    $resourceInfo = $resourceModel->getResourceDetailedInfo($resource['resource_id']);
    if($resourceInfo['bet_status'] == 1) {
      $resourceDetail = array(
        'access_token' => $accessToken,
        'resource_id' => $resourceInfo['resource_id'],
        'title' => $resourceInfo['title'],
        'expert_id' => $resourceInfo['expert_id'],
        'schedule' => $resourceInfo['detail'][0]['schedule'],
        'content' => $resourceInfo['detail'][0]['content'],
        'images' => $resourceInfo['detail'][0]['static'],
        'publish_time' => $resourceInfo['release_time']
      );
      submitResource($resourceDetail);
    }
  }
}

function getAccessToken() {
  $baiDuParams = new BaiDuParams();
  $programBaiDuSmallRoutineInfo = $baiDuParams->geBaiDuSmallRoutineParamsV2();

  $commonHandler = new CommonHandler();
  $accessTokenParams = array(
    'grant_type' => 'client_credentials',
    'client_id' => $programBaiDuSmallRoutineInfo['App-Key'],
    'client_secret' => $programBaiDuSmallRoutineInfo['App-Secret'],
    'scope' => 'smartapp_snsapi_base'
  );

  $accessTokenInfo = $commonHandler->httpPostRequest('https://openapi.baidu.com/oauth/2.0/token', $accessTokenParams);
  $accessTokenInfo = json_decode($accessTokenInfo, true);
  $accessToken = $accessTokenInfo['access_token'];
  return $accessToken;
}

function submitResource($resourceInfo) {
  $title = $resourceInfo['title'];
  $images = array();
  if(count($resourceInfo['images']) > 0) {
    foreach($resourceInfo['images'] as $image) {
      if($image['static_type'] == 1) {
        $images[] = $image['url'] . '?imageView2/1/w/750/h/750';
      }
      if(count($images) == 3)   break;
    }
    $url = 'https://openapi.baidu.com/rest/2.0/tieba/v1/tp/submitMaterial';
    $params = array(
      'access_token' => $resourceInfo['access_token'],
      'app_id' => '15817354',
      'title' => str_replace('精推', '专业分析', $title),
      'body' => $resourceInfo['content'],
      'images' => json_encode($images),
      'path' => '/pages/content/content?resource_id='. $resourceInfo['resource_id'],
      'mapp_type' => 1000,
      'mapp_sub_type' => 1001,
      'feed_type' => '体育',
      'feed_sub_type' => '足球',
      'tags' => '足球,篮球,中超,欧冠,英超,法甲,意甲,西甲,德甲,NBA',
      'ext' => json_encode(['publish_time' => date('Y年m月d日', $resourceInfo['publish_time'])])
    );
    $result = CommonHandler::newInstance()->httpPostRequest($url, $params);
    $result = json_decode($result, true);
    if($result['error_code'] != 0) {
      var_dump($resourceInfo['resource_id']);
      var_dump($result);
    }
  }
}
