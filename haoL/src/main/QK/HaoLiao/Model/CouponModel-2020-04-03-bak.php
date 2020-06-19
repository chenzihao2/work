<?php
/**
 * 优惠券处理模块
 * User: zyj
 * Date: 2019/11/16
 * Time: 10:10
 */

namespace QK\HaoLiao\Model;


use QK\HaoLiao\Common\StringHandler;
use QK\HaoLiao\DAL\DALCoupon;
use QK\HaoLiao\Model\UserModel;

use QK\CloudStorage\CloudStorageFactory;
use QK\WSF\Settings\AppSetting;
use Upload\File;
use Upload\Storage\FileSystem;
use QK\HaoLiao\Common\CommonHandler;
use Qiniu\Auth;
use Qiniu\Storage\UploadManager;
use Qiniu\Storage\BucketManager;
class CouponModel extends BaseModel {

    private $_dalCoupon;

    public function __construct() {
        parent::__construct();
        $this->_dalCoupon = new DALCoupon($this->_appSetting);
        $this->common = new CommonHandler();
    }

    /**
     * 后台创建优惠券
     * @param $params
     */
      public function createCoupon($params) {
        $data['name'] = StringHandler::newInstance()->stringExecute($params['name']);
        $data['slogan'] = StringHandler::newInstance()->stringExecute($params['slogan']);
        $data['discount'] = $this->ncPriceYuan2Fen($params['discount']);
        $data['condition'] = $this->ncPriceYuan2Fen($params['condition']);
        $data['status'] =0;
        $data['type'] =$params['type'];
        $data['days'] =$params['days'];
        $data['start_time'] =$params['start_time'];
        $data['end_time'] =$params['end_time'];
        $data['ctime'] =date("Y-m-d H:i:s");
        $data['modify_time'] =date("Y-m-d H:i:s");
        $data['sort'] =time();
        $this->_dalCoupon->createCoupon($data);
        $id = $this->_dalCoupon->getInsertId();
          $this->packageBindCoupon($id,1);
        return $id;
    }

    /**
     * 更新优惠券信息
     * @param $Id
     * @param $data
     */
    public function updateCoupon($Id, $data) {
       
        if(isset($data['condition'])){
            $data['condition']=$this->ncPriceYuan2Fen($data['condition']);
        }
        if(isset($data['discount'])){
            $data['discount']=$this->ncPriceYuan2Fen($data['discount']);
        }
        $res = $this->_dalCoupon->updateCoupon($Id, $data);
        return $res;
    }




    /**
     * 条件更新
     */
    public function updateSort($condition, $sort) {
        $res = $this->_dalCoupon->updateSort($condition, $sort);
        return $res;
    }


    /**
     * 后台获取优惠券信息
     * @param $Id
     * @return bool|mixed|null|string
     */
    public function getCouponInfo($Id) {
        $couponInfo = $this->_dalCoupon->getCouponInfo($Id);
        $couponInfo['money']=intval($this->ncPriceFen2Yuan($couponInfo['money']));
        return $couponInfo;
    }
    /**
     * 修改礼包信息
     * @param $Id
     * @param $data
     */
    public function updatePackageV2($Id, $data) {
        $res = $this->_dalCoupon->updatePackageV2($Id, $data);
        return $res;
    }


    /**
     * 后台获取礼包信息
     * @param $Id
     * @return bool|mixed|null|string
     */
    public function getPackageInfo($Id) {
        $couponInfo = $this->_dalCoupon->getPackageInfo($Id);
        return $couponInfo;
    }


    //需改礼包时礼包绑定优惠券
    public function packageBindCoupon($cid,$Id=0){
        //礼包id
        if($Id){
            $info=$this->getPackageInfo($Id);
            $cidArr=explode(',',$info['cid']);
            if(!in_array($cid,$cidArr)){
                array_push($cidArr,$cid);
            }
            $cids=implode(',',$cidArr);
            $this->updatePackageV2($Id,['cid'=>trim($cids,'')]);
        }
    }



    /**
     * 获取优惠券列表
     * @return mixed
     */
    public function getCouponList($condition = array(), $fields = array(), $page = 0, $pageSize = 20, $orderBy = array(),$user_id = 0) {
        $list = $this->_dalCoupon->getCouponList($condition, $fields, $page, $pageSize, $orderBy);
        if($page == 0){
            $total = $this->_dalCoupon->getCouponTotal([]);
        }else {
            $total = $this->_dalCoupon->getCouponTotal($condition);
        }
		

        foreach($list as &$v){
			$v['condition']=intval($this->ncPriceFen2Yuan($v['condition']));
			$v['discount']=intval($this->ncPriceFen2Yuan($v['discount']));


            //获取 该优惠券 用户已领取数量
            $condition=" cid=".$v['id'];
            $v['count']=$this->_dalCoupon->getCouponUserCount($condition);
        }
		
        return array(
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'pagesize' => $pageSize
        );
    }

