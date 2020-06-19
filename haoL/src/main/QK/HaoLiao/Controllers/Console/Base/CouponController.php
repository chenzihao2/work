<?php
/**
 * 优惠券相关接口
 * User: zyj
 * Date: 2019/11/16
 */

namespace QK\HaoLiao\Controllers\Console\Base;

use QK\HaoLiao\Controllers\Console\ConsoleController;
use QK\HaoLiao\Model\CouponModel;
use QK\HaoLiao\Model\FeedModel;

use QK\CloudStorage\CloudStorageFactory;
use QK\WSF\Settings\AppSetting;
use Upload\File;
use Upload\Storage\FileSystem;

class CouponController extends ConsoleController{
    /**
     * 优惠券列表
     */
    public function couponList(){
        $param = $this->checkApiParam([], ['query' => '', 'page' => 1, 'pagesize' => 20]);
        $query = json_decode($param['query'], true);
        $condition = array();
        if (!empty($query['id'])) {
          $condition['id'] = $query['id'];
        }
        if (!empty($query['name'])) {
          $condition['name'] = ['like', '%'.trim($query['title']).'%'];
        }
       
        if (!empty($query['start_time'])) {
          $condition['ctime'][] = ['>', $query['start_time']];
        }

        if (!empty($query['end_time'])) {
          $condition['ctime'][] = ['<=', $query['end_time']];
        }
        $page = intval($param['page']);
        $pagesize = intval($param['pagesize']);

        $CouponModel = new CouponModel();
        $orderBy = ['sort' => 'DESC','ctime' => 'DESC'];
        $data = $CouponModel->getCouponList($condition, array(), $page, $pagesize, $orderBy);
        $list = $data['list'];
        foreach($list as $index => $value) {
             
            unset($list[$index]['sort']);
            unset($list[$index]['deleted']);

        }
        $data['list'] = $list;
        $this->responseJson($data);
    }

    /**
     * 创建/更新优惠券
     */
    public function editCoupon() {
        $params = $this->checkApiParam(['name','slogan','discount','type','days'], ['id'=> 0,'condition'=>0,'count'=>0]);

        $id=intval($params['id']);
        $content=$params['content'];
        $couponModel = new CouponModel();
        $params['start_time']=date('Y-m-d 00:00:00');
        $params['end_time']=date('Y-m-d 23:59:59',strtotime("+{$params['days']} day"));
        if (!empty($id)) {
            $res = $couponModel->updateCoupon($id, $params);
        } else {

            $res = $couponModel->createCoupon($params);
        }
      if($res){
          $this->responseJson();
      }else{
          $this->responseJsonError(1000, '新增失败');
      }


    }

    /*
     * 优惠券上下架
     */
    public function changeCouponStatus(){
        $params = $this->checkApiParam(['id','status'], []);
        $id=$params['id'];
        $data['status']=$params['status'];
        $couponModel = new CouponModel();
        $res = $couponModel->updateCoupon($id, $data);
        if($res){
            $this->responseJson();
        }else{
            $this->responseJsonError(10001, '状态修改失败');
        }
    }


    /**
     * 优惠券内容
     */
    public function getCouponInfo(){
        $param = $this->checkApiParam(['nid']);

        $Id = $param['id'];
        $couponModel = new CouponModel();
        $couponInfo = $couponModel->getCouponInfo($Id);
        $this->responseJson($couponInfo);
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
