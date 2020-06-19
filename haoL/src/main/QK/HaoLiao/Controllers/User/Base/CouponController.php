<?php

namespace QK\HaoLiao\Controllers\User\Base;

use QK\HaoLiao\Model\CouponModel;
use QK\HaoLiao\Model\UserModel;
use QK\HaoLiao\Model\FeedModel;
use QK\WSF\Settings\AppSetting;
use QK\HaoLiao\Controllers\User\UserController;

class CouponController extends UserController{

    public function receiveCoupon() {
        $param = $this->checkApiParam(['package_id', 'user_id'], ['device_number' => false]);
        $package_id = $param['package_id'];
        $user_id = $param['user_id'];
        $CouponModel = new CouponModel();
        $device_number = $param['device_number'];
        $result = $CouponModel->receiveCoupon($package_id, $user_id, $device_number);
        if ($param['device_number']) {
            $userModel = new UserModel();
            $userModel->consumeDevice($param['device_number'], $user_id); 
        }
        $this->responseJson($result);
    }

    public function onlinePackage() {
        $param = $this->checkApiParam(['area', 'device_number'], ['user_id' => 0]);
        $area = $param['area'];
        $user_id = $param['user_id'] ?: 0;
        $device_number = $param['device_number'];
        $CouponModel = new CouponModel();
        $result = $CouponModel->onlinePackage($area, $user_id, $device_number);
        $this->responseJson($result);
    }

    public function userCoupons() {
        $param = $this->checkApiParam(['user_id'], ['status' => 0, 'price' => 0]);
        $user_id = $param['user_id'];
        $status = $param['status'] ?: 0;
        $price = $param['price'] ?: 0;
        $CouponModel = new CouponModel();
        $result = $CouponModel->userCoupons($user_id, $status, $price);
        if ((empty($result) || !in_array(0, array_column($result, status))) && $price) {
            $this->responseJson($result, 'noCoupon');
            return;
        }
        $this->responseJson($result);
    }

    /**
     * 优惠券列表
     */
    public function couponList(){
        $param = $this->checkApiParam([], ['query' => '', 'page' => 1, 'pagesize' => 20]);
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
            $list[$index]['start_time']=date("Y-m-d H:i:s",$value['start_time']);
            $list[$index]['end_time']=date("Y-m-d H:i:s",$value['end_time']);
            $list[$index]['create_time']=date("Y-m-d H:i:s",$value['ctime']);
            unset($list[$index]['modify_time']);
            unset($list[$index]['ctime']);
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
        $params = $this->checkApiParam(['name','slogan','discount','type','days','count'], ['id'=> 0,'condition'=>0]);

        $id=intval($params['id']);
        $content=$params['content'];
        $couponModel = new CouponModel();
        $params['start_time']=strtotime(date('Y-m-d 00:00:00'));
        $params['end_time']=strtotime(date('Y-m-d 23:59:59',strtotime("+{$params['days']} day")));
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
    
}
