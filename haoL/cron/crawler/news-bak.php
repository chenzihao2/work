<?php
/**
 * 足球数据抓取
 * User: WangHui
 * Date: 2018/10/31
 * Time: 上午10:29
 */
require(__DIR__ . "/../cron.php");

use QL\QueryList;
use QK\HaoLiao\Common\CommonHandler;
use QK\HaoLiao\Model\NewsModel;
use QK\WSF\Settings\AppSetting;
use Qiniu\Auth;
use Qiniu\Storage\UploadManager;
use Qiniu\Storage\BucketManager;


date_default_timezone_set('PRC');

$url = 'http://ww.jqw007.net';
$tags = ['/basketball/', '/world/', '/china/'];
//$tags = ['/basketball/'];
foreach($tags as $tag) {
  $data = QueryList::get($url . $tag)->rules([
    'sub_type' => ['tr>td:eq(0)>strong', 'text'],
    'link' => ['tr>td:eq(1)>a', 'href']
  ])->range('.news_list>.title')->encoding('UTF-8','gb2312')->removeHead()->queryData();

  foreach($data as $listpage) {
    $subtype = $listpage['sub_type'];
    $article_url = QueryList::get($listpage['link'])->rules([
      'article_link' => ['a', 'href']
    ])->range('.news_list>.box>tr>td>ul>li')->encoding('UTF-8','gb2312')->removeHead()->queryData();
    foreach($article_url as $val) {
      $article_link = $val['article_link'];
      $articleInfo = QueryList::get($article_link)->rules([
        'title' => ['.hd>h1', 'text'],
        'info' => ['.hd>.titBar>.info', 'text'],
        'content' => ['.bd>#Cnt-Main', 'html']
      ])->range('#Article')->encoding('UTF-8','gb2312')->removeHead()->queryData();
      $articleInfo[0]['n_type'] = $subtype;
      $articleInfo[0]['keywords'] = $subtype;
      save($articleInfo[0]);
    }
  }
}

function save($articleInfo) {
  $url = 'http://ww.jqw007.net';
  if(!empty($articleInfo) && !empty($articleInfo['title']) 
	&& strpos($articleInfo['content'], '.gif') == false 
	&& strpos($articleInfo['content'], 'http://') == false
	&& strpos($articleInfo['content'], 'https://') == false) {
    $ctime = mb_substr($articleInfo['info'], mb_strlen('时间：'), 19);
    $target = '互联网';
    if(strtotime("-3 hour") <= strtotime($ctime)) {
      $newsModel = new NewsModel();
      $condition = array('source' => 5, 'title' => $articleInfo['title']);
      $recentNews = $newsModel->getNewsList($condition, array(), 0, 1, array('create_time' => 'desc'));
      if(!empty($recentNews['list']) && $recentNews['list'][0]['create_time'] >= strtotime($ctime)){
        return;
      }

      list($content_url, $icon_url) = handleContent($articleInfo['content']);
      $insertData = array(
        'title' => $articleInfo['title'],
        'ctime' => strtotime($ctime),
        'url' => $content_url,
        'icon' => $icon_url,
        'n_type' => $articleInfo['n_type'],
        'keywords' => $articleInfo['keywords'],
        'source' => 5,
        'status' => 1,
        'base_views' => 0,
        'description' => '',
        'target' => $target
      );
	//var_dump($insertData);
      $res = $newsModel->createNews($insertData);
    }
  }
}

function handleContent($content) {
  $url = 'http://ww.jqw007.net';
  $filespath = QueryList::html($content)->find('img')->map(function($item){
    return $item->src;
  });

  $icon_url = '';
  foreach($filespath as $index => $file) {
    $current_url = rs_fetch($url . $file, substr($file, 1));
    if ($index == 0) {
      $icon_url = $current_url;
    }
    $content = str_replace($file, $current_url, $content);
  }
  $patternStr = array('\r\n', '\n', '\r');
  $content=str_replace($patternStr, '<br/>', $content);
  $content = preg_replace('/<span.+class="img_descr"[^<|>]*>.*?<\/span>/is', '', $content);

  $source = file_get_contents(APP_ROOT.'/static/news_tmpl/news_tmpl.html');
  $source = preg_replace('/<div id="content" class="content">.*?<\/div>/is', '<div id="content" class="content">'.$content. '</div>', $source, 1);
  $file_name = 'jqty_news_'.time().rand(1,100000).'.html';
  $newsModel = new NewsModel();
  $contentUrl = $newsModel->uploadNews($file_name, $source);
  return [$contentUrl, $icon_url];
}

function rs_fetch($url, $key){
  $accessKey = AppSetting::newInstance(APP_ROOT)->getConstantSetting('QiNiu-PUBLIC-KEY');
  $secretKey = AppSetting::newInstance(APP_ROOT)->getConstantSetting('QiNiu-PRIVATE-KEY');
  $auth = new Auth($accessKey, $secretKey);
  $bucket = AppSetting::newInstance(APP_ROOT)->getConstantSetting('QiNiu-BUCKET');
  $bucketManager = new BucketManager($auth);

  list($ret, $err) = $bucketManager->fetch($url, $bucket, $key);
  if ($err !== null) {
    return "";
  } else {
    $res = 'https://hl-static.haoliao188.com' . DIRECTORY_SEPARATOR . $key;
    return $res;
  }
}
