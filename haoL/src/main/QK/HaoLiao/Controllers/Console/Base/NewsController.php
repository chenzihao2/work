<?php
/**
 * 资讯相关接口
 * User: zwh
 * Date: 2019/03/22
 */

namespace QK\HaoLiao\Controllers\Console\Base;

use QK\HaoLiao\Controllers\Console\ConsoleController;
use QK\HaoLiao\Model\NewsModel;
use QK\HaoLiao\Model\FeedModel;

use QK\CloudStorage\CloudStorageFactory;
use QK\WSF\Settings\AppSetting;
use Upload\File;
use Upload\Storage\FileSystem;

class NewsController extends ConsoleController{
    /**
     * 资讯列表
     */
    public function newsList(){
        $param = $this->checkApiParam([], ['query' => '', 'page' => 1, 'pagesize' => 20, 'is_admin' => 0]);
        $query = json_decode($param['query'], true);
        $condition = array();
        if (!empty($query['nid'])) {
          $condition['nid'] = $query['nid'];
        }
        if (!empty($query['title'])) {
          $condition['title'] = ['like', '%'.trim($query['title']).'%'];
        }
        if (!empty($query['target'])) {
          $condition['target'] =  ['like', '%'.trim($query['target']).'%'];
        }
        if (!empty($query['start_time'])) {
          $condition['create_time'][] = ['>', $query['start_time']];
        }

        if (!empty($query['end_time'])) {
          $condition['create_time'][] = ['<=', $query['end_time']];
        }
        $page = intval($param['page']);
        $pagesize = intval($param['pagesize']);
        $is_admin = $param['is_admin'];

        $newsModel = new NewsModel();
        $orderBy = ['sort' => 'DESC','create_time' => 'DESC'];
        $data = $newsModel->getNewsList($condition, array(), $page, $pagesize, $orderBy, 0, $is_admin);
        $list = $data['list'];
        foreach($list as $index => $value) {
          $sortVal = intval(floor($value['sort']/10000000000));

            $sortRule = array(
                0 => 0,
                1 => 9,
                2 => 8,
                3 => 7,
                4 => 6,
                5 => 5,
                6 => 4,
                7 => 3,
                8 => 2,
                9 => 1,

            );
          $list[$index]['sort'] = $sortRule[$sortVal];
        }
        $data['list'] = $list;
        $this->responseJson($data);
    }

    /**
     * 资讯内容
     */
    public function getNewsInfo(){
        $param = $this->checkApiParam(['nid']);

        $newsId = $param['nid'];
        $newsModel = new NewsModel();
        $newsInfo = $newsModel->getNewsInfo($newsId);
        $newsInfo['content'] = $this->getUrlContent($newsInfo['url']);
        $this->responseJson($newsInfo);
    }

    public function publishFeed() {
      $param = $this->checkApiParam(['nid']);
      $newsId = $param['nid'];
      $feedModel = new FeedModel();
      $res = $feedModel->publishNews($newsId);
      $this->responseJson($res);
    }

