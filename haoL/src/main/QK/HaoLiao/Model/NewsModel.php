<?php
/**
 * 资讯处理模块
 * User: zwh
 * Date: 2019/03/22
 * Time: 10:10
 */

namespace QK\HaoLiao\Model;


use QK\HaoLiao\Common\StringHandler;
use QK\HaoLiao\DAL\DALNews;

use QK\CloudStorage\CloudStorageFactory;
use QK\WSF\Settings\AppSetting;
use Upload\File;
use Upload\Storage\FileSystem;
use QK\HaoLiao\Common\CommonHandler;
use Qiniu\Auth;
use Qiniu\Storage\UploadManager;
use Qiniu\Storage\BucketManager;
use QK\HaoLiao\DAL\DALOrder;
class NewsModel extends BaseModel {

    private $_dalNews;
    private $bq_link = 'http://t.ynet.cn/data/chuangke/sports.xml';

    public function __construct() {
        parent::__construct();
        $this->_dalNews = new DALNews($this->_appSetting);
        $this->common = new CommonHandler();
    }

    /**
     * 创建资讯
     * @param $params
     */
    public function createNews($params) {
        $news['title'] = StringHandler::newInstance()->stringExecute($params['title']);
        $news['url'] = StringHandler::newInstance()->stringExecute($params['url']);
        $news['icon'] = StringHandler::newInstance()->stringExecute($params['icon']);
        $news['description'] = StringHandler::newInstance()->stringExecute($params['description']);
        $news['target'] = StringHandler::newInstance()->stringExecute($params['target']);
        $news['n_type'] = StringHandler::newInstance()->stringExecute($params['n_type']);
        $news['keywords'] = StringHandler::newInstance()->stringExecute($params['keywords']);
        $news['source'] = $params['source'];
        $news['create_time'] = $params['ctime'];
        $news['modify_time'] = $params['ctime'];
        $news['status'] = $params['status'];
        $news['base_views'] = $params['base_views'];
        $news['views'] = 0;
        $news['sort'] = $params['ctime'];
        $news['cid'] = $params['cid'];
        $news['comment'] = $params['comment'];
		$news['money'] = isset($params['money'])?$this->ncPriceYuan2Fen($params['money']):0;
        $news['is_pay'] = isset($params['is_pay'])?$params['is_pay']:0;
        $news['article_source'] = isset($params['article_source']) ? $params['article_source']:0;
        $this->_dalNews->createNews($news);
        $id = $this->_dalNews->getInsertId();
        return $id;
    }

    /**
     * 更新资讯信息
     * @param $newsId
     * @param $data
     */
    public function updateNews($newsId, $data) {
        $data['modify_time'] = time();
		if(isset($data['money'])){
			$data['money']=$this->ncPriceYuan2Fen($data['money']);
		}
		
        $res = $this->_dalNews->updateNews($newsId, $data);
        return $res;
    }




    /**
     * 更新资讯内容信息
     * @param $nid
     * @param $data
     */
    public function updateContent($nid, $data) {
        $data['modify_time'] = time();
        $res = $this->_dalNews->updateContent($nid, $data);
        return $res;
    }
    /**
     * 添加资讯内容信息
     * @param $cid
     * @param $data
     */
    public function addContent($data) {

        $res = $this->_dalNews->createContent($data);
        $id = $this->_dalNews->getInsertId();
        return $id;
    }

    /**
     * 查询资讯内容信息
     * @param $nid
     * @param $data
     */

    public function findContent($nid){
        $res = $this->_dalNews->findContent($nid);
        return $res;
    }



    /**
     * 根据名称获取资讯
     * @param $d
     */
    public function getNameNewsInfo($name) {
        $news = $this->_dalNews->getNameNewsInfo($name);
		//$news['money']=intval($this->ncPriceFen2Yuan($news['money']));
        return $news;
    }



    /**
     * 条件更新
     */
    public function updateSort($condition, $sort) {
        $res = $this->_dalNews->updateSort($condition, $sort);
        return $res;
    }


