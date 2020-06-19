<?php

namespace App\Http\Controllers\Api\V1;

use App\Clients;
use App\models\buyer;
use App\models\client;
use App\models\client_extra;
use App\models\client_money_change;
use App\models\client_rate;
use App\models\order;
use App\models\purchase_record;
use App\models\purchase_record_front;
use App\models\source;
use App\models\contents;
use App\models\source_extra;
use App\models\source_sensitives;
use App\models\follow;
use App\profile;
use App\resource;
use App\Sources;
use Dingo\Api\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\orders;
use WxPayUnifiedOrder;
use WxPayOrderQuery;
use Tymon\JWTAuth\Facades\JWTAuth;
use Ramsey\Uuid\Uuid;
use \QKPHP\Common\Utils\Http;

include_once(app_path() . "/alipay/wappay/service/AlipayTradeService.php");
include_once(app_path() . "/alipay/wappay/buildermodel/AlipayTradeWapPayContentBuilder.php");
include_once (app_path()."/../public/alipay/aop/AopCertClient.php");
include_once (app_path()."/../public/alipay/aop/request/AlipayTradeWapPayRequest.php");

class OrdersController extends BaseController{

    private $max_wheel = 10;
    private $min_totals = 0.01;
    private $max_totals = 50;
    private $min_hours = 8;
    private $max_hours = 20;

    public function prepayIdToDB() {
        $redisKey = "Order_Prepay_Id";
        while($sql = Redis::rpop($redisKey)){
            DB::update($sql);
        }
    }

    /**
     * 用户初步下单
     */
    public function postOrder(Request $request)
    {
        $token = JWTAuth::getToken();
        $uid = $request->input('uid', '');
        $sid = $request->input('sid', '');
        $clients = $this->UserInfo($token);
        if (empty($token) || $clients['id'] != $uid) {
            $return['status_code'] = 10001;
            $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
            return response()->json($return);
        }

        // 判断是否存在用户详情
        if( empty($clients['unionid']) ) {
            $return['status_code'] = '10002';
            $return['error_message'] = '下单需获取用户详情';
            return response()->json($return);
        }

        // 判断料状态， 销售数量是否超过
        $sources = source::LeftJoin('source_extra', 'source_extra.sid', 'source.sid')->where('source.sid', $sid)->first();
        if(!$sources || $sources['status'] != 0 ) {
            $return['status_code'] = '10003';
            $return['error_message'] = '资源异常， 已被删除或下架';
            $return['status'] = $sources['status'];
            return response()->json($return);
        }

        if( $sources['thresh'] != 0) {
            if( $sources['soldnumber'] + 1 > $sources['thresh'] ) {
                $return['status_code'] = '10004';
                $return['error_message'] = '资源已售罄';
                return response()->json($return);
            }
        }

        // 查看是否已购买过该资源
        $buy_sources = order::where('buyerid', $uid)
            ->where('sid', $sid)
            ->where('pack_type', 0)
            ->whereRaw('orderstatus & 1')
            //->whereRaw("substring(bin(orderstatus), -1, 1) = 1")    //获取已支付订单，不管用户是否删除
            ->first();
        if( !empty($buy_sources) ) {
            if ($buy_sources['orderstatus'] < 5) {
                // 排除续费订单
                $return['status_code'] = '10005';
                $return['error_message'] = '用户已购买过该资源';
                return response()->json($return);
            }
        }

        $today = date('Y-m-d H:i:s');
        $ordernum = $this->order_number();
        $order['id'] = Redis::incr('order_id');
        $order['sid'] = $sources['sid'];
        $order['pack_type'] = $sources['pack_type'];
        $order['ordernum'] = $ordernum;
        $order['buyerid'] = $uid;
        $order['selledid'] = $sources['uid'];
        $order['sourceid'] = $sources['sid'];
        $order['price'] = $sources['price'];
        if ($sources['pack_type'] == 1) {
            $order['score'] = '1'.time();
        } else {
            $order['score'] = time();
        }
        $order['createtime'] = $today;
        $order['modifytime'] = $today;
        $order['pack_type'] = $sources['pack_type'];
        $order['start_time'] = date('Y-m-d H:i:s', time()-3*3600);
        // 判断料是否为免费， 免费则直接修改状态为已购买并返回
        $free = 0;
        if( $sources['price'] == 0 ) {
            if ($sources['pack_type'] == 1) { // 更新续费订单的renew状态和end_time
                $historyOrders = order::where('sid', $sources['sid'])->where('buyerid', $uid)->whereRaw('orderstatus in (1,3)')->get()->ToArray();
                if (!empty($historyOrders)) {
                    foreach ($historyOrders as $horder) {
                        order::where('id', $horder['id'])->update([
                            'orderstatus' => $horder['orderstatus'] | 4,
                            'end_time' => date('Y-m-d H:i:s', strtotime($horder['start_time'])+($sources['pack_day']+$sources['delayed_day'])*86400+3*3600),
                            'modifytime'=>date('Y-m-d H:i:s')
                        ]);
                    }
                }
            }
            $free = 1;
            $order['orderstatus'] = 1;
            // 添加订单， 增加售出数量
            order::create($order);
            source_extra::where("id", $sid)->increment('soldnumber', '1');

            if($sources['thresh'] != 0) {        //当料为免费但限制数量时，将此料的销售量和购买量统计加1
                client_extra::where('id', $uid)->increment('buynum');  // 已经购买的料总数
                client_extra::where('id', $sources['uid'])->increment('soldnum');   // 销售的料总数加1
            }

            $return['status_code'] = '200';
            $return['free'] = $free;
            return response()->json($return);
        }

        if (empty($order['selledid']) || empty($order['sourceid'])) {
                $log_content = 'czhfixorder|controller=OrdersController:148|uid=' . $uid . '|sid=' . $sid .'|clients=' . json_encode($clients) .'|request='. json_encode($request->all()) . '|';
                \Log::info($log_content);
        }

        // 生成支付订单并请求微信预支付订单
        order::create($order);

        // 添加微信预支付订单
        $pre['body'] = "给料小程序资源";   // 商品描述
        $pre['order_number'] = $ordernum; // 订单号
        $pre['money'] = $sources['price'] * 100;    // 金额需进行转换， 微信已分为单位， 元转分
        $pre['openId'] = $clients['openid'];    // 用户openid
        $wx_pre = $this->servers($pre);

        $data['timestamp'] = (string)time();
        $data['appId'] = $wx_pre['appid'];
        $data['nonceStr'] = $wx_pre['nonce_str'];
        $data['package'] = "prepay_id=".$wx_pre['prepay_id'];
        $data['signType'] = "MD5";
        $keywords = 'appId='.$wx_pre['appid'].'&nonceStr='.$wx_pre['nonce_str'].'&package=prepay_id='.$wx_pre['prepay_id'].'&signType=MD5&timeStamp='.$data['timestamp'].'&key=SDJy3O9G8pQevPY1Jb5Ayy2LSIzK4yB3';
        $data['paySign'] = md5($keywords);

        $return['status_code'] = '200';
        $return['free'] = $free;
        $return['data'] = $data;
        return response()->json($return);

    }

    private function is_weixin() {
      if (strpos($_SERVER['HTTP_USER_AGENT'],'MicroMessenger') !== false) {
        return true;
      }
      return false;
    }

    public function getSourceOrderStatus(Request $request) {
      $token = JWTAuth::getToken();
      $uid = $request->input('uid', '');
      $sids = $request->input('sids', '');
      $sids = json_decode($sids, true);

      $clients = $this->UserInfo($token);
      if (empty($token) || $clients['id'] != $uid) {
        $return['status_code'] = 10001;
        $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
        return response()->json($return);
      }

      $orders = order::where('buyerid', $uid)->whereIn('sid', $sids)->whereRaw('orderstatus & 1')->get()->ToArray();
      if (empty($orders)) {
        return response()->json(['status_code' => 200, 'data' => ['sids' => []]]);
      } else {
        return response()->json(['status_code' => 200, 'data' => ['sids' => array_column($orders, 'sid')]]);
      }
    }
    
    public function batchWxPayNotify() {
      $xml = file_get_contents("php://input");
      $data = $this->xmlToArray($xml);
      if ($data['return_code'] == "SUCCESS") {
        $orders = order::where("batch_order_num", $data['out_trade_no'])->get()->ToArray();
        foreach($orders as $orderInfo) {
          if ($orderInfo['orderstatus'] == 0) {
            $this->updateOrderStatus($orderInfo);
          }
        }
      } else {
        \Log::INFO($data['return_msg']);
      }
      $return['return_code'] = 'SUCCESS';
      $return['return_msg'] = 'OK';
      $returnXml = '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
      return $returnXml;
    }

    public function alipayNotify(Request $request) {
      $data = $request->all();
      $aop = new \AopCertClient ();
      $alipayCertPath = config('pay.alipay.cert_public_key_rsa');
      $alipay_public_key = $aop->getPublicKey($alipayCertPath);
      $config = [
        'app_id' => config("pay.alipay.appid"),
        'merchant_private_key' => config("pay.alipay.private_key"),
        'notify_url' => config('constants.backend_domain') . '/pub/order/alipaynotify',
        'return_url' => config('constants.backend_domain') . '/pub/order/alipaysuccess',
        'charset' => "UTF-8",
        'sign_type' => "RSA2",
        'gatewayUrl' => "https://openapi.alipay.com/gateway.do",
        //'alipay_public_key' => config("pay.alipay.alipay_publick_key"),
        'alipay_public_key' => $alipay_public_key,
      ];
      $buyerid = 0;
      $aliPaySevice = new \AlipayTradeService($config);
      $result = $aliPaySevice->check($data);

      if($result) {//验证成功
        $out_trade_no = $data['out_trade_no'];
        if($data['trade_status'] == 'TRADE_FINISHED') {
        
        } else if ($data['trade_status'] == 'TRADE_SUCCESS') {
          $orders = order::where("batch_order_num", $data['out_trade_no'])->get()->ToArray();
          foreach($orders as $orderInfo) {
            if ($orderInfo['orderstatus'] == 0) {
              $this->updateOrderStatus($orderInfo);
            }
            $buyerid = $orderInfo['buyerid'];
          }
          if ($buyerid) {
            client_extra::auto_open_wx($buyerid);
          }
          return 'success';
        }
        echo "success";   //请不要修改或删除
      }else {
        echo "fail";  //请不要修改或删除
      }
    }

    public function alipaySuccess() {
      return view('wechat/success');
    }

