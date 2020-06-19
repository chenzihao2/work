<?php
/**
 * 支付宝订单创建与回调管理
 * User: WangHui
 * Date: 2018/9/20
 * Time: 16:30
 */

namespace App\Http\Controllers\Api\V1;

use App\models\client_money_change;
use App\models\client_extra;
use App\models\contents;
use App\models\follow;
use App\models\order;
use App\models\purchase_record;
use App\models\source;
use App\models\source_extra;
use Dingo\Api\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

include_once(app_path() . "/alipay/wappay/service/AlipayTradeService.php");
include_once(app_path() . "/alipay/wappay/buildermodel/AlipayTradeWapPayContentBuilder.php");

class AliPayController extends BaseController
{
    /**
     * 支付
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createOrder(Request $request) {
        $token = JWTAuth::getToken();
        $sid = $request->input('sid', "");
        $uid = $request->input('uid', "");
        $follow = $request->input('follow', true);
        $clients = $this->UserInfo($token);

        //微信内
        if ($this->is_weixin()) {
            return view('wechat/redirect')->with('token', $token)->with('uid', $uid);
        }

        if (empty($token) || $clients['id'] != $uid) {
            $return['status_code'] = 10001;
            $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
            return response()->json($return);
        }

        $sources = source::select('status', 'thresh', 'sid', 'pack_type', 'play_time', 'play_end', 'free_watch', 'order_status', 'uid', 'price', 'pack_day', 'delayed_day')->where('source.sid', $sid)->first();

        $sources['soldnumber'] = source_extra::where('sid', $sources['sid'])->value('soldnumber');
        if (!$sources || $sources['status'] != 0) {
            $return['status_code'] = '10003';
            $return['error_message'] = '资源异常， 已被删除或下架';
            $return['status'] = $sources['status'];
            return response()->json($return);
        }

        if ($sources['thresh'] != 0) {
            if ($sources['soldnumber'] + 1 > $sources['thresh']) {
                $return['status_code'] = '10004';
                $return['error_message'] = '资源已售罄';
                return response()->json($return);
            }
        }


        //比赛一小时后不可买，检查是否可买（1可买，0不可买）
        if ($sources['pack_type'] == 3) {
            if ($sources['play_end'] == 1) {
                $now = time();
                $getInfoTime = $sources['play_time'] + 60 * 60;
                if ($now >= $getInfoTime) {
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
        $buy_sources = order::where('buyerid', $uid)->where('sid', $sid)->where('pack_type', 0)->whereRaw('orderstatus & 1')->first();
        if (!empty($buy_sources)) {
            if ($buy_sources['orderstatus'] < 5) {
                // 排除续费订单
                $return['status_code'] = '10005';
                $return['error_message'] = '用户已购买过该资源';
                return response()->json($return);
            }
        }
        $today = date('Y-m-d H:i:s');
        $order['sid'] = $sid;
        $order['pack_type'] = $sources['pack_type'];
        $order['buyerid'] = $uid;
        $order['selledid'] = $sources['uid'];
        $order['sourceid'] = $sources['sid'];
        $order['price'] = $sources['price'];
        if ($sources['pack_type'] == 1) {
            $order['score'] = '1' . time();
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
            if (empty($follow_info)) {
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
        if ($sources['price'] == 0) {
            if ($sources['pack_type'] == 1) { // 更新续费订单的renew状态和end_time
                $lastcontent = contents::where('sid', $order['sid'])->where('is_check', 1)->orderBy('createtime', 'desc')->first();
                if (strtotime($lastcontent['createtime']) + 24 * 3600 >= time()) {
                    $order['start_time'] = $lastcontent['createtime'];
                } else {
                    //用户在24小时内未更新
                    $order['start_time'] = date('Y-m-d H:i:s', time());
                }
                $historyOrders = order::where('sid', $sources['sid'])->where('buyerid', $uid)->whereRaw('orderstatus in (1,3)')->get()->ToArray();
                if (!empty($historyOrders)) {
                    foreach ($historyOrders as $horder) {
                        order::where('id', $horder['id'])->update([
                            'orderstatus' => $horder['orderstatus'] | 4,
                            'end_time' => date('Y-m-d H:i:s', strtotime($horder['createtime']) + ($sources['pack_day'] + $sources['delayed_day']) * 86400),
                            'modifytime' => date('Y-m-d H:i:s')
                        ]);
                    }
                }
            }

            $free = 1;
            $order['ordernum'] = $this->order_number(true);
            $order['orderstatus'] = 1;
            $order['payment'] = 0;
            // 添加订单， 增加售出数量 
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
        $body = '咨询解答收费';

        if (empty($order['selledid']) || empty($order['sourceid'])) {
                $log_content = 'czhfixorder|controller=AliPayController:188|uid=' . $uid . '|sid=' . $sid .'|clients=' . json_encode($clients) .'|request='. json_encode($request->all()) . '|';
                \Log::info($log_content);
        }

        $ordernum = $this->order_number(true);
        $order['payment'] = 3;
        $order['ordernum'] = $ordernum;
        $order['id'] = order::insertGetId($order);


        $payRequestBuilder = new \AlipayTradeWapPayContentBuilder();
        $payRequestBuilder->setBody($body);
        $payRequestBuilder->setSubject($body);
        $payRequestBuilder->setOutTradeNo($ordernum);
        $payRequestBuilder->setTotalAmount($sources['price']);
        $config = [
            //应用ID,您的APPID。
            'app_id' => config("pay.alipay.appid"),
            //商户私钥，您的原始格式RSA私钥
            'merchant_private_key' => config("pay.alipay.private_key"),
            //异步通知地址
            'notify_url' => config("pay.alipay.notify_url"),
            //同步跳转
            'return_url' => config("pay.alipay.return_url"),
            //编码格式
            'charset' => "UTF-8",
            //签名方式
            'sign_type' => "RSA2",
            //支付宝网关
            'gatewayUrl' => "https://openapi.alipay.com/gateway.do",
            //支付宝公钥,查看地址：https://openhome.alipay.com/platform/keyManage.htm 对应APPID下的支付宝公钥。
            'alipay_public_key' => config("pay.alipay.alipay_publick_key"),
        ];

        $payResponse = new \AlipayTradeService($config);
        $payResponse->wapPay($payRequestBuilder, $config['return_url'], $config['notify_url']);
    }

    /**
     * 回调
     */
    public function notice(Request $request) {
        $data = $request->all();
        $config = [
            //应用ID,您的APPID。
            'app_id' => config("pay.alipay.appid"),
            //商户私钥，您的原始格式RSA私钥
            'merchant_private_key' => config("pay.alipay.private_key"),
            //异步通知地址
            'notify_url' => config("pay.alipay.notify_url"),
            //同步跳转
            'return_url' => config("pay.alipay.return_url"),
            //编码格式
            'charset' => "UTF-8",
            //签名方式
            'sign_type' => "RSA2",
            //支付宝网关
            'gatewayUrl' => "https://openapi.alipay.com/gateway.do",
            //支付宝公钥,查看地址：https://openhome.alipay.com/platform/keyManage.htm 对应APPID下的支付宝公钥。
            'alipay_public_key' => config("pay.alipay.alipay_publick_key"),
        ];
        $aliPaySevice = new \AlipayTradeService($config);
        $result = $aliPaySevice->check($data);

        /* 实际验证过程建议商户添加以下校验。
        1、商户需要验证该通知数据中的out_trade_no是否为商户系统中创建的订单号，
        2、判断total_amount是否确实为该订单的实际金额（即商户订单创建时的金额），
        3、校验通知中的seller_id（或者seller_email) 是否为out_trade_no这笔单据的对应的操作方（有的时候，一个商户可能有多个seller_id/seller_email）
        4、验证app_id是否为该商户本身。
        */
        if($result) {//验证成功
            /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
            //请在这里加上商户的业务逻辑程序代


            //——请根据您的业务逻辑来编写程序（以下代码仅作参考）——

            //获取支付宝的通知返回参数，可参考技术文档中服务器异步通知参数列表

            //商户订单号

            $out_trade_no = $data['out_trade_no'];


            if($data['trade_status'] == 'TRADE_FINISHED') {

                //判断该笔订单是否在商户网站中已经做过处理
                //如果没有做过处理，根据订单号（out_trade_no）在商户网站的订单系统中查到该笔订单的详细，并执行商户的业务程序

                //请务必判断请求时的total_amount与通知时获取的total_fee为一致的
                //如果有做过处理，不执行商户的业务程序

                //注意：
                //退款日期超过可退款期限后（如三个月可退款），支付宝系统发送该交易状态通知
            }
            else if ($data['trade_status'] == 'TRADE_SUCCESS') {
                //判断该笔订单是否在商户网站中已经做过处理
                //如果没有做过处理，根据订单号（out_trade_no）在商户网站的订单系统中查到该笔订单的详细，并执行商户的业务程序
                // 修改订单状态 以及 增加资源表内, 销量， 销量金额
                $order = order::where("ordernum", $out_trade_no)->first();
                if ($order['orderstatus'] == 0) {
                    $this->updateOrderStatus($order);
                    return 'success';
                }

                //请务必判断请求时的total_amount与通知时获取的total_fee为一致的
                //如果有做过处理，不执行商户的业务程序
                //注意：
                //付款完成后，支付宝系统发送该交易状态通知
            }
            //——请根据您的业务逻辑来编写程序（以上代码仅作参考）——

            echo "success";		//请不要修改或删除

        }else {
            //验证失败
            echo "fail";	//请不要修改或删除
        }
    }

