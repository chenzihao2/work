<?php

namespace QK\HaoLiao\Model;

use QK\HaoLiao\Common\CommonHandler;
use QK\WSF\Settings\AppSetting;
use QK\HaoLiao\Common\BaiDuParams;
use QK\HaoLiao\Model\ResourceModel;

class FeedModel extends BaseModel {
  public function __construct() {
    parent::__construct();
  }

  public function publishResource($resource_id) {
    //获取ID为5881和5853的料
    $resourceModel = new ResourceModel();
    $accessToken = $this->getAccessToken();
    $resourceInfo = $resourceModel->getResourceDetailedInfo($resource_id);
    if(in_array($resourceInfo['expert_id'], [5881, 5853])) {
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
        $this->submitResource($resourceDetail);
      }
    }
  }

  public function publishNews($newsId) {
    $newsModel = new NewsModel();
    $accessToken = $this->getAccessToken();
    $newsInfo = $newsModel->getNewsInfo($newsId);
    if(!empty($newsInfo['icon'])) {

      $source = file_get_contents($newsInfo['url']);
      preg_match('/<div id="content" class="content">([\s\S]+)<\/div>/is', $source, $content_matches);
      $content = $content_matches[1];
      preg_match('/<p>([\s\S]+)<\/p>/is', $content, $content_detail_matches);
      $content = $content_detail_matches[1];
      $content = preg_replace('/直播吧/is', '', $content);
      //$content = preg_replace('/<div id="views_info">.*?<\/div>/is', '', $content);

      /*$htmlUrl = $newsInfo['url'] . '?title=' . urlencode($newsInfo['title']) .
                    '&target=' . urlencode($newsInfo['target']) .
                    '&create_time=' . $newsInfo['create_time'] .
                    '&views=' . $newsInfo['views'] .
                    '&platform=1';*/
      //$url = 'https://openapi.baidu.com/rest/2.0/smartapp/access/submitresource';
      $url = 'https://openapi.baidu.com/rest/2.0/tieba/v1/tp/submitMaterial';
	$params = array(
        'access_token' => $accessToken,
        'app_id' => '15817354',
        'title' => $newsInfo['title'],
        'body' => trim(strip_tags($content)),
        'images' => json_encode([$newsInfo['icon']]),
        'path' => '/pages/informatDetail/informatDetail?t=1&nid='. $newsId,
        'mapp_type' => 1000,
        'mapp_sub_type' => 1001,
        'feed_type' => '体育',
        'feed_sub_type' => '足球',
        'tags' => '足球,篮球,中超,欧冠,英超,法甲,意甲,西甲,德甲,NBA',
        'ext' => json_encode(['publish_time' => date('Y年m月d日', $newsInfo['create_time'])])
      );
      error_log(print_r($params, true));
      $result = CommonHandler::newInstance()->httpPostRequest($url, $params);
      return json_decode($result, true);
    }
  }

  private function getAccessToken() {
    $baiDuParams = new BaiDuParams();
    $programBaiDuSmallRoutineInfo = $baiDuParams->geBaiDuSmallRoutineParamsV2();

    $commonHandler = new CommonHandler();
    $accessTokenParams = array(
      'grant_type' => 'client_credentials',
      'client_id' => $programBaiDuSmallRoutineInfo['App-Key'],
      'client_secret' => $programBaiDuSmallRoutineInfo['App-Secret'],
      'scope' => 'smartapp_snsapi_base'
    );

    $accessTokenInfo = $commonHandler->httpPost('https://openapi.baidu.com/oauth/2.0/token', $accessTokenParams);
    $accessTokenInfo = json_decode($accessTokenInfo, true);
    $accessToken = $accessTokenInfo['access_token'];
    return $accessToken;
  }

  private function submitResource($resourceInfo) {
    $title = $resourceInfo['title'];
    $images = array();
    if(count($resourceInfo['images']) > 0) {
      foreach($resourceInfo['images'] as $image) {
        if($image['static_type'] == 1) {
          $images[] = $image['url'];
        }
        if(count($images) == 3)   break;
      }
      $url = 'https://openapi.baidu.com/rest/2.0/smartapp/access/submitresource';
      $params = array(
        'access_token' => $resourceInfo['access_token'],
        'app_id' => '15817354',
        'title' => $title,
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
    }
  }

}