    private function getCouponByPackageId($package_id) {
        $condition = ['id' => $package_id];
        $package_info = $this->_dalCoupon->getPackageByCondition($condition);
        if (!$package_info) {
            return false;
        }
        $cid = $package_info['cid'];
        $cids = explode(',', $cid);
        $coupon_infos = [];
        foreach ($cids as $coupon_id) {
            $c_condition = ['id' => $coupon_id, 'status' => 1, 'deleted' => 0];
            $coupon_info = $this->_dalCoupon->getCouponByCondition($c_condition);
            if ($coupon_info) {
                $coupon_infos[] = $coupon_info;
            }
        }
        return $coupon_infos;
    }

    //领取礼包
    public function receiveCoupon($package_id, $user_id, $device_number = '') {
        $condition = ['uid' => $user_id, 'bid' => $package_id];
        $userModel = new UserModel();
        $user_info = $userModel->getUserInfo($user_id);
        $uuid = 0;
        if ($user_info['uuid']) {
            $uuid = $user_info['uuid'];
            $condition['uuid'] = $uuid;
            unset($condition['uid']);
        }
        $exists = $this->_dalCoupon->existsUserCoupon($condition);
        if ($exists) {
            return false;
        }
        $package_info = $this->_dalCoupon->getPackageByCondition(['id' => $package_id]);
        $coupon_infos = $this->getCouponByPackageId($package_id);
        if ($device_number) {
            if ($package_info['user_type'] == 0) {
                $device_res = $userModel->checkDevice($device_number, $user_id);
                if ($device_res['status']) {
                    return false;
                }
            }
        }
        $package_info = $this->_dalCoupon->getPackageByCondition(['id' => $package_id]);
        if (empty($coupon_infos)) {
            return false;
        }
        foreach ($coupon_infos as $item) {
            $format_data = [];
            $format_data['uid'] = $user_id;
            $format_data['uuid'] = $uuid;
            $format_data['cid'] = $item['id'];
            $format_data['bid'] = $package_id;
            $format_data['name'] = $item['slogan'];
            $format_data['pname'] = $package_info['name'];
            $format_data['condition'] = $item['condition'];
            $format_data['discount'] = $item['discount'];
            $format_data['type'] = $item['type'];
            $format_data['start_time'] = $item['start_time'];
            $format_data['end_time'] = $item['end_time'];
            $this->_dalCoupon->addUserCoupon($format_data);
        }
        return true;
    }

    public function onlinePackage($area, $user_id, $device_number) {
        $userModel = new UserModel();
        $userModel->checkDevice($device_number, $user_id);
        $condition = [];
        $now = date('Y-m-d H:i:s', time());
        $condition['area'] = $area;
        $condition['deleted'] = 0;
        $condition['start_time'] = [" < '", $now . "'"];
        $condition['end_time'] = [" > '", $now . "'"];
        if (empty($user_id)) {
            $condition['user_type'] = 0;
        }
        $result = $this->_dalCoupon->getPackageList($condition);
        $packages = [];
        $sort = [];
        foreach ($result as $item) {
            $check = $this->checkPackageLimit($item['user_type'], $item['id'], $device_number, $user_id);
            if (!$check) {
                continue;
            }
            $sort[] = $item['user_type'];
            $tmp = [];
            $tmp['id'] = $item['id'];
            $tmp['name'] = $item['name'];
            $tmp['img'] = $item['img'];
            $packages[] = $tmp;
        }
        array_multisort($sort, SORT_ASC, $packages);
        return $packages;
    }

    public function userCoupons($user_id, $status, $price) {
        $condition = ['uid' => $user_id, 'status' => $status];
        $userModel = new UserModel();
        $user_info = $userModel->getUserInfo($user_id);
        $uuid = 0;
        if ($user_info['uuid']) {
            $uuid = $user_info['uuid'];
            $this->dealCouponUidUuid($uuid);
            $condition['uuid'] = $uuid;
            unset($condition['uid']);
        }
        $data = $this->_dalCoupon->getUserCoupons($condition);
        $result = [];
        foreach ($data as $item) {
            $tmp = [];
            $tmp['id'] = $item['id'];
            $tmp['name'] = $item['name'];
            $tmp['pname'] = $item['pname'];
            $tmp['start_time'] = str_replace('-', '.', substr($item['start_time'], 0, 10)) . ' ';
            $tmp['end_time'] = ' ' . str_replace('-', '.', substr($item['end_time'], 0, 10));
            $tmp['status'] = $item['status'];
            $tmp['type'] = $item['type'];
            if (($price * 100 < $item['condition']) && $item['type'] == 1 && $price) {
                $tmp['status'] = 3; //不符合使用条件
            }
            $tmp['discount'] = bcdiv($item['discount'], 100);
            //if ($tmp['discount'] >= $price && $price) {
            //    $tmp['status'] = 3;
            //}
            $tmp['type_text'] = $this->dealCouponTypeText($item['type'], $item['condition'], $item['discount']);
            $result[] = $tmp;
        }
        $status = array_column($result, 'status');
        $discounts = array_column($result, 'discount');
        array_multisort($status, $discounts,SORT_DESC,$result);
        return $result;
    }