    /**
     * 调整排序
     */
    public function updateSort() {
        $param = $this->checkApiParam(['nid', 'sort']);
        $sort_num = 0;
        $updateCondition = array();
        switch($param['sort']) {
            case 1: //置顶1 最大位
                $sort_num = 9;
                $updateCondition = array('sort' => ['>', 90000000000]);
                break;
            case 2: //置顶2
                $sort_num = 8;
                $updateCondition = array('sort' => ['>', 80000000000],'sort_end' => ['<', 90000000000]);
                break;
            case 3: //置顶3
                $sort_num = 7;
                $updateCondition = array('sort' => ['>', 70000000000],'sort_end' => ['<', 80000000000]);
                break;
            case 4: //置顶4
                $sort_num = 6;
                $updateCondition = array('sort' => ['>', 60000000000],'sort_end' => ['<', 70000000000]);
                break;
            case 5: //置顶5
                $sort_num = 5;
                $updateCondition = array('sort' => ['>', 50000000000],'sort_end' => ['<', 60000000000]);
                break;
            case 6: //置顶6
                $sort_num = 4;
                $updateCondition = array('sort' => ['>', 40000000000],'sort_end' => ['<', 50000000000]);
                break;
            case 7: //置顶7
                $sort_num = 3;
                $updateCondition = array('sort' => ['>', 30000000000],'sort_end' => ['<', 40000000000]);
                break;
            case 8: //置顶8
                $sort_num = 2;
                $updateCondition = array('sort' => ['>', 20000000000],'sort_end' => ['<', 30000000000]);
                break;
            case 9: //置顶9，最小位
                $sort_num = 1;
                $updateCondition = array('sort' => ['>', 10000000000],'sort_end' => ['<', 20000000000]);
                break;
        }

        $newsModel = new NewsModel();
         if($param['sort']){
            $ret = $newsModel->updateSort($updateCondition, 0);
         }

        $res = $newsModel->updateSort(array('nid' => $param['nid']), $sort_num);
        $this->responseJson();
    }
    public function updateSort_bak() {
      $param = $this->checkApiParam(['nid', 'sort']);
      $sort_num = 0;
      $updateCondition = array();
      switch($param['sort']) {
        case 1: //置顶1 最大位
          $sort_num = 3;
          $updateCondition = array('sort' => ['>', 30000000000]);
          break;
        case 2: //置顶2
          $sort_num = 2;
          $updateCondition = array('sort' => ['>', 20000000000], 'sort' => ['<', 30000000000]);
          break;
        case 3: //置顶3，最小位
          $sort_num = 1;
          $updateCondition = array('sort' => ['>', 10000000000], 'sort' => ['<', 20000000000]);
          break;
      }

      $newsModel = new NewsModel();
      $ret = $newsModel->updateSort($updateCondition, 0);
      $res = $newsModel->updateSort(array('nid' => $param['nid']), $sort_num);
      $this->responseJson();
    }

    /**
     * 创建/更新资讯
     */
    public function editNews() {
        $params = $this->checkApiParam([], ['nid'=> 0, 'title'=> '', 'icon' => '', 'content' => '', 'description' => '', 'target' => '', 'base_views' => 0, 'status' => -1,'cid'=>0,'money'=>0,'is_pay'=>0, 'comment' => 1, 'article_source' => 0]);
		$params['is_pay']=(int)$params['is_pay'];
        $nid=$params['nid'];
        $content=$params['content'];

        $newsModel = new NewsModel();
        $source = 0;
        if ($nid) {
            $news_info = $newsModel->getNewsInfo($nid);
            $source = $news_info['source'];
        }
        foreach($params as $key => $val) {
            if ($key == 'content' && $source == 3) {
                unset($params[$key]);
                continue;
            }
            if ($key == 'content' && !empty($val)) {
                $fileUrl = $this->parseFile($params['content']);

                if($fileUrl == null) {
                    $this->responseJsonError(1111);
                }
                $params['url'] = $fileUrl;
                unset($params['content']);

            } else {
                $params[$key] = $val;
            }
            $params['url'] = $fileUrl;


      }

      if (!empty($params['nid'])) {
        $newsId = intval($params['nid']);
        foreach($params as $updateKey => $updateVal) {
          if (empty($updateVal) || ($updateKey == 'status' && $updateVal == -1)) {
              if($updateKey != 'comment'){
                  unset($params[$updateKey]);
              }
          }
        }
        $res = $newsModel->updateNews($newsId, $params);
      } else {
        $params['ctime'] = time();
        $params['status'] = 0;
        $params['n_type'] = '';
        $params['keywords'] = '';
        $params['source'] = 2;
        $res = $newsModel->createNews($params);
      }
	    //存入内容表
        // if($nid){
            // $row=$this->newsContent($nid,$content);
        // }
      $this->responseJson();
    }