  private function getMchAccount($totals = 0, $special_uid = 0, $uid = 0) {
      $total = 2;
      $account_no = 0;    //商户账号对应到数据库的编号，master为0，其他账号为配置文件中slave_[NO]中的NO
      //$account_no = 3;    //商户账号对应到数据库的编号，master为0，其他账号为配置文件中slave_[NO]中的NO
      $account = 'master';  //对应商户号的配置，master为当前主账号，slave_[NO]为备用账号
     // $account = 'slave_' . $account_no;  //对应商户号的配置，master为当前主账号，slave_[NO]为备用账号
     // if (env('APP_ENV') == 'dev') {
     //       $account_no = 0;
     //       $lastMchAccount = (Redis::get('mch:last_mch_account')) ? : 1;
     //       $account_no = ($lastMchAccount == $total) ? 1 : $lastMchAccount + 1;
     //       $account = 'slave_' . $account_no;
     //       Redis::set('mch:last_mch_account', $account_no);
     // }
     // if (env('APP_ENV') == 'production') {
     //     $r_key = 'new_mch_300';
     //     //if ($totals >= $this->min_totals && $this->max_totals >= $totals) {
     //       //$hours = ltrim(date('H', time()), 0);
     //       //if ($hours >= $this->min_hours && $this->max_hours >= $hours) {
     //           $wheel_num = Redis::incr($r_key);
     //           //$wheel_mod = $wheel_num % $this->max_wheel;
     //           if ($wheel_num >= $this->max_wheel) {
     //               \Log::info('czh_successed');
     //               $account_no = 2;
     //               $account = 'slave_' . $account_no;
     //               Redis::set($r_key, 0);
     //           }
     //       //}
     //     //}
     // }
     // if (env('APP_ENV') == 'production') {
     //     if ($uid) {
     //         $begin = date('Y-m-d', time());
     //         //$begin = '2018-09-07';
     //         $end = date('Y-m-d', time() + 86400);
     //         //$end = '2018-09-08';
     //         $u_info = client::where('id', $uid)->where('createtime', '>', $begin)->
     //             where('createtime', '<', $end)->first();
     //         if ($u_info) {
     //           $lastMchAccount_new = (Redis::get('mch:last_mch_account_new')) ? : 1;
     //           if ($lastMchAccount_new > 1) {
     //               $account_no = 1;
     //               $account = 'slave_' . $account_no;
     //               Redis::set('mch:last_mch_account_new', 1);
     //           } else {
     //               Redis::set('mch:last_mch_account_new', 2);
     //           }
     //         }
     //     }
     // }
      $wx_pay_users_3671 = config('constants.wx_pay_users_3671');
      if (in_array($special_uid, $wx_pay_users_3671)) {
            $account_no = 1;
            $account = 'slave_' . $account_no;
      }
        return [$account_no, $account];
    }


    public function genBatchOrder(Request $request) {
      $token = JWTAuth::getToken();
      $uid = $request->input('uid', '');
      $target = $request->input('channel', 'wxpay');
      $totals = $request->input('totals', 0);
      $page = $request->input('page', 1);
      $sids = $request->input('sids', '');
      $sids = json_decode($sids, true);

      //微信内
      if ($target == 'alipay' && $this->is_weixin()) {
        return view('wechat/redirect')->with('token', $token)->with('uid', $uid)->with('page', $page);
      }
      $clients = $this->UserInfo($token);
      if (empty($token) || $clients['id'] != $uid) {
        $return['status_code'] = 10001;
        $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
        return response()->json($return);
      }

      $batchOrders = array();
      $batch_order_num = $this->order_number(true);
      $special_uid = 0;
      foreach($sids as $sid) {
        $sourceInfo = source::select('status', 'thresh', 'sid', 'pack_type','play_time','play_end','free_watch','order_status', 'uid', 'price', 'pack_day', 'delayed_day')->where('source.sid', $sid)->first();
        if(!$sourceInfo || $sourceInfo['status'] != 0 ) {
          return response()->json(['status_code' => 10003, 'error_message' => '资源异常， 已被删除或下架', 'status' => $sourceInfo['status']]);
        }

         //比赛一小时后不可买，检查是否可买（1可买，0不可买）
        if ($sourceInfo['pack_type'] == 3) {
          if ($sourceInfo['play_end'] == 1) {
            if(time() >= $sourceInfo['play_time'] +  60 * 60){
              return response()->json(['status_code' => 10006, 'error_message' => '资源已过售卖时间']);
            }
          }
        }
        //设置红黑单的料不允许再次购买
        if ($sourceInfo['pack_type'] == 2) {
          if ($sourceInfo['order_status'] != 0) {
            return response()->json(['status_code' => 10007, 'error_message' => '资源判定结果，不允许售卖']);
          }
        }
        // 查看是否已购买过该资源
        $buy_sources = order::where('buyerid', $uid)->where('sid', $sid)->where('pack_type', 0)->whereRaw('orderstatus & 1')->first();
        if( !empty($buy_sources) ) {
          if ($buy_sources['orderstatus'] < 5) {
            return response()->json(['status_code' => 10005, 'error_message' => '用户已购买过该资源']);   // 排除续费订单
          }
        }

        //记录用户支付步骤状态
        purchase_record::setPurchaseRecord($sid, $uid, $sourceInfo['uid'], 2);
        
        $currentTime = date('Y-m-d H:i:s');
        $ordernum = $this->order_number(true);
        $orderInfo = array(
          'sid' => $sid,
          'pack_type' => $sourceInfo['pack_type'],
          'buyerid' => $uid,
          'selledid' => $sourceInfo['uid'],
          'sourceid' => $sourceInfo['sid'],
          'price' => $sourceInfo['price'],
          'ordernum' => $ordernum,
          'score' => ($sourceInfo['pack_type'] == 1) ? '1'.time() : time(),
          'is_batch' => (count($sids) > 1) ? 1 : 0,
          'batch_order_num' => (count($sids) > 1) ? $batch_order_num : $ordernum,
          'createtime' => $currentTime,
          'modifytime' => $currentTime,
          'start_time' => $currentTime
        );

        // 判断料是否为免费， 免费则直接修改状态为已购买并返回
        if( $sourceInfo['price'] == 0 ) {
          if ($sourceInfo['pack_type'] == 1) { // 更新续费订单的renew状态和end_time
            $lastcontent = contents::where('sid',$orderInfo['sid'])->where('is_check',1)->orderBy('createtime','desc')->first();
            if(strtotime($lastcontent['createtime'])+24*3600>=time()){
              $orderInfo['start_time'] = $lastcontent['createtime'];
            }else{
              $orderInfo['start_time'] = date('Y-m-d H:i:s', time());     //用户在24小时内未更新 
            }
            $historyOrders = order::where('sid', $sourceInfo['sid'])->where('buyerid', $uid)->whereRaw('orderstatus in (1,3)')->get()->ToArray();
            if (!empty($historyOrders)) {
              foreach ($historyOrders as $horder) {
                order::where('id', $horder['id'])->update([
                  'orderstatus' => $horder['orderstatus'] | 4,
                  'end_time' => date('Y-m-d H:i:s', strtotime($horder['createtime'])+($sourceInfo['pack_day']+$sourceInfo['delayed_day'])*86400),
                  'modifytime'=>date('Y-m-d H:i:s')
                ]);
              }
            }
          }
          
          source_extra::where("id", $sourceInfo['sid'])->increment('soldnumber', '1');

          $orderInfo['orderstatus'] = 1;
          $orderInfo['payment'] = 0;
          //记录用户支付步骤状态
          purchase_record::setPurchaseRecord($sid, $uid, $sourceInfo['uid'], 3);
        } else {
          //非免费
          $orderInfo['orderstatus'] = 0;
          $orderInfo['payment'] = ($target == 'wxpay') ? 1 : 3;
        }
        if (empty($orderInfo['selledid']) || empty($orderInfo['sourceid'])) {
                $log_content = 'czhfixorder|controller=OrdersController:379|uid=' . $uid . '|sid=' . $sid .'|clients=' . json_encode($clients) .'|request='. json_encode($request->all()) . '|';
                \Log::info($log_content);
        }
        $batchOrders[] = $orderInfo;
        $special_uid = $orderInfo['selledid'];
      }

      //商户号负载分配
      list($mch_account, $mch_acct_conf_key) = $this->getMchAccount($totals, $special_uid, $uid);
      foreach($batchOrders as $order_index => $order_info) {
        $batchOrders[$order_index]['mch_account'] = $mch_account;
      }
      order::insert($batchOrders);        //批量添加订单
      if($totals == 0) {
        return response()->json(['status_code' => 200, 'data' => ['free' => 1, 'sids' => $sids]]);
      }
      
      $pre_order_number = (count($batchOrders) > 1) ? $batchOrders[0]['batch_order_num'] : $batchOrders[0]['ordernum'];
      if ($target == 'wxpay') {
        //===========wxpay=============
        $input = new WxPayUnifiedOrder();
        $input->SetBody('咨询解答收费');
        $input->SetOut_trade_no($pre_order_number); //订单号
        $input->SetTotal_fee($totals * 100);              // 金额
        $input->SetNotify_url(config('constants.backend_domain') . '/pub/order/batchnotify');                  // 推送信息
        $input->SetTrade_type("JSAPI");
        $input->SetOpenid($clients['serviceid']);  // 用户openId

	      $config['appid'] = config('wxxcx.wechat_appid');
        $config['secret'] = config('wxxcx.wechat_appsecret');
        //这里通过 $mch_acct_conf_key 决定使用哪个商户好去支付
        $mch_config = config('pay.wxpay.'. $mch_acct_conf_key);
        $config = array_merge($config, $mch_config);

        $wx_pre = \WxPayApi::unifiedOrder($input, $config);

        if ($wx_pre['return_code'] == 'SUCCESS') {
          $redisKey = "Order_Prepay_Id";
          $sql = "UPDATE `order` SET `prepay_id`='" . $wx_pre['prepay_id'] . "' WHERE batch_order_num= '" . $pre_order_number . "'";
          Redis::lpush($redisKey, $sql);

          $timestamp = (string)time();
          $keywords = 'appId=' . $wx_pre['appid'] . '&nonceStr=' . $wx_pre['nonce_str'] . '&package=prepay_id=' . $wx_pre['prepay_id'] . '&signType=MD5&timeStamp=' . $timestamp . '&key='  . $config['mch_secret_key'];
          $res = array(
            'timestamp' => $timestamp,
            'appId' => $wx_pre['appid'],
            'nonceStr' => $wx_pre['nonce_str'],
            'package' => "prepay_id=" . $wx_pre['prepay_id'],
            'signType' => 'MD5',
            'paySign' => md5($keywords),
            'orderNo' => $pre_order_number
          );
          return response()->json(['status_code' => 200, 'data' => $res]);
        } else {
          return response()->json(['status_code' => $wx_pre['return_code'], 'error_message' => $wx_pre['return_msg']]);
        }
      } else {
        //$pre_order_number = time() . $pre_order_number;
        $aop = new \AopCertClient ();
        $appCertPath = config("pay.alipay.cert_public_key");
        $alipayCertPath = config('pay.alipay.cert_public_key_rsa');
        $rootCertPath = config('pay.alipay.root_cert');

        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = config("pay.alipay.appid");
        $aop->rsaPrivateKey = config("pay.alipay.private_key");
        //$aop->alipayrsaPublicKey=config("pay.alipay.alipay_publick_key");
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';

        $aop->alipayrsaPublicKey = $aop->getPublicKey($alipayCertPath);
        $aop->isCheckAlipayPublicCert = true;
        $aop->appCertSN = $aop->getCertSN($appCertPath);
        $aop->alipayRootCertSN = $aop->getRootCertSN($rootCertPath);
        $aop->postCharset = 'utf-8';
        $aop->format='json';
        $aop->signType= "RSA2";
        $return_url = config('constants.backend_domain') . '/pub/order/alipaysuccess';
        $notify_url = config('constants.backend_domain') . '/pub/order/alipaynotify';
        $aop->notify_url = $notify_url;
        $aop->return_url = $return_url;
        $request = new \AlipayTradeWapPayRequest();
        $request->setBizContent("{" .
            "\"subject\":\"咨询解答收费\"," .
            "\"out_trade_no\":\"" . $pre_order_number . "\"," .
            "\"total_amount\":\"" . $totals . "\"," .
            "\"quit_url\":\"" . $return_url . "\"," .
            //"\"notify_url\":\"" . $notify_url . "\"," .
            //"\"return_url\":\"" . $return_url . "\"," .
            "\"product_code\":\"QUICK_WAP_WAY\"," .
            "}");
        $request->setNotifyUrl($notify_url);
        $request->setReturnUrl($return_url);
        $result = $aop->pageExecute($request);
        echo $result;
        //return '';
        //var_dump($result);die;
        //$payRequestBuilder = new \AlipayTradeWapPayContentBuilder();
        //$payRequestBuilder->setBody('咨询解答收费');
        //$payRequestBuilder->setSubject('咨询解答收费');
        //$payRequestBuilder->setOutTradeNo($pre_order_number);
        //$payRequestBuilder->setTotalAmount($totals);
        //$aop = new \AopCertClient ();
        //$alipayCertPath = config('pay.alipay.cert_public_key_rsa');
        //$alipay_public_key = $aop->getPublicKey($alipayCertPath);
        //$config = [
        //  'app_id' => config("pay.alipay.appid"),     //应用ID,您的APPID。
        //  'merchant_private_key' => config("pay.alipay.private_key"),       //商户私钥，您的原始格式RSA私钥
        //  'notify_url' => config('constants.backend_domain') . '/pub/order/alipaynotify',
        //  'return_url' => config('constants.backend_domain') . '/pub/order/alipaysuccess',
        //  'charset' => "UTF-8",         //编码格式
        //  'sign_type' => "RSA2",        //签名方式
        //  'gatewayUrl' => "https://openapi.alipay.com/gateway.do",        //支付宝网关
        //  //支付宝公钥,查看地址：https://openhome.alipay.com/platform/keyManage.htm 对应APPID下的支付宝公钥。
        //  //'alipay_public_key' => config("pay.alipay.alipay_publick_key"),
        //  'alipay_public_key' => $alipay_public_key,
        //];
        //$payResponse = new \AlipayTradeService($config);
        //$payResponse->wapPay($result, $return_url, $notify_url);
        //var_dump($payResponse);die;
      }
    }

