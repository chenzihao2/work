<?php
/**
 * banner相关接口
 * User: zwh
 * Date: 2019/03/22
 */

namespace QK\HaoLiao\Controllers\User\V2;

use QK\HaoLiao\Controllers\User\Base\NewsController as News;
use QK\HaoLiao\Model\NewsModel;
use QK\HaoLiao\Model\MatchModel;
use QK\HaoLiao\Model\CategoryModel;
use QK\HaoLiao\DAL\DALOrder;
use QL\QueryList;
class NewsController extends News{
    /**
     * 资讯列表
     */
    public function newsList(){
        $param = $this->checkApiParam([], ['lastId' => '', 'pagesize' => 20, 'cid' => 0, 'platform' => 2,'user_id'=>0]);
		if($param['user_id']){
            $this->checkToken();
        }
        $condition = array('status' => 1);
		    $platform = $param['platform'];
		    //if ($platform != 1 && $GLOBALS['display'] == 2) {
		    if ($platform == 1) {
		      $condition['source'] = ['<>', 3];
		    }
        if (!empty($param['lastId'])) {
          $condition['sort'] = ['<', $param['lastId']];
        }
        if ($param['cid']) {
            $condition['cid'] = $param['cid'];
        }
        $pagesize = intval($param['pagesize']);

        $newsModel = new NewsModel();
        $orderBy = ['sort' => 'DESC'];
        $data = $newsModel->getNewsList($condition, array(), 0, $pagesize, $orderBy,$param['user_id']);
		
		
        if(empty($data)){
            $this->responseJsonError(1301);
        }
        $this->responseJson($data);
    }


    /**
     * APP资讯列表
     */
    public function newsListV2(){
        $param = $this->checkApiParam([], ['lastId' => '','page'=>0, 'pagesize' => 20, 'cid' => 0, 'platform' => 2,'user_id'=>0]);
        if($param['user_id']){
            $this->checkToken();
        }

        $condition = array('status' => 1);
        $pagesize = intval($param['pagesize']);
        $page = intval($param['page']);
        $platform = $param['platform'];
        //if ($platform != 1 && $GLOBALS['display'] == 2) {
        if ($platform == 1) {
            $condition['source'] = ['<>', 3];
        }
        if (!empty($param['lastId']) && !$page) {
            $condition['sort'] = ['<', $param['lastId']];
        }
        if ($param['cid']) {
            $condition['cid'] = $param['cid'];
        }
        $condition['source'] = ['!=', 3];
        $newsModel = new NewsModel();
       // $orderBy = ['day'=>'DESC','FIELD(cid,12)','sort' => 'DESC'];
        $orderBy = ['sort' => 'DESC'];
        $data = $newsModel->getNewsList($condition, array(), $page, $pagesize, $orderBy,$param['user_id']);

        if(empty($data)){
            $this->responseJsonError(1301);
        }
        $this->responseJson($data);
    }


    /**
     * 资讯内容
     */
    public function getNewsInfo(){
        $param = $this->checkApiParam([], ['nid' => 0]);

        $newsId = $param['nid'];
        $newsModel = new NewsModel();
        $newsInfo = $newsModel->getNewsInfo($newsId);
        if ($newsInfo['article_source'] == 'expert') {
            
        }
        $newsInfo['content']=[];
        if($newsInfo['url']){
            $newsInfo['content']=$this->getUrlContent($newsInfo['url']);

        }
        $this->responseJson($newsInfo);
    }

    /**
     * APP资讯内容
     */
    public function getNewsInfoV2(){
        $param = $this->checkApiParam([], ['nid' => 0]);
        $newsId = $param['nid'];
        $newsModel = new NewsModel();

        $newsInfo = $newsModel->getNewsInfo($newsId);
        $newsInfo['content']=[];
        if($newsInfo['url']){
             if($newsInfo['source']==2){
                $newsInfo['content']=$this->getUrlContentV2($newsInfo['url']);
            }else{
                $newsInfo['content']=$this->getUrlContent($newsInfo['url']);
            }

        }
        $newsInfo['create_time']=date('Y-m-d H:i:s',$newsInfo['create_time']);
		

        $this->responseJson($newsInfo);
    }