    /**
     * 获取资讯信息
     * @param $newsId
     * @return bool|mixed|null|string
     */
    public function getNewsInfo($newsId) {
        $newsInfo = $this->_dalNews->getNewsInfo($newsId);
		$newsInfo['money']=intval($this->ncPriceFen2Yuan($newsInfo['money']));
        return $newsInfo;
    }

    /**
     * 获取资讯分类信息
     * @param $cid
     * @return bool|mixed|null|string
     */
    public function getNewsCateInfo($cid) {
        $cateInfo = $this->_dalNews->getNewsCateInfo($cid);
        return $cateInfo;
    }



    /**
     * 获取资讯列表
     * @return mixed
     */
    public function getNewsList($condition = array(), $fields = array(), $page = 0, $pageSize = 20, $orderBy = array(),$user_id = 0, $is_admin = 0) {
        $list = $this->_dalNews->getNewsList($condition, $fields, $page, $pageSize, $orderBy);

        if($page == 0){
            $total = $this->_dalNews->getNewsTotal([]);
        }else {
            $total = $this->_dalNews->getNewsTotal($condition);
        }
		
        $DALOrder=new DALOrder($this->_appSetting);

        foreach($list as &$v){
            $v['is_buy']=0;
            if($v['is_pay']&&$user_id){
                $res=$DALOrder->getOrderByUserId($user_id,$v['nid'],3);
                if($res){
                    $v['is_buy']=1;
                }
            }
			$v['money']=intval($this->ncPriceFen2Yuan($v['money']));
            if (!$is_admin) {
                $tmp_icon = explode(',', $v['icon']);
                $v['icon'] = $tmp_icon[0];
            }
        }
		
        return array(
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'pagesize' => $pageSize
        );
    }

    /**
     * 上传资讯文章
     */
    public function uploadNews($file_name, $source) {
        $prefix = 'news';
        $path = $this->getPath($prefix);

        $localPath = $this->_appSetting->getConstantSetting('FILE-UPLOAD') . "/" . $prefix . "/" . $path;
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
            chmod($path, 0777);
        }
    }

    public function getNewsListV2($condition = array(), $fields = array(), $offset = 0, $limit = 0, $orderBy = array()) {
        return $this->_dalNews->newsListV2($condition, $fields, $offset, $limit, $orderBy);
    }

    public function importBqNews() {
        $url = $this->bq_link;
        $data = $this->ask($url);
        foreach ($data as $k => $v) {
            $format_data = [];
            $format_data['title'] = $v['title'];
            $format_data['url'] = $v['link'];
            $format_data['target'] = $v['source'];
            $format_data['cid'] = 12;
            $format_data['source'] = 3;
            $format_data['status'] = 1;
            $format_data['create_time'] = strtotime($v['pubDate']);
            $format_data['sort'] = strtotime($v['pubDate']);
            if (is_string($v['description']) && !empty($v['description'])) {
                $suffix_start = strrpos($v['description'], '/');
                $suffix = substr($v['description'], $suffix_start - 11);
                $our_img = $this->fs_fetch($v['description'], $suffix);
                if ($our_img) {
                    $format_data['icon'] = $our_img;
                } else {
                    $format_data['icon'] = $v['description'];
                }
            }
            $news = $this->_dalNews->getNewsByUrl($format_data['url']);
            if ($news) {
                $this->updateNews($news['nid'], $format_data);
            } else {
                $this->_dalNews->createNews($format_data);
            }
        }
    }

    private function ask($url) {
        $xml = $this->common->httpGet($url, []);
        $data =  simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $data = json_decode(json_encode($data),TRUE);
        if ($data['channel']['item']) {
            return $data['channel']['item'];
        } else {
            return [];
        }
    }

    private function fs_fetch ($url, $suffix = '') {
        if (!$suffix) {
            return '';
        }
        $key = '/bq' . $suffix;
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

}