    public function generateOrder(Request $request)
    {
        $token = JWTAuth::getToken();
        $uid = $request->input('uid', '');
        $sid = $request->input('sid', '');
        $follow = $request->input('follow', true);
        $clients = $this->UserInfo($token);
        if (empty($token) || $clients['id'] != $uid) {
            $return['status_code'] = 10001;
            $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
            return response()->json($return);
        }

        // 判断料状态， 销售数量是否超过
        $sources = source::select('status', 'thresh', 'sid', 'pack_type','play_time','play_end','free_watch','order_status', 'uid', 'price', 'pack_day', 'delayed_day')->where('source.sid', $sid)->first();
        $sources['soldnumber'] = source_extra::where('sid',$sources['sid'])->value('soldnumber');
        if(!$sources || $sources['status'] != 0 ) {
            $return['status_code'] = '10003';
            $return['error_message'] = '资源异常， 已被删除或下架';
            $return['status'] = $sources['status'];
            return response()->json($return);
        }

        if( $sources['thresh'] != 0) {
            if( $sources['soldnumber'] + 1 > $sources['thresh'] ) {
                $return['status_code'] = '10004';
                $return['error_message'] = '资源已售罄';
                return response()->json($return);
            }
        }



        //比赛一小时后不可买，检查是否可买（1可买，0不可买）
        if ($sources['pack_type'] == 3) {
            if ($sources['play_end'] == 1) {
                $now = time();
                $getInfoTime = $sources['play_time'] +  60 * 60;
                if($now>=$getInfoTime){
                    $return['status_code'] = '10006';
                    $return['error_message'] = '资源已过售卖时间';
                    return response()->json($return);
                    die;

                }
            }
        }
        //设置红黑单的料不允许再次购买
        if ($sources['pack_type'] == 2) {
            if ($sources['order_status'] != 0) {
                $return['status_code'] = '10007';
                $return['error_message'] = '资源判定结果，不允许售卖';
                return response()->json($return);
                die;
            }
        }


        // 查看是否已购买过该资源
        $buy_sources = order::where('buyerid', $uid)
            ->where('sid', $sid)
            ->where('pack_type', 0)
            ->whereRaw('orderstatus & 1')
            ->first();
        if( !empty($buy_sources) ) {
            if ($buy_sources['orderstatus'] < 5) {
                // 排除续费订单
                $return['status_code'] = '10005';
                $return['error_message'] = '用户已购买过该资源';
                return response()->json($return);
            }
        }

        $today = date('Y-m-d H:i:s');
        $order['sid'] = $sources['sid'];
        $order['pack_type'] = $sources['pack_type'];
        $order['buyerid'] = $uid;
        $order['selledid'] = $sources['uid'];
        $order['sourceid'] = $sources['sid'];
        $order['price'] = $sources['price'];
        if ($sources['pack_type'] == 1) {
            $order['score'] = '1'.time();
        } else {
            $order['score'] = time();
        }
        $order['createtime'] = $today;
        $order['modifytime'] = $today;
        $order['pack_type'] = $sources['pack_type'];
        $order['start_time'] = date('Y-m-d H:i:s', time());

        //记录用户支付步骤状态
        purchase_record::setPurchaseRecord($order['sid'], $order['buyerid'], $order['selledid'], 2);

        if($follow){
            //记录用户关注
            $follow_info = follow::where('star', $order['selledid'])->where('fans', $order['buyerid'])->first();
            if(empty($follow_info)){
                $follow = [];
                $follow['star'] = $order['selledid'];
                $follow['fans'] = $order['buyerid'];
                $follow['create_time'] = time();
                follow::create($follow);
            }else{
                follow::where('star', $order['selledid'])->where('fans', $order['buyerid'])->update([
                    'status' => 1
                ]);
            }
        }


        // 判断料是否为免费， 免费则直接修改状态为已购买并返回
        $free = 0;
        if( $sources['price'] == 0 ) {
            if ($sources['pack_type'] == 1) { // 更新续费订单的renew状态和end_time
                $lastcontent = contents::where('sid',$order['sid'])->where('is_check',1)->orderBy('createtime','desc')->first();
                if(strtotime($lastcontent['createtime'])+24*3600>=time()){
                    $order['start_time'] = $lastcontent['createtime'];
                }else{
                    //用户在24小时内未更新
                    $order['start_time'] = date('Y-m-d H:i:s', time());
                }
                $historyOrders = order::where('sid', $sources['sid'])->where('buyerid', $uid)->whereRaw('orderstatus in (1,3)')->get()->ToArray();
                if (!empty($historyOrders)) {
                    foreach ($historyOrders as $horder) {
                        order::where('id', $horder['id'])->update([
                            'orderstatus' => $horder['orderstatus'] | 4,
                            'end_time' => date('Y-m-d H:i:s', strtotime($horder['createtime'])+($sources['pack_day']+$sources['delayed_day'])*86400),
                            'modifytime'=>date('Y-m-d H:i:s')
                        ]);
                    }
                }
            }

            $free = 1;
            $order['ordernum'] = $this->order_number(true);
            $order['orderstatus'] = 1;
            $order['payment'] = 0;
            // 添加订单， 增加售出数量

            if (empty($order['selledid']) || empty($order['sourceid'])) {
                $log_content = 'czhfixorder|controller=OrdersController:592|uid=' . $uid . '|sid=' . $sid .'|clients=' . json_encode($clients) .'|request='. json_encode($request->all()) . '|';
                \Log::info($log_content);
            }

            order::create($order);
            source_extra::where("id", $sid)->increment('soldnumber', '1');

            if ($sources['thresh'] != 0) {        //当料为免费但限制数量时，将此料的销售量和购买量统计加1
                client_extra::where('id', $uid)->increment('buynum');  // 已经购买的料总数
                client_extra::where('id', $sources['uid'])->increment('soldnum');   // 销售的料总数加1
            }

            //记录用户支付步骤状态
            purchase_record::setPurchaseRecord($order['sid'], $order['buyerid'], $order['selledid'], 3);

            $return['status_code'] = '200';
            $return['free'] = $free;
            $res['free'] = $free;
            $return['data'] = $res;
            return response()->json($return);
        }
        $requestCount = 0;
        $pre['money'] = $sources['price'] * 100;
        $pre['openId'] = $clients['serviceid'];
        // $pre['body'] = '小程序资源';
        $pre['body'] = '咨询解答收费';

        $payment = config('pay.payment');
        // $payment = 'qfpay';

        switch ($payment) {
            case 'wxpay':
                //===========wxpay=============
                $ordernum = $this->order_number(true);
                $order['payment'] = 1;
                $order['ordernum'] = $ordernum;
                $order['id'] = order::insertGetId($order);

                $pre['order_number'] = $ordernum;
                $pre['id'] = $order['id'];
                $pay_res = $this->wxpay($pre);
                if ($pay_res) {
                    $pay_res['data']['free'] = $free;
                    $pay_res['data']['orderNo'] = $order['ordernum'];
                }
                return response()->json($pay_res);

                break;
            case 'hypay':
                //==========hypay===============
                $ordernum = $this->order_number(FALSE);
                $order['payment'] = 2;
                $order['ordernum'] = $ordernum;
                $pre['order_number'] = $ordernum;
                $pre['sourceid'] = $order['sourceid'];
                order::create($order);
                $pay_res = $this->hypay($pre);
                if($pay_res['status_code'] == 200){
                    $pay_res['data']['free'] = $free;
                    $pay_res['data']['orderNo'] = $order['ordernum'];
                }
                return response()->json($pay_res);
                break;
            case 'qfpay':
                //==========hypay===============
                $ordernum = $this->order_number(true);
                $order['payment'] = 4;
                $order['ordernum'] = $ordernum;
                $order['id'] = order::insertGetId($order);
                $pre['order_number'] = $ordernum;
                $pre['id'] = $order['id'];
                $pay_res = $this->qfPay($pre);
                if($pay_res['status_code'] == 200){
                    $pay_res['data']['free'] = $free;
                    $pay_res['data']['orderNo'] = $order['ordernum'];
                }
                return response()->json($pay_res);
                break;
        }

    }