    /**
     * 完成页
     */
    public function success() {
        return view('wechat/success');
    }

    public function test() {

        // $order = order::where("ordernum", 'gl2130190467533745')->first();


        // $order_res = order::where('id',$order['id'])->first();
        // if($order_res['orderstatus'] == 0){
        // 	$date = date('Y-m-d H:i:s', time());
        // 	$current_update = ['orderstatus' => 1, 'modifytime' => $date];
        // 	if ($order['pack_type'] == 1) { // 更新续费订单的renew状态和end_time
        // 		$lastcontent = contents::where('sid',$order['sid'])->orderBy('createtime','desc')->first();
        // 		$sources = source::where('sid',$order['sid'])->first();
        // 		if(strtotime($lastcontent['createtime'])+24*3600>=time()){
        // 			//这地方还要判断这条内容是否在上一个订单的时间范围内
        // 			$pre_order = order::where('sid', $order['sid'])->where('buyerid', $order['buyerid'])
        // 				->whereRaw('orderstatus in (1,3)')->orderBy('createtime','desc')->first();
        // 			$pre_endtime = strtotime($pre_order['createtime'])+($sources['pack_day']+$sources['delayed_day'])*86400;
        // 			if($pre_endtime<strtotime($lastcontent['createtime'])){
        // 				$current_update['start_time'] = $lastcontent['createtime'];
        // 			}else{
        // 				$current_update['start_time'] = date('Y-m-d H:i:s', time());
        // 			}
        // 		}else{
        // 			//用户在24小时内未更新
        // 			$current_update['start_time'] = date('Y-m-d H:i:s', time());
        // 		}
        // 		$historyOrders = order::where('sid', $order['sid'])->where('buyerid', $order['buyerid'])
        // 			->whereRaw('orderstatus in (1,3)')->get()->ToArray();
        // 		if (!empty($historyOrders)) {
        // 			foreach ($historyOrders as $horder) {
        // 				//订单结束时间是通过订单创建时间计算
        // 				$end_time = date('Y-m-d H:i:s', strtotime($horder['createtime'])+($sources['pack_day']+$sources['delayed_day'])*86400);
        // 				order::where('id', $horder['id'])->update([
        // 					'orderstatus' => $horder['orderstatus'] | 4,
        // 					'end_time' => $end_time,
        // 					'modifytime'=>date('Y-m-d H:i:s')
        // 				]);
        // 			}
        // 		}
        // 	}
        // 	$res = DB::table('order')->where('id', $order['id'])->update($current_update);
        // 	var_dump($res);exit;
        // 	$buy_client_extra = client_extra::where('id', $order['buyerid'])->first();
        // 	$status = decbin($buy_client_extra['role']);
        // 	$oldStatus = sprintf('%08d', $status);
        // 	$newStatus = substr_replace($oldStatus, 1, -2, 1);
        // 	$newStatusChange = bindec((int)$newStatus);
        // 	DB::table('client_extra')->where('id', $order['selledid'])->increment('soldnum');   // 销售的料总数加1
        // 	// 增加料已售出数量
        // 	DB::table('source_extra')->where('id', $order['sourceid'])->increment('soldnumber');
        // 	// 付款者增加已经支付的金额， 已经购买的料总数， 修改用户身份购买者
        // 	DB::table('client_extra')->where('id', $order['buyerid'])->increment('buynum');  // 已经购买的料总数
        // 	DB::table('client_extra')->where('id', $order['buyerid'])->increment('payed', $order['price']);  // 已经支付的金额
        // 	DB::table('client_extra')->where('id', $order['buyerid'])->update(['role' => $newStatusChange]);  // 修改用户身份
        // 	//不中退款，在判定红单后在增加收益
        // 	if ($order['pack_type'] != 2) {
        // 		DB::table('client_extra')->where('id', $order['selledid'])->increment('balance', $order['price']);   // 销售者余额增加
        // 		DB::table('client_extra')->where('id', $order['selledid'])->increment('total', $order['price']);  // 收入增加
        // 		//记录金额变更
        // 		client_money_change::setChange($order['selledid'], $order['price'], 1, 1);
        // 	}

        // 	//记录用户支付步骤操作
        // 	purchase_record::setPurchaseRecord($order['sid'], $order['buyerid'], $order['selledid'], 3);
        // }
        // print_r($order);exit;
    }

