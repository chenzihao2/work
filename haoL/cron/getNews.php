<?php
/**
 *
 */
require(__DIR__ . "/cron.php");

use QK\HaoLiao\Common\CommonHandler;
use QK\HaoLiao\Model\NewsModel;
use QK\WSF\Settings\AppSetting;

//直播吧
$zbb_list_url = 'https://api.qiumibao.com/application/app/';

$zbb_list_params = array(
  '_url' => '/news/list',
  'date' => date('Y-m-d')
);

$types = array('足球', 'NBA', 'CBA');
foreach($types as $label) {
  $zbb_list_params['label'] = $label;
  $zbb_list = CommonHandler::newInstance()->httpGetRequest($zbb_list_url, $zbb_list_params);
  $zbb_list = json_decode($zbb_list, true);
  if ($zbb_list['status'] == 'success') {
    if(isset($zbb_list['data'])) {
      $data = $zbb_list['data'];
      foreach($data['list'] as $key => $value) {
        handleZBBDetails($value);
      }
    }
  }
}

//中国竞彩网
/*$jcw_list_url = 'https://i.sporttery.cn/zfapp_v1_1/news_info/get_news_info';
$jcw_list_params = array(
  'type' => 6,
  'count' => 50,
  'start' => 0,
  //'_' => time()*1000
);
$jcw_list = CommonHandler::newInstance()->httpGetRequest($jcw_list_url, $jcw_list_params);
$jcw_list = json_decode($jcw_list, true);
if ($jcw_list['status']['code'] == 1001) {
  foreach($jcw_list['data'] as $jcw) {
    handleJCWDetails($jcw);
  }
}*/


function handleZBBDetails($zbb_data) {
  $zbb_detail_prefix = 'https://s.qiumibao.com/news/ios/json';
  $detail_url = $zbb_detail_prefix.$zbb_data['url'];
  $detail_res = CommonHandler::newInstance()->httpGetRequest($detail_url, []);
  $detail_res = json_decode($detail_res, true);
  if(!empty($detail_res)) {
    if((date('H') -1) == date('H', strtotime($detail_res['createtime']))) {
      $newsModel = new NewsModel();
      $condition = array('source' => 1);
      $recentNews = $newsModel->getNewsList($condition, array(), 0, 1, array('create_time' => 'desc'));
      if(!empty($recentNews) && $recentNews['list'][0]['create_time'] >= strtotime($detail_res['createtime'])){
        return;
      }

      $content_url = handleZBBContent($detail_res['content']);

      $insertData = array(
        'title' => $detail_res['title'],
        'ctime' => strtotime($detail_res['createtime']),
        'url' => $content_url,
        'icon' => (isset($detail_res['thumbnail']) && !empty($detail_res['thumbnail'])) ? $detail_res['thumbnail'] : '',
        'n_type' => $zbb_data['type'],
        'keywords' => $detail_res['labels'],
        'source' => 1,
        'status' => 1,
        'base_views' => 0,
        'description' => $detail_res['description'],
        'target' => $detail_res['from_name']
      );
      $res = $newsModel->createNews($insertData);
    }
  }
}

function handleJCWDetails($jcw) {
  $aid = $jcw['aid'];
  $jcw_detail_url = 'https://i.sporttery.cn/zfapp_v1_1/news_info/get_news_url';
  $jcw_detail_res = CommonHandler::newInstance()->httpGetRequest($jcw_detail_url, ['aid' => $aid]);
  if($jcw_detail_res != null) {
    $jcw_detail_res = str_replace('contProcess(', '', $jcw_detail_res);
    $jcw_detail_res = json_decode(rtrim($jcw_detail_res, ');'), true);
    if((date('H') -1) == date('H', $jcw_detail_res['newstime'])) {
      $newsModel = new NewsModel();
      $condition = array('source' => 0);
      $recentNews = $newsModel->getNewsList(array(), array(), 0, 1, array('create_time' => 'desc'));
      if(!empty($recentNews) && $recentNews['list'][0]['create_time'] >= $jcw_detail_res['newstime']){
        return;
      }
      $content = $jcw_detail_res['content'];
      $patternStr = array('\r\n', '\n', '\r');
      $content=str_replace($patternStr, '<br/>', $content);
      $source = file_get_contents(APP_ROOT.'/static/news_tmpl/news_tmpl.html');
      $source = preg_replace('/<div id="content" class="content">.*?<\/div>/is', '<div id="content" class="content">'.$content. '</div>', $source, 1);
      $file_name = 'jcw_news_'.$aid.'.html';
      $content_url = $newsModel->uploadNews($file_name, $source);
    
      if($content_url != null) {
        $insertData = array(
          'title' => $jcw_detail_res['title'],
          'ctime' => $jcw_detail_res['newstime'],
          'url' => $content_url,
          'icon' => (isset($jcw['litpic']) && !empty($jcw['litpic'])) ? 'https://static.sporttery.cn'.$jcw['litpic'] : '',
          'n_type' => $jcw_detail_res['typename'],
          'source' => 0,
          'keywords' => '',
          'status' => 1,
          'base_views' => 0,
          'description' => $jcw['adesc'],
          'target' => $jcw['asource']
        );
        $res = $newsModel->createNews($insertData);
      }
    }
  }
}

function handleZBBContent($content) {
  $patternStr = array('\r\n', '\n', '\r');
  $content=str_replace($patternStr, '<br/>', $content);
  $content = preg_replace('/<script[^<|>]*>.*?<\/script>/is', '', $content);
  $content = preg_replace('/<video[^<|>]*>.*?<\/video>/is', '', $content);
  $content = preg_replace('/<a[^<|>]*>.*?<\/a>/is', '', $content);
  $content = preg_replace('/<div class="play_btn"[^<|>]*>.*?<\/div>/is', '', $content);
  $content = preg_replace('/(<img.+onload=\"?)(.*?)(\".+>)/i', "\${1}\${3}", $content);
  $content = preg_replace('/(<img.+style=\"?)(.*?)(\".+>)/i',"\${1} \${2} height:100%;\${3}", $content);
  $content = preg_replace('/直播吧/is', '', $content);
  $content = preg_replace('/t-rc/is', 'src', $content);
  $content = preg_replace('/https/is', 'http', $content);
  $content = preg_replace('/http/is', 'https', $content);
  $content = preg_replace('/display: none;/is', '', $content);
  
  $source = file_get_contents(APP_ROOT.'/static/news_tmpl/news_tmpl.html');
  $source = preg_replace('/<div id="content" class="content">.*?<\/div>/is', '<div id="content" class="content">'.$content. '</div>', $source, 1);
  $file_name = 'zbbnews_'.time().rand(1,100000).'.html';
  $newsModel = new NewsModel();
  $contentUrl = $newsModel->uploadNews($file_name, $source);
  return $contentUrl;
}