    public function qfPay($data) {
        $url = "https://openapi.qfpay.com/trade/v1/payment";

        $params['txamt'] = $data['money'];
        $params['txcurrcd'] = "CNY";
        $params['pay_type'] = "800207";
        $params['out_trade_no'] = $data['order_number'];
        $params['sub_openid'] = $data['openId'];
        $params['goods_name'] = $data['body'];
        $params['txdtm'] = date("Y-m-d H:i:s",time());
        ksort($params);
        $string = $this->getJoin($params,1,'&');
        $string = $string.config('pay.qfpay.key');


        $custom = array(
            "X-QF-APPCODE" => config('pay.qfpay.code'),
            "X-QF-SIGN" => md5($string),
        );

        $headerArr = array();
        foreach ($custom as $n => $v) {
            $headerArr[] = $n . ':' . $v;
        }
        $httpResult = $this->httpRequestOnce($url, $params, 'post', false, $headerArr);
        if($httpResult['result']){
            $result = json_decode($httpResult['msg'],1);
            if ($result['respcd'] == '0000') {
                $wx_pre = $result['pay_params'];
                $redisKey = "Order_Prepay_Id";
                $sql = "UPDATE `order` SET `porder`='" . $result['syssn'] . "' WHERE id= '" . $data['id'] . "'";
                Redis::lpush($redisKey, $sql);
                $return['status_code'] = 200;
                $res['timestamp'] = $wx_pre['timeStamp'];
                $res['appId'] = $wx_pre['appId'];
                $res['nonceStr'] = $wx_pre['nonceStr'];
                $res['package'] = $wx_pre['package'];
                $res['signType'] = $wx_pre['signType'];
                $res['paySign'] = $wx_pre['paySign'];
                $return['data'] = $res;
            } else {
                $return['status_code'] = $result['respcd'];
                $return['error_message'] = 'error';
            }
            return $return;
        }else{
            $return['status_code'] = '10001';
            $return['error_message'] = 'error';
            return $return;
        }
    }
    public function getJoin($arrVal,$joinType=1,$joinChar='&',$sequenceKey=null){
        $resArr = array();
        if(is_array($arrVal) && !empty($arrVal)) {
            if(empty($sequenceKey)){
                foreach ($arrVal as $k => $v) {
                    if($joinType == 1) array_push($resArr,$k.'='.$v);
                    else if($joinType == 2) array_push($resArr,$v);
                }
            }else{
                $arrKey = explode(',',$sequenceKey);
                foreach ($arrKey as $v) {
                    if(!isset($arrVal[$v])) return false;
                    if($joinType == 1) array_push($resArr,$v.'='.$arrVal[$v]);
                    else if($joinType == 2) array_push($resArr,$arrVal[$v]);
                }
            }
        }
        return join($joinChar,$resArr);
    }
    public function httpRequestOnce($url, $param, $type = 'post', $verify = '', $header = '') {
        if (!empty($param) && is_array($param)) {
            $param = http_build_query($param);
        }
        $curlHandle = curl_init($url . ($type != 'post' ? "?$param" : ''));                                        // 初始化curl
        $options = array(
            CURLOPT_HEADER => false,
            // 不显示返回的Header区域内容
            CURLOPT_RETURNTRANSFER => true,
            // 获取的信息以文件流的形式返回
            CURLOPT_CONNECTTIMEOUT => 20,
            // 连接超时
            CURLOPT_TIMEOUT => 40
            // 总超时
        );
        if ($type == 'post') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $param;
        }
        if ($verify !== '') {
            $options[CURLOPT_SSL_VERIFYPEER] = $verify; // 验证对方提供的（读取https）证书是否有效，过期，或是否通过CA颁发的！
            $options[CURLOPT_SSL_VERIFYHOST] = $verify; // 从证书中检查SSL加密算法是否存在
        }
        if ($header !== '') {
            $options[CURLOPT_HTTPHEADER] = $header; //header信息设置
        }
        curl_setopt_array($curlHandle, $options);
        $httpResult = curl_exec($curlHandle);
        $errorMsg = curl_error($curlHandle);
        if (false === $httpResult || !empty($errorMsg)) {
            $errorNo = curl_errno($curlHandle);
            $errorInfo = curl_getinfo($curlHandle);
            curl_close($curlHandle);
            return array(
                'result' => false,
                'msg' => $errorMsg,
                'url' => "[$type]$url?" . urldecode($param),
                'errno' => $errorNo,
                'errinfo' => $errorInfo
            );
        }
        curl_close($curlHandle);//关闭curl
        return array(
            'result' => true,
            'msg' => $httpResult,
            'url' => "[$type]$url?" . urldecode($param)
        );
    }
    public function qfNotify(Request $request) {
        $data = file_get_contents("php://input");
        $sign = $request->header('X-QF-SIGN');
        $sign_data = strtoupper(md5($data.config('pay.qfpay.key')));
        if
        ($sign_data==$sign){
            $orderInfo = json_decode($data,1);
            if($orderInfo['notify_type']=="payment" && $orderInfo['status']==1){
                $order = order::where("ordernum", $orderInfo['out_trade_no'])->first();
                if ($order['orderstatus'] == 0) {
                    $this->updateOrderStatus($order);
                }
            }
            echo "success";
        }
    }

    public function wxpay($data) {
        $input = new WxPayUnifiedOrder();
        $input->SetBody($data['body']);
        $input->SetOut_trade_no($data['order_number']); //订单号
        $input->SetTotal_fee($data['money']);  // 金额
        $input->SetNotify_url(config('pay.wxpay.notify_url'));  // 推送信息
        $input->SetTrade_type("JSAPI");
        $input->SetOpenid($data['openId']);  // 用户openId

        $config['appid'] = config('wxxcx.wechat_appid');
        $config['secret'] = config('wxxcx.wechat_appsecret');
        $config['mchid'] = config('pay.wxpay.mchid');
        $config['mch_secret_key'] = config('pay.wxpay.mch_secret_key');
        $config['sslcert_path'] = config('pay.wxpay.sslcert_path');
        $config['sslkey_path'] = config('pay.wxpay.sslkey_path');

        $wx_pre = \WxPayApi::unifiedOrder($input, $config);
        header("Content-Type: application/json");
        if ($wx_pre['return_code'] == 'SUCCESS') {
            $redisKey = "Order_Prepay_Id";
            $sql = "UPDATE `order` SET `prepay_id`='" . $wx_pre['prepay_id'] . "' WHERE id= '" . $data['id'] . "'";
            Redis::lpush($redisKey, $sql);
            //微信prepay_id入库
            /*
                  order::where('id', $data['id'])->update([
                      'prepay_id' => $wx_pre['prepay_id']
                  ]);
            */
            $return['status_code'] = 200;
            $res['timestamp'] = (string)time();
            $res['appId'] = $wx_pre['appid'];
            $res['nonceStr'] = $wx_pre['nonce_str'];
            $res['package'] = "prepay_id=" . $wx_pre['prepay_id'];
            $res['signType'] = 'MD5';
            $keywords = 'appId=' . $wx_pre['appid'] . '&nonceStr=' . $wx_pre['nonce_str'] . '&package=prepay_id=' . $wx_pre['prepay_id'] . '&signType=MD5&timeStamp=' . $res['timestamp'] . '&key=SDJy3O9G8pQevPY1Jb5Ayy2LSIzK4yB3';
            $res['paySign'] = md5($keywords);
            $return['data'] = $res;
        } else {
            $return['status_code'] = $wx_pre['return_code'];
            $return['error_message'] = $wx_pre['return_msg'];
        }
        return $return;
    }

    public function wxNotify() {
        $xml = file_get_contents("php://input");
        $data = $this->xmlToArray($xml);
        if ($data['return_code'] == "SUCCESS") {
            $order = order::where("ordernum", $data['out_trade_no'])->first();
            if ($order['orderstatus'] == 0) {
                $this->updateOrderStatus($order);
            }
        } else {
            \Log::INFO($data['return_msg']);
        }
        $return['return_code'] = 'SUCCESS';
        $return['return_msg'] = 'OK';
        $returnXml = '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
        return $returnXml;
    }

    public function hypay($data) {
        $params['openID'] = $data['openId'];            //微信openID
        $params['orderNo'] = $data['order_number'];     //唯一订单号，每次请求必须不同(请全部使用数字组合)
        $params['name'] = $data['body'];                //商品名称
        $params['total'] = $data['money'];              //总金额，整数，单位为分
        $params['returnUrl'] = config('pay.hypay.returnUrl') . '?sid=' . $data['sourceid'] . '&from=payment';         //订单支付同步回调地址
        $params['merchantNo'] = config('pay.hypay.merchantNo'); //获取配置文件里面的商户号
        $params['nofityUrl'] = config('pay.hypay.notifyUrl'); //获取配置文件里面的回调(订单支付异步回调地址)
//      \Log::INFO($params['orderNo'] . '预支付============' . time());
        $order = $this->payHttpPost('wxgzh/api', $params);
        $order = $this->signCheck($order); //对返回的数据进行验签名
        $wx_pre = json_decode($order, true);
        if ($wx_pre['code'] == 1) {
            $pay_pre = json_decode($wx_pre['result']['formfield'], true);
            $return['status_code'] = 200;
            $res['timestamp'] = $pay_pre['timeStamp'];
            $res['appId'] = $pay_pre['appId'];
            $res['nonceStr'] = $pay_pre['nonceStr'];
            $res['package'] = $pay_pre['package'];
            $res['signType'] = $pay_pre['signType'];
            $res['paySign'] = $pay_pre['paySign'];
            $return['data'] = $res;
        } else {
            $return['status_code'] = $wx_pre['code'];
            $return['error_message'] = $wx_pre['msg'];
        }
        return $return;
    }

    public function hyNotify(Request $request) {
        /*key: code value: 1
          key: total value: 1
          key: orderNo value: 201791154751371
          key: merchantNo value: 800440054111002
          key: timestamp value: 1504252077530
          key: sign value: 3294b86482178fc8a8a55c4c87e70d6e*/
        $data = $request->all();
//      \Log::INFO($data['orderNo'] . '回调============' . time());
        $sign_data = $data;
        unset($sign_data['sign']);
        $sign = $this->createSign($sign_data);
        if ($sign == $data['sign']) {
            if ($data['code'] == 1) {
                // 修改订单状态 以及 增加资源表内, 销量， 销量金额
                $order = Order::where("ordernum", $data['orderNo'])->first();
                if ($order['orderstatus'] == 0) {
                    $this->updateOrderStatus($order);
                    return 'success';
                }
            } else {
                echo "获取信息有误";
            }
        } else {
            echo "支付签名不匹配";
        }
    }
    
    public function orderStatus(Request $request) {
      $orderNum = $request->input('ordernum', '');
      
      $orders = order::where('ordernum', $orderNum)->get()->ToArray();
      if (empty($orders)) {
        $orders = order::where('batch_order_num', $orderNum)->get()->ToArray();
      }

      if (empty($orders)) {
        return response()->json([
          'status_code' => 10015,
          'error_message' => '订单不存在'
        ]);
      }

      $mch_account = $orders[0]['mch_account'];

      $input = new WxPayOrderQuery();
      $input->SetOut_trade_no($orderNum);
      $config['appid'] = config('wxxcx.wechat_appid');
      $config['secret'] = config('wxxcx.wechat_appsecret');
      $mch_config_key = ($mch_account == 0) ? 'master' : 'slave_' . $mch_account;
      $mch_config = config('pay.wxpay.'. $mch_config_key);
      $config = array_merge($config, $mch_config);

      $orderstatus = \WxPayApi::orderQuery($input, $config);

      if ($orderstatus['return_code'] == 'SUCCESS') {
        if ($orderstatus['trade_state'] == 'SUCCESS') {
          foreach($orders as $order) {
            if ($order['orderstatus'] == 0) {
              $this->updateOrderStatus($order);
            }
          }
          return response()->json([
            'status_code' => 200, 
            'data' => ['code' => 1, 'message' => '支付成功']
          ]);
        } else if ($orderstatus['trade_state'] == 'NOTPAY') {
          return response()->json([
            'status_code' => 200,
            'data' => ['code' => 0, 'message' => '等待支付']
          ]);
        } else if ($orderstatus['trade_state'] == 'USERPAYING') {
          return response()->json([
            'status_code' => 200,
            'data' => ['code' => 0, 'message' => '等待支付']
          ]);
        } else if ($orderstatus['trade_state'] == 'PAYERROR') {
          return response()->json([
            'status_code' => 200,
            'data' => ['code' => 2, 'message' => '订单失败']
          ]);
        }
      } else {
        return response()->json([
          'status_code' => '-1',
          'error_message' => '服务器失败'
        ]);
      }
    }

    /*public function orderStatus(Request $request) {
        $orderNum = $request->input('ordernum', '');
        $order = order::where('ordernum', $orderNum)->first();
        if ($order['orderstatus'] == 0) {
            if($order['payment'] == 1){
                //微信支付
                $input = new WxPayOrderQuery();
                $input->SetOut_trade_no($orderNum);
                $config['appid'] = config('wxxcx.wechat_appid');
                $config['secret'] = config('wxxcx.wechat_appsecret');
                $config['mchid'] = config('pay.wxpay.mchid');
                $config['mch_secret_key'] = config('pay.wxpay.mch_secret_key');
                $config['sslcert_path'] = config('pay.wxpay.sslcert_path');
                $config['sslkey_path'] = config('pay.wxpay.sslkey_path');

                $orderstatus = \WxPayApi::orderQuery($input, $config);
                header("Content-Type: application/json");
                if ($orderstatus['return_code'] == 'SUCCESS') {
                    if ($orderstatus['trade_state'] == 'SUCCESS') {
                        $this->updateOrderStatus($order);
                        return response()->json([
                            'status_code' => 200,
                            'data' => [
                                'code' => 1,
                                'message' => '支付成功'
                            ]
                        ]);
                    } else if ($orderstatus['trade_state'] == 'NOTPAY') {
                        return response()->json([
                            'status_code' => 200,
                            'data' => [
                                'code' => 0,
                                'message' => '等待支付'
                            ]
                        ]);
                    } else if ($orderstatus['trade_state'] == 'USERPAYING') {
                        return response()->json([
                            'status_code' => 200,
                            'data' => [
                                'code' => 0,
                                'message' => '等待支付'
                            ]
                        ]);
                    } else if ($orderstatus['trade_state'] == 'PAYERROR') {
                        return response()->json([
                            'status_code' => 200,
                            'data' => [
                                'code' => 2,
                                'message' => '订单失败'
                            ]
                        ]);
                    }
                } else {
                    return response()->json([
                        'status_code' => '-1',
                        'error_message' => '服务器失败'
                    ]);
                }
            } elseif ($order['payment'] == 2) {
                //华移支付
                //$url = 'https://pay.cnmobi.cn/pay/order/orderStatus';
                // $url = config('pay.hypay.orderStatusUrl');
                // $params['orderNo'] = $order['ordernum'];
                // $params['merchantNo'] = config('pay.hypay,merchantNo');
                // $params['timestamp'] = time() * 1000;
                // $params['sign'] = $this->createSign($params);
                // list($status, $content) = Http::post($url, $params);
                // $content = json_decode($content, true);
                return response()->json([
                    'status_code' => 200,
                    'data' => [
                        'code' => 0,
                        'message' => '支付结果确认中……'
                    ]
                ]);
                if ($content['code'] == 1) {
                    if ($content['result']['code'] == 0) {
                        //等待支付
                        return response()->json([
                            'status_code' => 200,
                            'data' => [
                                'code' => 0,
                                'message' => '等待支付'
                            ]
                        ]);
                    } else if ($content['result']['code'] == 1) {
                        //成功
                        //update
                        $this->updateOrderStatus($order);   //更新订单状态
                        return response()->json([
                            'status_code' => 200,
                            'data' => [
                                'code' => 1,
                                'message' => '支付成功'
                            ]
                        ]);
                    } else if ($content['result']['code'] == 2) {
                        //失败
                        //'data'=>['code'=>2,'message'=>'订单失败']
                        return response()->json([
                            'status_code' => 200,
                            'data' => [
                                'code' => 2,
                                'message' => '订单失败'
                            ]
                        ]);
                    } else if ($content['result']['code'] == 3) {
                        //撤销订单
                    } else {
                        //订单退款
                    }
                } else {
//                    \Log::INFO($content);
                    return response()->json([
                        'status_code' => '-1',
                        'error_message' => '服务器失败'
                    ]);
                }
            }
        } else {
            return response()->json([
                'status_code' => 200,
                'data' => [
                    'code' => 1,
                    'message' => '支付成功'
                ]
            ]);
        }
    }*/

    public function orderStatushy(Request $request) {
        //查询数据库中的orderstatus状态
        //如果为1，则返回成功
        $orderNum = $request->input('ordernum', '');
        $order = order::where('ordernum', $orderNum)->first();
        if ($order['orderstatus'] == 0) {
//          $url = 'https://pay.cnmobi.cn/pay/order/orderStatus';
            $url = config('pay.hypay.orderStatusUrl');
            $params['orderNo'] = $order['ordernum'];
            $params['merchantNo'] = config('pay.hypay,merchantNo');
            $params['timestamp'] = time() * 1000;
            $params['sign'] = $this->createSign($params);
            list($status, $content) = Http::post($url, $params);
            $content = json_decode($content, true);
            if ($content['code'] == 1) {
                if ($content['result']['code'] == 0) {
                    //等待支付
                    return response()->json([
                        'status_code' => 200,
                        'data' => [
                            'code' => 0,
                            'message' => '等待支付'
                        ]
                    ]);
                } else if ($content['result']['code'] == 1) {
                    //成功
                    //update
                    $this->updateOrderStatus($order);   //更新订单状态
                    return response()->json([
                        'status_code' => 200,
                        'data' => [
                            'code' => 1,
                            'message' => '支付成功'
                        ]
                    ]);
                } else if ($content['result']['code'] == 2) {
                    //失败
                    //'data'=>['code'=>2,'message'=>'订单失败']
                    return response()->json([
                        'status_code' => 200,
                        'data' => [
                            'code' => 2,
                            'message' => '订单失败'
                        ]
                    ]);
                } else if ($content['result']['code'] == 3) {
                    //撤销订单
                } else {
                    //订单退款
                }
            } else {
                \Log::INFO($content);
                return response()->json([
                    'status_code' => '-1',
                    'error_message' => '服务器失败'
                ]);
            }
        } else {
            return response()->json([
                'status_code' => 200,
                'data' => [
                    'code' => 1,
                    'message' => '支付成功'
                ]
            ]);
        }
    }

    public function updateOrderStatus($order){
        $order_res = order::where('id',$order['id'])->first();
        if($order_res['orderstatus'] == 0){
            $date = date('Y-m-d H:i:s', time());
            $current_update = ['orderstatus' => 1, 'modifytime' => $date];
            if ($order['pack_type'] == 1) { // 更新续费订单的renew状态和end_time
                $lastcontent = contents::where('sid',$order['sid'])->orderBy('createtime','desc')->first();
                $sources = source::where('sid',$order['sid'])->first();
                if(strtotime($lastcontent['createtime'])+24*3600>=time()){
                    //这地方还要判断这条内容是否在上一个订单的时间范围内
                    $pre_order = order::where('sid', $order['sid'])->where('buyerid', $order['buyerid'])
                        ->whereRaw('orderstatus in (1,3)')->orderBy('createtime','desc')->first();
                    $pre_endtime = strtotime($pre_order['createtime'])+($sources['pack_day']+$sources['delayed_day'])*86400;
                    if($pre_endtime<strtotime($lastcontent['createtime'])){
                        $current_update['start_time'] = $lastcontent['createtime'];
                    }else{
                        $current_update['start_time'] = $lastcontent['createtime'];
                    }
                }else{
                    //用户在24小时内未更新
                    $current_update['start_time'] = date('Y-m-d H:i:s', time());
                }
                $historyOrders = order::where('sid', $order['sid'])->where('buyerid', $order['buyerid'])
                    ->whereRaw('orderstatus in (1,3)')->get()->ToArray();
                if (!empty($historyOrders)) {
                    foreach ($historyOrders as $horder) {
                        //订单结束时间是通过订单创建时间计算
                        $end_time = date('Y-m-d H:i:s', strtotime($horder['createtime'])+($sources['pack_day']+$sources['delayed_day'])*86400);
                        order::where('id', $horder['id'])->update([
                            'orderstatus' => $horder['orderstatus'] | 4,
                            'end_time' => $end_time,
                            'modifytime'=>date('Y-m-d H:i:s')
                        ]);
                    }
                }
            }
            DB::table('order')->where('id', $order['id'])->update($current_update);
            $buy_client_extra = client_extra::where('id', $order['buyerid'])->first();
            $status = decbin($buy_client_extra['role']);
            $oldStatus = sprintf('%08d', $status);
            $newStatus = substr_replace($oldStatus, 1, -2, 1);
            $newStatusChange = bindec((int)$newStatus);
            // 增加料已售出数量
            DB::table('source_extra')->where('id', $order['sourceid'])->increment('soldnumber');
            // 付款者增加已经支付的金额， 已经购买的料总数， 修改用户身份购买者
            DB::table('client_extra')->where('id', $order['buyerid'])->increment('buynum');  // 已经购买的料总数
            DB::table('client_extra')->where('id', $order['buyerid'])->increment('payed', $order['price']);  // 已经支付的金额
            DB::table('client_extra')->where('id', $order['buyerid'])->update(['role' => $newStatusChange]);  // 修改用户身份
            //不中退款，在判定红单后在增加收益
            if ($order['pack_type'] != 2) {
                DB::table('client_extra')->where('id', $order['selledid'])->increment('balance', $order['price']);   // 销售者余额增加
                DB::table('client_extra')->where('id', $order['selledid'])->increment('total', $order['price']);  // 收入增加
                //记录金额变更
                client_money_change::setChange($order['selledid'], $order['price'], 1, 1);
            }
            DB::table('client_extra')->where('id', $order['selledid'])->increment('soldnum');   // 销售的料总数加1

            //记录用户支付步骤操作
            purchase_record::setPurchaseRecord($order['sid'], $order['buyerid'], $order['selledid'], 3);
        }
    }

    /*public function batchWxPayNotify() {
      $xml = file_get_contents("php://input");
      $data = $this->xmlToArray($xml);
      if ($data['return_code'] == "SUCCESS") {
        $orders = order::where("batch_order_num", $data['out_trade_no'])->get()->ToArray();
        foreach($orders as $orderInfo) {
          if ($orderInfo['orderstatus'] == 0) {
            $current_update = ['orderstatus' => 1, 'modifytime' => date('Y-m-d H:i:s', time())];
            if ($orderInfo['pack_type'] == 1) { // 更新续费订单的renew状态和end_time
              $lastcontent = contents::where('sid',$orderInfo['sid'])->orderBy('createtime','desc')->first();
              $sources = source::where('sid',$orderInfo['sid'])->first();
              if(strtotime($lastcontent['createtime'])+24*3600>=time()){
                //这地方还要判断这条内容是否在上一个订单的时间范围内
                $pre_order = order::where('sid', $orderInfo['sid'])->where('buyerid', $orderInfo['buyerid'])
                        ->whereRaw('orderstatus in (1,3)')->orderBy('createtime','desc')->first();
                $pre_endtime = strtotime($pre_order['createtime'])+($sources['pack_day']+$sources['delayed_day'])*86400;
                if($pre_endtime<strtotime($lastcontent['createtime'])){
                  $current_update['start_time'] = $lastcontent['createtime'];
                }else{
                  $current_update['start_time'] = $lastcontent['createtime'];
                }
              }else{
                $current_update['start_time'] = date('Y-m-d H:i:s', time());      //用户在24小时内未更新
              }
              $historyOrders = order::where('sid', $order['sid'])->where('buyerid', $order['buyerid'])
                    ->whereRaw('orderstatus in (1,3)')->get()->ToArray();
              if (!empty($historyOrders)) {
                foreach ($historyOrders as $horder) {
                  //订单结束时间是通过订单创建时间计算
                  $end_time = date('Y-m-d H:i:s', strtotime($horder['createtime'])+($sources['pack_day']+$sources['delayed_day'])*86400);
                  order::where('id', $horder['id'])->update([
                    'orderstatus' => $horder['orderstatus'] | 4,
                    'end_time' => $end_time,
                    'modifytime'=>date('Y-m-d H:i:s')
                  ]);
                }
              }
            }

            DB::table('order')->where('id', $orderInfo['id'])->update($current_update);
            $buy_client_extra = client_extra::where('id', $order['buyerid'])->first();
            $status = decbin($buy_client_extra['role']);
            $oldStatus = sprintf('%08d', $status);
            $newStatus = substr_replace($oldStatus, 1, -2, 1);
            $newStatusChange = bindec((int)$newStatus);
            // 增加料已售出数量
            DB::table('source_extra')->where('id', $orderInfo['sourceid'])->increment('soldnumber');
            // 付款者增加已经支付的金额， 已经购买的料总数， 修改用户身份购买者
            DB::table('client_extra')->where('id', $orderInfo['buyerid'])->update([
              'buynum' => DB::raw('buynum + 1'),                      // 已经购买的料总数
              'payed' => DB::raw('payed + ' . $orderInfo['price']),   // 已经支付的金额
              'role' => $newStatusChange                             // 修改用户身份
            ]);
            //卖家统计信息更新
            $selled_orderInfo = ['soldnum' => DB::raw('soldnum + 1')];       // 销售的料总数加1
            if ($orderInfo['pack_type'] != 2) {
              $selled_orderInfo['balance'] = DB::raw('balance + ' . $orderInfo['price']);
              $selled_orderInfo['total'] = DB::raw('total + ' . $orderInfo['price']);
              //记录金额变更
              client_money_change::setChange($orderInfo['selledid'], $orderInfo['price'], 1, 1);
            }
            DB::table('client_extra')->where('id', $orderInfo['selledid'])->update($selled_orderInfo);
            //记录用户支付步骤操作
            purchase_record::setPurchaseRecord($orderInfo['sid'], $orderInfo['buyerid'], $orderInfo['selledid'], 3);
          }
        }
      } else {
        //\Log::INFO($data['return_msg']);
      }
      $return['return_code'] = 'SUCCESS';
      $return['return_msg'] = 'OK';
      $returnXml = '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
      return $returnXml;
    }*/


    // 订单生成
    public function generate()
    {
        $token = JWTAuth::getToken();
        $sid = (int)request("sid", "");
        if (empty($token) or empty($sid)) {
            $error['message'] = "token 或 资源id 不可为空";
            return response()->json($error);
        }

        $clients = $this->UserInfo($token);

        if( empty($clients['nickname']) ) {
            $encryptedData = request('encryptedData', '');
            $iv = request('iv', '');
            $code = request('code', '');
            $GetUserInfo['encryptedData'] = $encryptedData;
            $GetUserInfo['iv'] = $iv;
            $GetUserInfo['sessionKey'] = $clients['sessionkey'];
            $GetUserInfo['openid'] = $clients['openid'];
            $GetUserInfo['code'] = $code;
            $this->GetUserInfo($GetUserInfo);
        }

        // 查看用户是否已经购买过该资源
        $sell_source = orders::where("buy_uid", $clients['id'])->where("sid", $sid)->first();
        if ( $sell_source['pay_status'] == 1 ) {
            $error['status'] = '1001';
            $error['message'] = "已购买过该资源";
            return response()->json($error, 409);
        }

        // 获取资源详情
        $sources = Sources::where('id', $sid)->first();
        if ($sources['status'] != 0) {
            $error['status'] = '1002';
            $error['message'] = "用户已删除";
            $error['type'] = $sources['status'];
            return response()->json($error, 409);
        }

        if ($sources['admin_status'] != 0) {
            $error['status'] = '1003';
            $error['message'] = "涉及敏感信息";
            return response()->json($error, 409);
        }

        // 查看资源数量是否充足
        if( $sources['is_num'] != 0 ) {
            if ($sources['num'] <= 0) {
                $error['status'] = '1004';
                $error['message'] = "资源已售罄，请联系卖家";
                return response()->json($error, 409);
            }
        }

        // 免费资源直接进行返回信息
        if(floatval($sources['price']) == 0) {
            $error['message'] = "免费资源";
            $error['type'] = "0";
            return response()->json($error);
        }

        // 预支付订单添加
        if ( $sell_source['pay_status'] == 1 ) {

            $orders = $sell_source->ToArray();

        } else {
            $orders['orderNum'] = $this->order_number();    // 支付号

            $orders['buy_uid'] = $clients['id'];  // 购买者id

            $orders['sell_id'] = $sources['uid'];  // 出售者id

            $orders['sid'] = $sid;  // 资源ID

            $orders['sell_balance'] = floatval($clients['balance']);  // 用户余额

            $orders['source_price'] = floatval($sources['price']);    // 资源价格

            $orders['pay_status'] = 0;    // 支付状态

            orders::create($orders);

        }

        // 添加微信预支付订单
        $pre['body'] = $sources['title'];   // 商品描述
        $pre['order_number'] = $orders['orderNum']; // 订单号
        $pre['money'] = $orders['source_price'] * 100;    // 金额需进行转换， 微信已分为单位， 元转分
        $pre['openId'] = $clients['openid'];    // 用户openid
        $wx_pre = $this->servers($pre);

        $data['timestamp'] = (string)time();
        $data['appId'] = $wx_pre['appid'];
        $data['nonceStr'] = $wx_pre['nonce_str'];
        $data['package'] = "prepay_id=".$wx_pre['prepay_id'];
        $data['signType'] = "MD5";
        $keywords = 'appId='.$wx_pre['appid'].'&nonceStr='.$wx_pre['nonce_str'].'&package=prepay_id='.$wx_pre['prepay_id'].'&signType=MD5&timeStamp='.$data['timestamp'].'&key=SDJy3O9G8pQevPY1Jb5Ayy2LSIzK4yB3';
        $data['paySign'] = md5($keywords);
        return response()->json($data);

    }

    // 微信返回更新信息
    public function postOrderUpdate()
    {

        $xml = file_get_contents("php://input");
        $data = $this->xmlToArray($xml);

        // 业务结果SUCCESS/FAIL 修改订单状态
        if ($data['result_code'] == "SUCCESS") {
            // 分 转 元
            $money = $data['total_fee'] / 100;

            // 修改订单状态 以及 增加资源表内, 销量， 销量金额
            $all = order::where("ordernum", $data['out_trade_no'])->first();
            if ( $all['orderstatus'] == 0) {
                // 增加判断 未修改状态则进行
                $this->updateOrderStatus($all);
            }
        }else{
            error_log(print_r($data,true),3,'/tmp/order_error.log');
        }
        $returnXml = '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
        return $returnXml;
    }


    /**
     *  用户收益明细接口
     */
    public function getClinetPaid(Request $request, $uid)
    {
        $sorts = ['0' => 'desc', '1' => 'asc', '2' => 'desc'];
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        $page = $request->input('page', '0');
        $numberpage = $request->input('numberpage', '50');
        $lastid = $request->input('lastid', '-1');
        $sort = $request->input('sort', '0');
        if (empty($token) || $clients['id'] != $uid) {
            $return['status_code'] = 10001;
            $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
            return response()->json($return);
        }
        $offset = $page * $numberpage;

        $order = order::select('order.id','buyerid', 'ordernum',  'order.price', 'sid', 'order.createtime');
        //$order->whereRaw("substring(bin(orderstatus), -1, 1) = 1");
        $order->whereRaw("orderstatus & 1");
        $order->where('selledid', $uid);
        $order->orderby('order.createtime', $sorts[$sort]);
        if ($lastid == '-1') {
            $order->offset($offset);
        }
        if ($lastid != '-1' && !empty($lastid)) {
            if ($sort != 1) { // desc
                $order->where('order.createtime', '<', $lastid);
            } else {
                $order->where('order.createtime', '>', $lastid);
            }
        }
        $order->limit($numberpage);
        $data = $order->get();

        foreach ( $data as $key => $value ) {


            $userInfo = client::select('nickname','avatarurl')->where('id',$value['buyerid'])->first();
            $sourceInfo = source::select('sid','title')->where('sid',$value['sid'])->first();
            $data[$key]['nickname'] = $userInfo['nickname'];
            $data[$key]['avatarurl'] = $userInfo['avatarurl'];
            $data[$key]['title'] = $sourceInfo['title'];

            $data[$key]['price'] = floatval($value['price']);
            $data[$key] = source_sensitives::apply($data[$key]);
        }
        $count = 0;
        if ($lastid == '-1') {
            $order = order::select();
            //$order->whereRaw("substring(bin(orderstatus), -1, 1) = 1");
            $order->whereRaw("orderstatus & 1");
            $order->where('selledid', $uid);
            $count = $order->count();
        }
        $pagenum = ceil($count/$numberpage);

        $return['status_code'] = '200';
        $return['count'] = $count;
        $return['pagenum'] = $pagenum;
        $return['data'] = $data;

        return response()->json($return);
    }

    public function devClientIncome(Request $request,$uid)
    {
        $sorts = ['0' => 'desc', '1' => 'asc', '2' => 'desc'];
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        $page = $request->input('page', '0');
        $numberpage = $request->input('numberpage', '50');
        $lastid = $request->input('lastid', '-1');
        $sort = $request->input('sort', '0');
        if (empty($token) || $clients['id'] != $uid) {
            $return['status_code'] = 10001;
            $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
            return response()->json($return);
        }
        $offset = $page * $numberpage;

        $order = order::select('order.id', 'ordernum', 'nickname', 'avatarurl', 'title', 'order.price', 'source.sid', 'order.createtime');
        $order->LeftJoin('client', 'client.id', 'order.buyerid');
        $order->LeftJoin('source', 'source.sid', 'order.sid');
        $order->whereRaw("orderstatus & 1");
        $order->where('selledid', $uid);
        $order->orderby('order.createtime', $sorts[$sort]);
        if ($lastid == '-1') {
            $order->offset($offset);
        }
        if ($lastid != '-1' && !empty($lastid)) {
            if ($sort != 1) { // desc
                $order->where('order.createtime', '<', $lastid);
            } else {
                $order->where('order.createtime', '>', $lastid);
            }
        }
        $order->limit($numberpage);
        $data = $order->get();

        foreach ( $data as $key => $value ) {
            $data[$key]['price'] = floatval($value['price']);
        }
        $count = 0;
        if ($lastid == '-1') {
            $order = order::select();
            $order->whereRaw("orderstatus & 1");
            $order->where('selledid', $uid);
            $count = $order->count();
        }
        $pagenum = ceil($count/$numberpage);

        $return['status_code'] = '200';
        $res['count'] = $count;
        $res['pagenum'] = $pagenum;
        $res['dataList'] = $data;
        $return['data'] = $res;

        return response()->json($return);
    }

    /**
     * 删除我买的料
     * @param Request $request
     * @param $sid
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteMySource(Request $request, $sid){
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if ( array_key_exists('status_code', $clients) ) {
            if($clients['status_code'] == '401') {
                $return['status_code'] = "10001";
                $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
                return response()->json($return);
            }
        }
        // 判断料是否存在
        $orderSource = order::select('id', 'ordernum', 'buyerid', 'sid', 'orderstatus')->where('sid', $sid)->where('buyerid', $clients['id'])->where('orderstatus',1)->first();
        if( !$orderSource ) {
            $return['status_code'] = '10004';
            $return['error_message'] = '您要删除的料不存在';
            return response()->json($return);
        }

        $newStatusChange = $orderSource['orderstatus'] | 2;

        order::where('id', $orderSource['id'])->where('sid', $sid)->where('buyerid', $clients['id'])->update(['orderstatus' => $newStatusChange, 'modifytime'=>date('Y-m-d H:i:s')]);

        $return['status_code'] = '200';
        return response()->json($return);
    }


    // 微信预支付订单
    public function servers($data)
    {
        // 订单号

        $input = new WxPayUnifiedOrder();
        $input->SetBody($data['body']);    // 商品简单描述
        $input->SetOut_trade_no($data['order_number']); //订单号
        $input->SetTotal_fee($data['money']);  // 金额
        $input->SetNotify_url(config('constants.backend_domain') . "/pub/order/update");  // 推送信息
        $input->SetTrade_type("JSAPI");
        $input->SetOpenid($data['openId']);  // 用户openId
        $config['appid'] = config('wxxcx.appid');
        $config['secret'] = config('wxxcx.secret');
        $config['mchid'] = config('pay.wxpay.mchid');
        $config['mch_secret_key'] = config('pay.wxpay.mch_secret_key');
        $config['sslcert_path'] = config('pay.wxpay.sslcert_path');
        $config['sslkey_path'] = config('pay.wxpay.sslkey_path');
        $order = \WxPayApi::unifiedOrder($input,$config);
        header("Content-Type: application/json");

        return $order;
    }

    /**
     * 发送给用户消息
     *
     */
    public function seed_news()
    {
        $access_token = $this->access_token();
        $url = 'https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token='.$access_token;
        $dd = array();
        //$dd['access_token']=$access_token;
        $dd['touser']="oZdwA0fNPAFqXAyx4xHdoFjroTLQ";
        $dd['template_id']="PLbu5aEr0hO3nbtdbZh4vtl-Qs03Fcj1TYV_kU0jM8k";
//        $dd['page']=$page;  //点击模板卡片后的跳转页面，仅限本小程序内的页面。支持带参数,该字段不填则模板无跳转。
        $dd['form_id']="wx201710301137425e3f1898bd0366262314";

        $value = [
            'keyword1' => [
                'value' => '11.11',
            ],
            'keyword2' => [
                'value' => '2017年10月30日 11:11',
            ],
            'keyword3' => [
                'value' => '资源',
            ],
            'keyword4' => [
                'value' => '王大锤',
            ],
        ];

        $dd['data']=$value;                        //模板内容，不填则下发空模板

        $dd['color']='';                        //模板内容字体的颜色，不填默认黑色
        //$dd['color']='#ccc';
        $dd['emphasis_keyword']='';    //模板需要放大的关键词，不填则默认无放大
        //$dd['emphasis_keyword']='keyword1.DATA';

        //$send = json_encode($dd);   //二维数组转换成json对象

        /* curl_post()进行POST方式调用api： api.weixin.qq.com*/
        $result = $this->https_curl_json($url,$dd,'json');
        if($result){
            echo json_encode(array('state'=>5,'msg'=>$result));
        }else{
            echo json_encode(array('state'=>5,'msg'=>$result));
        }
    }

    /**
     * curl 发送请求
     * param string $url 地址
     * param xml/array $data 参数
     * param string xml/json $type 所传类型
     * param string ca 是否需要ca证书 0 / 1
     */
    public function https_curl_json($url,$data,$type,$ca = ''){

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);

        if($type=='json'){//json $_POST=json_decode(file_get_contents('php://input'), TRUE);
            $headers = array("Content-type: application/json;charset=UTF-8","Accept: application/json","Cache-Control: no-cache", "Pragma: no-cache");
            $data=json_encode($data);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers );
        } else if ($type == 'xml') {
            curl_setopt($curl, CURLOPT_HEADER, 0);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        }

        if (!empty($data)){
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS,$data);
        }

        // 是否需要微信ca证书
        if($ca == 1) {
            curl_setopt($curl, CURLOPT_CAPATH, "weixin/rootca.pem");
            curl_setopt($curl, CURLOPT_SSLCERT, "weixin/apiclient_cert.pem");
            curl_setopt($curl, CURLOPT_SSLKEY, "weixin/apiclient_key.pem");
        }

        $output = curl_exec($curl);
        if (curl_errno($curl)) {
            echo 'Errno'.curl_error($curl);//捕抓异常
        }
        curl_close($curl);
        return $output;
    }


    /**
     * 用户是否已经购买过该资源
     */
    public function already_buy()
    {
        $return = [
            'buy_state' => '0',
            'message' => '未购买',
        ];
        $token = JWTAuth::getToken();
        $sid = (int)request("sid", "");
        if (empty($token) or empty($sid)) {
            $error['message'] = "token 或 资源id 不可为空";
            return response()->json($error, 400);
        }

        $clients = $this->UserInfo($token);

        $sell_source = orders::where("buy_uid", $clients['id'])->where("sid", $sid)->where("pay_status", "1")->first();


        // 判断资源是否免费
        $sources = Sources::where('id', $sid)->first();
        $isFree = False;
        if($sources['price'] == 0) {
            $isFree = True;
        }
        $return['isFree'] = $isFree;

        // 资源数量
        $isNum = False;
        if($sources['is_num'] == 0){
            $isNum = True;
        }

        $return['isNum'] = $isNum;
        $return['num'] = $sources['num'];

        if ( $sell_source ) {
            $return['buy_state'] = "1";
            $return['message'] = "已购买";
        }

        return response()->json($return);

    }

    /**
     * 订单号生成
     */
    public function order_number($is_prefix)
    {
        //$yCode = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J');
        $yCode  = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
        if($is_prefix){
            $orderSn = "gl".$yCode[intval(date('Y')) - 2017] . strtoupper(dechex(date('m'))) . date('d') . substr(time(), -5) . substr(microtime(), 2, 5) . sprintf('%02d', rand(0, 99));
        } else {
            $orderSn = $yCode[intval(date('Y')) - 2017] . strtoupper(dechex(date('m'))) . date('d') . substr(time(), -5) . substr(microtime(), 2, 5) . sprintf('%02d', rand(0, 99));
        }

        return $orderSn;
    }

    /**
     * 微信返回更新订单状态
     */
    public function renew()
    {
        $xml = file_get_contents("php://input");
        $data = $this->xmlToArray($xml);

        // 业务结果SUCCESS/FAIL 修改订单状态
        if ($data['result_code'] == "SUCCESS") {
            // 分 转 元
            $money = $data['total_fee'] / 100;

            // 修改订单状态 以及 增加资源表内, 销量， 销量金额
            $all = orders::where("orderNum", $data['out_trade_no'])->first();
            if ( $all['pay_status'] == 0) {

                orders::where("orderNum", $data['out_trade_no'])->update(['pay_status' => 1]);
                Sources::where("id", $all['sid'])->increment('sold_num');
                Sources::where("id", $all['sid'])->increment('sold_money', $money);
                Sources::where("id", $all['sid'])->decrement('num');

                // 为用户添加角色标签买家
                $profile = profile::where("uid", $all['buy_uid'])->first();
                if( !$profile ) {
                    $files['uid'] = $all['buy_uid'];
                    $files['is_buy'] = 1;
                    profile::create($files);
                } else {
                    if($profile['is_buy'] == 0) {
                        profile::where("uid", $all['buy_uid'])->update(['is_buy' => '1']);
                    }
                }

                // 用户余额添加(出售用户)
                Clients::where('id', $all['sell_id'])->increment("balance", $money);

            }


        }
    }

    /**
     * 用户购买的料列表
     * @param token
     * @return array
     */
    public function buy_source()
    {

        $token = JWTAuth::getToken();

        $clients = $this->UserInfo($token);
        if( !empty($clients['status_code'])) {
            if($clients['status_code'] == '401') {
                $error['message'] = '用户登陆时间过期';
                return response()->json($error, 401);
            }
        }

        $data = orders::select('sources.id', 'title', 'orders.created_at', 'sources.admin_status as sources_status', 'sources.status')
            ->leftJoin('sources', 'sources.id', 'orders.sid')
            ->where("orders.buy_uid", $clients['id'])
            ->where("orders.status", "0")
            ->where("orders.pay_status", "1")
            ->orderBy("id", "desc")
            ->get();

        return response()->json($data);

    }

    /**
     * 我卖的料  详情页
     * @param sid
     * @return array
     */
    public function details()
    {
        $token = JWTAuth::getToken();

        $clients = $this->UserInfo($token);
        if( !empty($clients['status_code'])) {
            if($clients['status_code'] == '401') {
                $error['message'] = '用户登陆时间过期';
                return response()->json($error, 401);
            }
        }

        $return = [];
        $sid = (int)request("sid", "");

        if( empty ($sid) ) {
            $return['message'] = "参数不可为空";
            return response()->json($return);
        }

        $all = Sources::select('sold_num', 'sold_money', 'admin_status', 'status', 'num', 'is_num', 'price', 'title')->where("id", $sid)->first();

        $return['sold_num'] = $all['sold_num'];
        $return['sold_money'] = floatval($all['sold_money']);
        $return['admin_status'] = $all['admin_status'];
        $return['status'] = $all['status'];
        $return['num'] = $all['num'];
        $return['is_num'] = $all['is_num'];
        $return['price'] = floatval($all['price']);
        $return['title'] = $all['title'];

        $data = orders::select('clients.nickname', 'clients.avatarurl', 'orders.created_at', 'clients.sex', 'clients.city', 'clients.country')
            ->leftJoin('clients', 'clients.id', 'orders.buy_uid')
            ->where("orders.sid", $sid)
            ->where("pay_status", 1)
            ->get();
        $return['data'] = $data->ToArray();

        return response()->json($return);
    }


    /**
     * 购买的料，用户删除操作（status 0 up 1）
     * @param token
     * @return boolean
     */
    public function buy_del()
    {
        $token = JWTAuth::getToken();
        $sid = (int)request("sid", "");
        if (empty($token) or empty($sid)) {
            $error['message'] = "token 或 资源id 不可为空";
            return response()->json($error, 400);
        }

        $clients = $this->UserInfo($token);

        if( !empty($clients['status_code'])) {
            if($clients['status_code'] == '401') {
                $error['message'] = '用户登陆时间过期';
                return response()->json($error, 401);
            }
        }

        // 执行修改操作
        $re = orders::where("buy_uid", $clients['id'])->where("sid", $sid)->update(['status' => 1]);

        if($re) {
            $val = 1;
        } else {
            $val = 0;
        }

        return response()->json($val);

    }


    /**
     * 支付成功后的资源详情
     * @param string $token 用户标识
     * @param int $sid 资源标识
     * @return array
     */
    public function payment()
    {
        $return = [];

        $token = JWTAuth::getToken();
        $sid = (int)request("sid", "");
        if (empty($token) or empty($sid)) {
            $error['message'] = "token 或 资源id 不可为空";
            return response()->json($error, 400);
        }

        $clients = $this->UserInfo($token);
        if( !empty($clients['status_code'])) {
            if($clients['status_code'] == '401') {
                $error['message'] = '用户token验证失败， 请刷新重试';
                return response()->json($error, 401);
            }
        }

        // 资源列表
        $sources = Sources::where("id", $sid)->first();

        $pd = 1;    // 是否需要判断是否已经购买
        // 判断是否为制作人
        if($sources['uid'] == $clients['id']) {
            $pd = 0;
        }
        // 判断是否为免费不限数量直接进入
        if($sources['price'] == 0 and $sources['is_num'] == 0) {
            $pd = 0;
        }

        // 是否已经删除
        if($sources['status'] == 1) {
            $return['status_code'] = "1001";
            $return['message'] = "用户已经删除";
            return response()->json($return, 409); // 409 与请求的资源状态有冲突
        }
        // 是否涉及敏感信息
        if($sources['admin_status'] == 1) {
            $return['status_code'] = "1002";
            $return['message'] = "涉及敏感信息";
            return response()->json($return, 409); // 409 与请求的资源状态有冲突
        }

        if($pd != 0){
            // 判断用户是否已经购买
            $is_buy = orders::where("buy_uid", $clients['id'])->where("sid", $sid)->where("pay_status", "1")->first();
            if( !$is_buy ) {
                $return['status_code'] = "1003";
                $return['message'] = "用户未进行购买";
                return response()->json($return, 409); // 409 与请求的资源状态有冲突
            }
        }

        $rid = trim($sources['resources'], ',');
        $rids = explode(",", $rid);

        $data = resource::whereIn("rid", $rids)->orderBy("created_at")->get();
        // type 1，文字 2，图片 3，语音 4，视频 5，文件
        $datas = [];
        foreach($data as $key=> $value) {
            if ($value['type'] == 2) {
                $datas['picture'][] = 'https://zy.qiudashi.com/'.$value['position'];
            } else if ($value['type'] == 1) {
                $datas['text'] = $value['position'];
            } else if ($value['type'] == 3) {
                $datas['voice'] = 'https://zy.qiudashi.com/'.$value['position'];
            } else if ($value['type'] == 4) {
                $datas['video'] = 'https://zy.qiudashi.com/'.$value['position'];
            } else if ($value['type'] == 5) {
                $datas['file'] = 'https://zy.qiudashi.com/'.$value['position'];
            } else {
                $datas[$value['type']] = 'https://zy.qiudashi.com/'.$value['position'];
            }
        }
        $return['title'] = $sources['title'];
        $return['data'] = $datas;

        return response()->json($return);

    }



    /**
     * xml 转换为 array
     * @param xml
     * @return array
     */
    public function xmlToArray($xml)
    {
        // 禁止引用外部xml实体
        libxml_disable_entity_loader(true);

        $xmlstring = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);

        $val = json_decode(json_encode($xmlstring), true);

        return $val;
    }


    /**
     * 微信api 企业给用户打款
     */
    public function play()
    {
        $url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers";
        $pre = [];
        $pre['mch_appid'] = 'wx1ad97741a12767f9';
        $pre['mchid'] = '1487651632';
        $pre['nonce_str'] = md5(time() . mt_rand(0,1000));
        $pre['partner_trade_no'] = $this->order_number();
        $pre['openid'] = 'oZdwA0fNPAFqXAyx4xHdoFjroTLQ';
        $pre['check_name'] = 'NO_CHECK';
        $pre['amount'] = '10';
        $pre['desc'] = '您已成功提现'.$pre['amount'];
        $pre['spbill_create_ip'] = "10.10.139.114";
        $secrect_key = "SDJy3O9G8pQevPY1Jb5Ayy2LSIzK4yB3";

        ksort($pre);
        $sign = '';
        foreach ($pre as $key => $value) {
            $sign .= "{$key}={$value}&";
        }
        $sign .= 'key='.$secrect_key;
        $pre['sign'] = strtoupper(md5($sign));
        $xml = $this->arraytoxml($pre);
        $data = $this->https_curl_json($url, $xml, 'xml', '1');

    }

    /**
     * 数组转换为xml
     * param array $data
     * return xml
     */
    public function arraytoxml($data)
    {
        $str='<xml>';
        foreach($data as $k=>$v) {
            $str.='<'.$k.'>'.$v.'</'.$k.'>';
        }
        $str.='</xml>';
        return $str;
    }

    public function ordercheck(Request $request)
    {

//        $offset = $request->input('start',0);
        //var_dump($offset);
        $file = fopen(__DIR__ . '/check1111.log', 'a+');

//        $orderList = order::where('orderstatus', 0)->where('createtime','>=','2018-06-25 00:00:00')->where('createtime','<=','2018-06-25 21:00:00')->offset($offset)->limit(10)->get();

//      $orderList = $orderList->ToArray();
//      if(empty($orderList)) {
//            fwrite($file, "Over \n");
//          exit();
//      }

        $orderList = array(1625300378933590,1625384394245070,1625250966558220,1625250939697180,1625320680453540,1625312959975020,1625343140822660,1625423896570640,1625675465360090,1625600000095930,1625334990355230,1625324307631910,1625603595144860,1625266858531980,1625598897008380,1625071223819730,1625597061947570,1625294066156270,1625225945151850,1625220409420430,1625286998296900,1625187346692270,1625225740158170,1625617092955050,1625193895268020,1625698384704440,1625591877388800,1625628257815890,1625631954274220,1625314292333280,1625196336750940,1625277088817250,1625295620099670,1625351584050630,1625353326274530,1625149324758470,1625597577182660,1625192453434500,1625607834111840,1625213855603730,1625595190081430,1625351752069020,1625202363899270,1625315173106170,1625616004919240,1625312784759400,1625245494028670,1625160211179260,1625109546965620,1625296252010220,1625423448563060,1625624038239330,1625309822814000,1625146105574510,1625336442815930,1625684303684120,1625205089999190,1625605367936600,1625410294274530,1625270090538490,1625156271983020,1625316559988690,1625306034878120,1625312038162580,1625165329417240,1625402477569190,1625210512242590,1625619865548660,1625179843632350,1625118192309970,1625353653536290,1625351816383940,1625304513890850,1625293979216310,1625596562630780,1625048129434660,1625141181972790,1625322502213220,1625295890951720,1625326747900640,1625323912603250,1625187270670660,1625308112686490,1625422057597910,1625621209832510,1625175327895040,1625161392014740,1625320454218250,1625386630464530,1625774390619850,1625191676648710,1625630298322520,1625308591595500,1625195154668490,1625591507472430,1625138301788250,1625023225333820,1625598083113160,1625188865286620,1625402098817960,1625237175549230,1625585999192830,1625125753222240,1625604215535490,1625413835008980,1625614325362720,1625249958630080,1625187851964500,1625602901741990,1625008225606760,1625264949740390,1625609313641370,1625296057771630,1625419131678820,1625260171874560,1625679205636300,1625177542340670,1625307804565050,1625315551097050,1625210928221630,1625328578102890,1625351927826290,1625723388563140,1625192891486210,1625108669038010,1625670737805080,1625149137228270,1625179403541970,1625370192481770,1625202145501620,1625265538698110,1625348854613780);
//      var_dump($orderList);die;
        foreach ($orderList as $orderInfo) {

            $weixinApi = "https://api.mch.weixin.qq.com/pay/orderquery";
            // $time=time();
            $time = bin2hex(random_bytes(16));
            $config['appid'] = config("wxxcx.wechat_appid");
            //$config['secret'] = 'e2a7b38320e6ab2cea3a467a47ac5de4';
            $config['mch_id'] = '1487651632';
            $config['out_trade_no'] = $orderInfo;

            //$config['nonce_str'] = bin2hex(random_bytes(16));
            $keywords = 'appid='.$config['appid'].'&mch_id='.$config['mch_id'].'&nonce_str='.$time.'&out_trade_no='.$config['out_trade_no'].'&key=SDJy3O9G8pQevPY1Jb5Ayy2LSIzK4yB3';
            $sign = md5($keywords);
            $str = "<xml>
                <appid>".config("wxxcx.wechat_appid")."</appid>
                <mch_id>".config("pay.wxpay.mchid")."</mch_id>
                <out_trade_no>".$orderInfo."</out_trade_no>
                <nonce_str>$time</nonce_str>
                <sign>$sign</sign>
            </xml>";

            fwrite($file, $orderInfo."\n");

            $result = $this->https_curl_json($weixinApi,$str,'xml');
            $result = json_decode(json_encode(simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
            fwrite($file, json_encode($result)."\n");
//            if($result['return_code']=="SUCCESS" &&$result['trade_state']=="SUCCESS"){
//                $order = order::where("ordernum", $config['out_trade_no'])->first();
//                if($order['orderstatus'] == 0){
//                    // $this->updateOrderStatus($order);
//                    fwrite($file, "Update : " . $orderInfo['ordernum']."\n");
//                }
//            }

        }

        fclose($file);
//        $offset =$offset+10;
        sleep(1);
//        header("Location:https://api.qiudashi.com/pub/order/ordercheck?start=$offset");
    }


    /**
     * 设置扫码购买操作过程记录
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function purchaseRecord(Request $request){
        $return = [];

        $token = JWTAuth::getToken();
        //料ID
        $sourceid = $request->input('sourceid', 0);
        $buyerid = $request->input('buyerid', 0);
        $selledid = $request->input('selledid', 0);
        $step = $request->input('step', 1);

        if (empty($token) || empty($sourceid) ||  empty($buyerid) ||  empty($selledid) ||  empty($step)) {
            $error['message'] = "参数错误";
            return response()->json($error, 400);
        }

        $prefix = substr($sourceid, 0, 2);
        if ($prefix == 's.') {
            $sourceid = substr($sourceid, 2);
        } else if ($prefix == 'r.') {
            $rid = substr($sourceid, 2);
            $sourceid = '';
            $record = source_update_record::where('rid', $rid)->first();
            if ($record) {
                $sourceid = $record['sid'];
            }
        } else { // 旧的sourceid
            $oldsourceid = $sourceid;
            $sid = '';
            $source = source::where('id', $oldsourceid)->first();
            if ($source) {
                $sourceid = $source['sid'];
            }
        }

        if(!in_array($step, [1,2,3])){
            $error['message'] = "参数错误";
            return response()->json($error, 400);
        }

        $clients = $this->UserInfo($token);
        if (!empty($clients['status_code'])) {
            if ($clients['status_code'] == '401') {
                $error['message'] = '用户token验证失败， 请刷新重试';
                return response()->json($error, 401);
            }
        }

        purchase_record_front::setPurchaseRecord($sourceid, $buyerid, $selledid, $step);

        $return['status_code'] = '200';
        $return['data'] = [];
        return response()->json($return);
    }

    /**
     * 购买用户列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function buyerList(Request $request, $uid) {
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (empty($token) || !isset($clients['id']) || $clients['id'] != $uid) {
            $return['status_code'] = 10001;
            $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
            return response()->json($return);
        }
        $page = $request->input('page', '0');
        $numberpage = $request->input('numberpage', '20');
        $offset = ($page-1) * $numberpage;
        $nickname = $request->input('nickname', '');
        $orderBy = json_decode($request->input('orderby', ''), true);

        $buyerListQuery = buyer::select('client.id', 'client.avatarurl', 'client.nickname', 'buyer.id', 'buyer.selledid', 'buyer.buyerid', 'buyer.payed', 'buyer.buy_num', 'buyer.last_buy_time', 'buyer.status', 'buyer.create_time')->LeftJoin('client', 'client.id', 'buyer.buyerid')->where('buyer.selledid', $uid);

        if($nickname){
            $buyerListQuery->where('client.nickname','like','%'.$nickname.'%');
        }

        if($orderBy){
            foreach($orderBy as $key => $val){
                $buyerListQuery->orderby($key, $val);
            }
        } else {
            $buyerListQuery->orderby('last_buy_time', 'desc');
        }

        $buyerList = $buyerListQuery->offset($offset)->limit($numberpage)->get()->toArray();

        $buyerCountQuery = buyer::select('buyer.id')->LeftJoin('client', 'client.id', 'buyer.buyerid')->where('buyer.selledid', $uid);

        if($nickname){
            $buyerCountQuery->where('client.nickname','like','%'.$nickname.'%');
        }
        $buyerCount = $buyerCountQuery->count();

        $return['status_code'] = '200';
        $return['pagenum'] = $buyerCount;
        $return['data'] = $buyerList;

        return response()->json($return);
    }

    /**
     * 设置是否黑名单
     * @param Request $request
     * @param         $uid
     * @return \Illuminate\Http\JsonResponse
     */
    public function setBuyerStatus(Request $request, $uid){
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (empty($token) || !isset($clients['id']) || $clients['id'] != $uid) {
            $return['status_code'] = 10001;
            $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
            return response()->json($return);
        }
        $buyerid = $request->input('buyerid', '0');
        $status = $request->input('status', '0');

        $res = buyer::where('buyerid', $buyerid)->where('selledid', $uid)->update(['status' => $status]);

        $return['status_code'] = '200';
        $return['data'] = [];

        return response()->json($return);
    }
}