    /**
     * 更新浏览量
     */
    public function updateViews() {
      $param = $this->checkApiParam([], ['nid' => 0]);
      $nid = $param['nid'];
      $newsModel = new NewsModel();
      $newsInfo = $newsModel->getNewsInfo($nid);
      $updateInfo['views'] = $newsInfo['views'] + 1;
      $res = $newsModel->updateNews($nid, $updateInfo);
      $this->responseJson();
    }

    public function newsCategory() {
        $CategoryModel = new CategoryModel();
        $result = $CategoryModel->getNewsCategory();
        $this->responseJson($result);
    }

    public function hotMatch() {
        $match_model = new MatchModel();
        $result = $match_model->getHotMatch();
        $this->responseJson($result);
    }

    //获取文章内容
    private function getUrlContent($url) {
        $source = file_get_contents($url);
        preg_match('/<div id="content" class="content">([\s\S]+)<\/div>/is', $source, $content_matches);
        $content = $content_matches[1];
        $content = preg_replace('/<div id="views_info">.*?<\/div>/is', '', $content);
        $content=$this->DataFiltering($content);
        $content =$this->tags_replace($content);//方括号标签处理
        $newsArr=explode('</p>',$content);
        $article=[];
        foreach($newsArr as $v){

            $img = QueryList::html($v)->find('img')->attr('src');
            $alt = QueryList::html($v)->find('img')->attr('alt');
            $img=$this->DataFiltering($img);
            $text = QueryList::html($v)->find('p')->text();
            $text=$this->DataFiltering($text);
            //是图片名称 不写入 文章内容
            if($alt==$text){
                $text='';
            }
            if($img){
                $article[]=['url'=>$img];
            }
            if($alt){
                $article[]=['alt'=>$alt];
            }
            if($text){
                $text=strip_tags($text);//去除所有 html 标签
                $article[]=['text'=>$text];
            }

        }

        return $article;
    }
    //获取文章详情--平台发布的
    public function getUrlContentV2($url){
        $source = file_get_contents($url);
        preg_match('/<div id="content" class="content">([\s\S]+)<\/div>/is', $source, $content_matches);
        $content = $content_matches[1];
        $content = preg_replace('/<div id="views_info">.*?<\/div>/is', '', $content);
        $content=$this->DataFiltering($content);
        $content=html_entity_decode($content);
        $newsArr=explode('<br/>',$content);
        $article=[];
        foreach($newsArr as $v){
            if(!$v){
                continue;
            }
            $img = QueryList::html($v)->find('img')->attr('src');
            $img=$this->DataFiltering($img);
            $title = QueryList::html($v)->find('a')->text();
            $title=$this->DataFiltering($title);
            $alt = QueryList::html($v)->find('a')->attr('onclick');
            if($img){
                $article[]=['url'=>$img];
                $v = preg_replace("/<img[^>]+\>/i", "", $v);
                if(!$v){
                    unset($v);
                    continue;
                }
            }
            //需要跳转
            if($title&&$alt){
                $param=explode("'",$alt);
                $arr=explode("-",$param[1]);
                $article[]=['title'=>$title,'target'=>$arr[0],'id'=>$arr[1]];
                continue;
            }
            $v=str_replace(" ","",$v);
            if($v){
                $article[]=['text'=>$v];
            }
        }
        return $article;
    }


    //特殊标签转html标签
    public function tags_replace($content){
        $ubbcodes=array(
            '/\[size=(.*?)\](.*?)\[\/size\]/',
            /*
            '/\[b\](.*?)\[\/b\]/i',
            '/\[u\](.*?)\[\/u\]/i',
            '/\[i\](.*?)\[\/i\]/i',
            '/\[color=(.*?)\](.*?)\[\/color\]/',
            '/\[align=(.*?)\](.*?)\[\/align\]/'
            */
        );

        $htmls=array(
            '<font size="\1">\2</font>',
            /*
            '<b>\1</b>',
            '<u>\1</u>',
            '<i>\1</i>',
            '<font color="\1">\2</font>',
            '<p align="\1">\2</p>'
            */
        );
        $content=preg_replace($ubbcodes,$htmls,$content);
        return $content;
    }

    //过滤 文本特殊字符
    public function DataFiltering($content){
        $content=str_replace("\n","",$content);
        $content=str_replace("\t","",$content);
        $content=str_replace("　","",$content);
        $content=str_replace("</div>","",$content);
        return $content;
    }
}
