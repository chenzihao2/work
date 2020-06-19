<?php
/**
 * 5爱体育数据抓取
 * User: zyj
 * Date: 2019/12/7
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
$url = 'https://www.5aisport.com';
$tags = ['/article/football/121','/article/basketball/122'];



$cate=['篮球','国际足球','中国足球'];//分类id这个和上边 标签 一一对应
$categoryModel = new CategoryModel();


foreach($tags as $k=>$tag) {

    $data = QueryList::get($url . $tag)->rules([
        'name' => ['.news_nav_list>a', 'text'],
        'link' => ['.news_nav_list>a', 'href']
    ])->removeHead()->queryData();
    if($k==0){
        //国际足球
        $cateInfo= $categoryModel->getNameCategoryInfo($cate[1],1);

    }else{
        //篮球
        $cateInfo= $categoryModel->getNameCategoryInfo($cate[0],1);
    }

    foreach($data as $listpage) {
        if(in_array($listpage['name'],['全部','头条','赛事分析'])){
            continue;
        }
        if($listpage['name']=='中超'){
            $cateInfo= $categoryModel->getNameCategoryInfo($cate[2],1);
        }
        $subtype = $listpage['name'];

        $article_url = QueryList::get($listpage['link'])->rules([
            'article_link' => ['#news_page>li>.index_news_img>a', 'href'],
            'src' => ['#news_page>li>.index_news_img>a>img', 'src'],
            'name' => ['#news_page>li>.index_news_text>a>h3', 'text'],
            'original' => ['#news_page>li>.original', 'src']
        ])->removeHead()->queryData();

        // $article_url=array_slice($article_url,0,20);

        foreach($article_url as $val) {
            // if(isset($val['original'])&&$val['original']){
            // continue;
            // }

            $article_link = $url.$val['article_link'];

            $articleInfo = QueryList::get($article_link)->rules([
                'title' => ['.index_news_title', 'text'],
                'date' => ['.index_news_date>p:eq(1)', 'text'],
                'article_source' => ['.article_source', 'text'],
                'content' => ['.show_news_content', 'html']
            ])->removeHead()->queryData();

            $articleInfo[0]['n_type'] = $subtype;
            $articleInfo[0]['keywords'] = $subtype;
            $articleInfo[0]['src'] = $val['src'];//封面图地址

            if(!empty($articleInfo[0]) && !empty($articleInfo[0]['title'])
                && strpos($articleInfo[0]['article_source'], '直播吧') == false
                && strpos($articleInfo[0]['article_source'], '转载') == false){

                save($articleInfo[0],$cateInfo);
            }
        }
    }
}

function save($articleInfo,$cateInfo) {
    //转载，直播吧，独家
    if(!empty($articleInfo) && !empty($articleInfo['title'])
        && strpos($articleInfo['content'], '直播吧') == false
        && strpos($articleInfo['content'], '转载') == false) {
        //$ctime = mb_substr($articleInfo['date'], mb_strlen('时间：'), 19);
        if(!isset($articleInfo['date'])){
            $ctime = time();
        }else{
            $ctime = strtotime($articleInfo['date']);
        }


        $target = '互联网';
        $newsModel = new NewsModel();
        $where=" title='{$articleInfo['title']}'";
        $n=$newsModel->getNameNewsInfo($where);//标题相同的文章

        if($n){
            dump('重复的:'.$articleInfo['title']);
            return;
        }
        //根据封面图 去重
        if(isset($articleInfo['src']) && $articleInfo['src']){
            $path=parse_url($articleInfo['src']);
            $coverUrl = 'https://hl-static.haoliao188.com' .DIRECTORY_SEPARATOR. $path['path'];

            $where=" icon='{$coverUrl}'";
            $c=$newsModel->getNameNewsInfo($where);//封面图相同
            dump($coverUrl);
            if($c){
                dump('封面重复的:'.$articleInfo['title']);
                return;
            }
        }


        list($content_url, $icon_url) = handleContent($articleInfo['content']);
        if($articleInfo['src']){
            $path=parse_url($articleInfo['src']);
            $cover_url = rs_fetch($articleInfo['src'], $path['path']);//上传 封面图
            $icon_url=$cover_url?$cover_url:$icon_url;
        }


        $insertData = array(
            'title' => $articleInfo['title'],
            'ctime' =>$ctime,
            'url' => $content_url,
            'icon' => $icon_url,
            'n_type' => $articleInfo['n_type'],
            'keywords' => $articleInfo['keywords'],
            'source' => 5,
            'status' => 1,
            'base_views' => 0,
            'description' => '',
            'target' => $target,
            'article_source' => isset($articleInfo['article_source'])&&$articleInfo['article_source']?$articleInfo['article_source']:'',
            'money' => 0,
            'is_pay' =>0,
            'cid' =>$cateInfo?$cateInfo['id']:0,
        );

        $res = $newsModel->createNews($insertData);
        dump($res);

    }
}

function handleContent($content) {
    $filespath = QueryList::html($content)->find('img')->map(function($item){
        return $item->src;
    });

    $icon_url = '';
    foreach($filespath as $index => $file) {
        $path=parse_url($file);
        $current_url = rs_fetch($file, $path['path']);

        if ($index == 0) {
            $icon_url = $current_url;
        }
        $content = str_replace($file, $current_url, $content);
    }
    $patternStr = array('\r\n', '\n', '\r');
    $content=str_replace($patternStr, '<br/>', $content);

    $content = str_replace('class="img_descr"', 'style="margin:20px auto 0px auto;padding:5px 0;display:inline-block;line-height:20px;font-size:14px;font-weight:bold;text-align:center;zoom:1;"', $content);
    //$content = preg_replace('/<span.+class="img_descr"[^<|>]*>.*?<\/span>/is', '', $content);
    $content = preg_replace("/<a[^>]*>(.*?)<\/a>/is", "$1", $content);

    $source = file_get_contents(APP_ROOT.'/static/news_tmpl/news_tmpl.html');
    $source = preg_replace('/<div id="content" class="content">.*?<\/div>/is', '<div id="content" class="content">'.$content. '</div>', $source, 1);



    $file_name = 'jqty_news_'.time().rand(1,100000).'.html';
    $newsModel = new NewsModel();
    $contentUrl = $newsModel->uploadNews($file_name, $source);
    return [$contentUrl,$icon_url];
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
        // $res = 'https://hl-static.haoliao188.com' . $key;
        return $res;
    }
}