    /**
     * 订单号生成
     * @param $is_prefix
     * @return string
     */
    private function order_number($is_prefix) {
        //$yCode = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J');
        $yCode = array(
            '0',
            '1',
            '2',
            '3',
            '4',
            '5',
            '6',
            '7',
            '8',
            '9'
        );
        if ($is_prefix) {
            $orderSn = "gl" . $yCode[intval(date('Y')) - 2017] . strtoupper(dechex(date('m'))) . date('d') . substr(time(), -5) . substr(microtime(), 2, 5) . sprintf('%02d', rand(0, 99));
        } else {
            $orderSn = $yCode[intval(date('Y')) - 2017] . strtoupper(dechex(date('m'))) . date('d') . substr(time(), -5) . substr(microtime(), 2, 5) . sprintf('%02d', rand(0, 99));
        }

        return $orderSn;
    }

    function is_weixin() {

        if (strpos($_SERVER['HTTP_USER_AGENT'],

                'MicroMessenger') !== false) {

            return true;

        }

        return false;

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
                        $current_update['start_time'] = date('Y-m-d H:i:s', time());
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
            DB::table('client_extra')->where('id', $order['selledid'])->increment('soldnum');   // 销售的料总数加1
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

            //记录用户支付步骤操作
            purchase_record::setPurchaseRecord($order['sid'], $order['buyerid'], $order['selledid'], 3);
        }
    }



}
