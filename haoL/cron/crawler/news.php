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
use QK\HaoLiao\Model\CategoryModel;
use QK\WSF\Settings\AppSetting;
use Qiniu\Auth;
use Qiniu\Storage\UploadManager;
use Qiniu\Storage\BucketManager;

set_time_limit(120);
//ini_set('memory_limit', '8M');
date_default_timezone_set('PRC');

$url = 'http://ww.jqw007.net';
$tags = ['/basketball/', '/world/', '/china/','/allsports/'];
//$cate=[11,13,6,12];//分类id这个和上边 标签 一一对应
$cate=['篮球','国际足球','中国足球','其它'];//分类id这个和上边 标签 一一对应
$categoryModel = new CategoryModel();


foreach($tags as $k=>$tag) {
    if($k==0){
        continue;
    }
  $data = QueryList::get($url . $tag)->rules([
    'sub_type' => ['tr>td:eq(0)>strong', 'text'],
    'link' => ['tr>td:eq(1)>a', 'href']
  ])->range('.news_list>.title')->encoding('UTF-8','gb2312')->removeHead()->queryData();


    $cateInfo= $categoryModel->getNameCategoryInfo($cate[$k],1);

  foreach($data as $listpage) {
     
    $subtype = $listpage['sub_type'];
    $article_url = QueryList::get($listpage['link'])->rules([
      'article_link' => ['a', 'href']
    ])->range('.news_list>.box>tr>td>ul>li')->encoding('UTF-8','gb2312')->removeHead()->queryData();
    $article_url=array_slice($article_url,0,20);
    foreach($article_url as $val) {
      $article_link = $val['article_link'];
      $articleInfo = QueryList::get($article_link)->rules([
        'title' => ['.hd>h1', 'text'],
        'info' => ['.hd>.titBar>.info', 'text'],
        'content' => ['.bd>#Cnt-Main', 'html']
      ])->range('#Article')->encoding('UTF-8','gb2312')->removeHead()->queryData();

      $articleInfo[0]['n_type'] = $subtype;
      $articleInfo[0]['keywords'] = $subtype;
      save($articleInfo[0],$cateInfo);
      //sleep(1);
    }
  }
}

function save($articleInfo,$cateInfo) {
    //转载，直播吧，独家
  $url = 'http://ww.jqw007.net';
  if(!empty($articleInfo) && !empty($articleInfo['title']) 
	&& strpos($articleInfo['content'], '.gif') == false 
	&& strpos($articleInfo['content'], 'http://') == false
	&& strpos($articleInfo['content'], 'https://') == false
    && strpos($articleInfo['content'], '直播吧') == false
    && strpos($articleInfo['content'], '转载') == false
    && strpos($articleInfo['content'], '独家') == false) {
    $ctime = mb_substr($articleInfo['info'], mb_strlen('时间：'), 19);
    $target = '互联网';
      $newsModel = new NewsModel();
      /*
        if(strtotime("-3 hour") <= strtotime($ctime)) {
            $condition = array('source' => 5, 'title' => $articleInfo['title']);
            $recentNews = $newsModel->getNewsList($condition, array(), 0, 1, array('create_time' => 'desc'));
            if(!empty($recentNews['list']) && $recentNews['list'][0]['create_time'] >= strtotime($ctime)){
              return;
            }
        }
        */




    //根据时间去重
     // $where=" create_time=".strtotime($ctime);
          $where=" title='{$articleInfo['title']}'";
          $n=$newsModel->getNameNewsInfo($where);//标题相同的文章

          if($n){
               dump('重复的:'.$articleInfo['title']);
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
                'target' => $target,
                'money' => 0,
                'is_pay' =>0,
                'cid' =>$cateInfo?$cateInfo['id']:0,
              );

              $res = $newsModel->createNews($insertData);


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
  $content = str_replace('class="img_descr"', 'style="margin:20px auto 0px auto;padding:5px 0;display:inline-block;line-height:20px;font-size:14px;font-weight:bold;text-align:center;zoom:1;"', $content);
  //$content = preg_replace('/<span.+class="img_descr"[^<|>]*>.*?<\/span>/is', '', $content);

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