	 //写入对应文章内容
    public function newsContent($nid,$content){
        $params['modify_time'] = time();
        $params['content'] = $content;
        $params['nid'] = $nid;

        $newsModel = new NewsModel();
        $res=$newsModel->findContent($nid);
        if(!$res){
            $res = $newsModel->addContent($params);
        }else{
            $res = $newsModel->updateContent($nid,$params);
        }
        return $res;
    }

    private function parseFile($content) {
      $prefix = 'news';
      $path = $this->getPath($prefix);

      preg_match('/<body>([\s\S]+)<\/body>/is', $content, $content_matches);
      $content = $content_matches[1];

      $patternStr = array('\r\n', '\n', '\r');
      $content=str_replace($patternStr, '<br/>', $content);
      //$content = preg_replace('/\n/', '<br/>', $content);

      $content = preg_replace('/\[url=([^[]+)\]/is', '<a href="javascript:void(0);" onclick="miniProgramNavigate(\'$1\');">', $content);
      $content = preg_replace('/\[\/url\]/is', '</a>', $content);

      $content = preg_replace('/\[img\]/is', '<img src="', $content);
      $content = preg_replace('/\[\/img\]/is', '"/>', $content);

      $source = file_get_contents('./static/news_tmpl/news_tmpl.html');
      $source = preg_replace('/<div id="content" class="content">.*?<\/div>/is', '<div id="content" class="content">'.$content. '</div>', $source, 1);

      $localPath = $this->_appSetting->getConstantSetting('FILE-UPLOAD') . "/" . $prefix . "/" . $path;
      $file_name = 'news_'.time().'.html';
      $res = file_put_contents($localPath."/".$file_name, $source);


      $file_server_host = $this->_appSetting->getConstantSetting('STATIC_URL');
      $qiNiuPublicKey = $this->_appSetting->getConstantSetting('QiNiu-PUBLIC-KEY');
      $qiNiuPrivateKey = $this->_appSetting->getConstantSetting('QiNiu-PRIVATE-KEY');
      $qiNiuObj = CloudStorageFactory::newInstance()->createQiNiuObj($qiNiuPublicKey, $qiNiuPrivateKey);
      $qiNiuBucket = $this->_appSetting->getConstantSetting('QiNiu-BUCKET');

      $key = $prefix . "/" . $path . "/" . $file_name;
      $qiNiuObj->upload($qiNiuBucket, $key, $localPath.'/'.$file_name);

      $result = $qiNiuObj->getRet();
      if ($result['hash']) {
        return $file_server_host.$result['key'];
      } else {
        return null;
      }
    }

    private function getUrlContent($url) {
      $source = file_get_contents($url);
      preg_match('/<div id="content" class="content">([\s\S]+)<\/div>/is', $source, $content_matches);
      $content = $content_matches[1];
      $content = preg_replace('/<div id="views_info">.*?<\/div>/is', '', $content);
      //handle <a> to [url]
      //$content = preg_replace('<a href="javascript:void(0);" onclick="miniProgramNavigate(\'([^<|>]*)\');">', '[url=$1]', $content);
      //$content = preg_replace('/</a>/', '[/url]', $content);

      $htmlStr = "<!DOCTYPE html><html><head></head><body>".$content."</body></html>";
      return $htmlStr;
    }

    /**
     * 获取今日目录
     */
    private function getPath($prefix) {
        $time = time();
        $monthPathString = $this->_appSetting->getConstantSetting('FILE-UPLOAD') . "/" . $prefix . "/" . date('Ym', $time);

        $this->checkPath($monthPathString);
        $dayPathString = $monthPathString . "/" . date("d", $time);
        $this->checkPath($dayPathString);
        $onlinePath = date("Ym", $time) . "/" . date("d", $time);
        return $onlinePath;
    }

    /**
     * 创建目录
     */
    private function checkPath($path) {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }
}