    public function getUserCouponInfo($coupon_id, $user_id = 0) {
        $condition = ['id' => $coupon_id, 'status' => 0];
        if ($user_id) {
            $condition['uid'] = $user_id;
        }
        $coupon_info = $this->_dalCoupon->existsUserCoupon($condition);
        return $coupon_info;
            
    }

    public function changeUserCouponStatus($id, $status) {
        $condition = ['id' => $id];
        $data = ['status' => $status];
        if ($status == 1) {
            $user_coupon_info = $this->_dalCoupon->existsUserCoupon($condition);
            $coupon_id = $user_coupon_info['cid'];
            $this->_dalCoupon->amountAddById($coupon_id);
        }
        return $this->_dalCoupon->updateUserCoupon($condition, $data);
    }

    public function checkCouponTime() {
        $now = date('Y-m-d H:i:s', time());
        
        $package_condition = ['type' => 1, 'deleted' => 0];
       $packages = $this->_dalCoupon->getPackageList($package_condition);
        foreach ($packages as $item) {
            if ($item['end_time'] < $now) {
                $this->_dalCoupon->updatePackage(['id' => $item['id']], ['deleted' => 1]);
            }
            $have_coupon = 0;
            $cids = explode(',', $item['cid']);
            $coupon_infos = [];
            foreach ($cids as $coupon_id) {
                $c_condition = ['id' => $coupon_id, 'status' => 1, 'deleted' => 0];
                $coupon_info = $this->_dalCoupon->getCouponByCondition($c_condition);
                if ($coupon_info) {
                    $have_coupon = 1;
                }
            }
            if (!$have_coupon) {
                $this->_dalCoupon->updatePackage(['id' => $item['id']], ['deleted' => 1]);
            }
        }
        /*
        $coupon_condition = ['status' => 1, 'deleted' => 0];
        $coupons = $this->_dalCoupon->getCoupons($coupon_condition);
        foreach ($coupons as $item) {
            if ($item['end_time'] < $now) {
                $this->_dalCoupon->updateCoupons(['id' => $item['id']], ['status' => 2]);
            }
        }*/
        
        $user_coupon_condition = ['status' => 0];
        $user_coupons = $this->_dalCoupon->getUserCoupons($user_coupon_condition);
        foreach ($user_coupons as $item) {
            if ($item['end_time'] < $now) {
                $this->_dalCoupon->updateUserCoupon(['id' => $item['id']], ['status' => 2]);
            }
        }

        return;
    }

    private function checkPackageLimit($user_type, $package_id, $device_number, $user_id) {
        $userModel = new UserModel();
        $uuid = 0;
        if ($user_id) {
            $user_info = $userModel->getUserInfo($user_id);
            if ($user_info['uuid']) {
                $uuid = $user_info['uuid'];
            }
        }
        switch($user_type) {
        case 0:
            if (!$device_number) {
                return false; 
            }
            $device_status = $userModel->checkDevice($device_number, $user_id);
            if ($device_status['status'] == 1) {
                return false;
            }
            if ($user_id) {
                $condition = ['uid' => $user_id, 'bid' => $package_id];
                if ($uuid) {
                    $condition['uuid'] = $uuid;
                    unset($condition['uid']);
                }
                $exists = $this->_dalCoupon->existsUserCoupon($condition);
                if ($exists) {
                    return false;
                }
            }
            break;
        case 1:
            if ($user_id) {
                $condition = ['uid' => $user_id, 'bid' => $package_id];
                if ($uuid) {
                    $condition['uuid'] = $uuid;
                    unset($condition['uid']);
                }
                $exists = $this->_dalCoupon->existsUserCoupon($condition);
                if ($exists) {
                    return false;
                }
            }
            break;
        }
        return true;
    }

    private function dealCouponTypeText($type, $condition, $discount) {
        switch($type) {
        case 0:
            return '无门槛优惠券';
            break;
        case 1:
            return '满' . bcdiv($condition, 100) . '减' . bcdiv($discount, 100); 
            break;
        }
        return '';
    }

    private function dealCouponUidUuid($uuid) {
        if (empty($uuid)) {
            return false;
        }
        $userModel = new UserModel();
        $users = $userModel->getUsersByUUid($uuid);
        $user_ids = array_column($users, 'user_id');
        foreach ($user_ids as $user_id) {
            $condition = [];
            $condition['uid'] = $user_id;
            $condition['uuid'] = '0';
            $tmp_coupons = $this->_dalCoupon->getUserCoupons($condition);
            if ($tmp_coupons) {
                foreach ($tmp_coupons as $item) {
                    $this->_dalCoupon->updateUserCoupon(['id' => $item['id']], ['uuid' => $uuid]);
                }
            }
        }
        return true;
    }

    /**
     * 上传图片
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

  
}
