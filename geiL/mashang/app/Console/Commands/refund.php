<?php
/**
 * User: WangHui
 * Date: 2018/7/26
 * Time: 15:24
 */

namespace App\Console\Commands;


use App\models\buyer;
use App\models\client;
use App\models\order;
use App\models\refund_order;
use App\models\refund_order_tmp;
use App\models\client_subscribe;
use App\models\source;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

use WxPayRefund;


include_once(app_path() . "/alipay/wappay/service/AlipayTradeService.php");
include_once(app_path() . "/alipay/wappay/buildermodel/AlipayTradeRefundContentBuilder.php");
include_once (app_path()."/../public/alipay/aop/AopCertClient.php");
include_once (app_path()."/../public/alipay/aop/request/AlipayTradeRefundRequest.php");

class refund extends Command {

    protected $signature = 'refund';

    protected $description = 'refund';

    public function __construct() {
        parent::__construct();

    }

    public function handle() {
      $redisKey = ['refund_list', 'refund_list_admin'];
      foreach ($redisKey AS $key=>$val){
        while ($sid = Redis::rpop($val)) {
          $is_oper = $val == 'refund_list_admin' ? 1 : 0;
          $result = order::select()->where('sid', $sid)->whereIn('orderstatus', [1, 3])->get();
          if(!empty($result)){
            $this->refund_tmp($result, $is_oper);
          }
        }
      }

      $refundList = refund_order_tmp::where('status', 0)->where('refund_time', '<=', time())->get();
      if(!empty($refundList)){
        foreach ($refundList AS $ke=>$va) {
          //发起退款请求
          if ($va['price'] > 0) {
            //检查退款表是否有记录
            $refundOrder = refund_order::where('order', $va['order'])->first();
            if (!empty($refundOrder) || $refundOrder['order'] == $va['order']) {
              //已退款,跳过
            } else {
                //if ($va['buyerid'] == 382115) {
                    //var_dump(382115);
                    $refund = $this->refund($va);
                //}
            }
          }
        }
      }
    }


    private function refund_tmp($data, $is_oper = 0, $is_manual = 0, $reason = '') {
      // 获取最后记录的退款时间
      $fildPath = __DIR__ . '/refundLastTime.log';
      //获取分配时间数组
      $refund_time = $this->getRefundTime(count($data));
      $refund_key = 0;
      foreach ($data AS $key=>$val){
        $refund_order_tmp = [];
        $refund_order_tmp = refund_order_tmp::where('order', $val['ordernum'])->first();
        if(!empty($refund_order_tmp) || $val['price'] <= 0){
          continue;
        }

        $refund = [];
        $refund['sid'] = $val['sid'];
        $refund['is_batch_order'] = $val['is_batch'];
        if ($val['is_batch'] == 1) {
          $refund['batch_ordernum'] = $val['batch_order_num'];
        }
        $refund['order'] = $val['ordernum'];
        $refund['buyerid'] = $val['buyerid'];
        $refund['selledid'] = $val['selledid'];
        $refundOrder = $this->refundOrder();      //退款单号
        $refund['refund'] = $refundOrder;
        $refund['price'] = $val['price'];
        $refund['payment'] = $val['payment'];
	      $refund['mch_account'] = $val['mch_account'];
        $refund['refund_time'] = isset($refund_time[$refund_key]) ? $refund_time[$refund_key] : time();
        file_put_contents($fildPath, $refund['refund_time']);
        $refund_key++;
        $refund['create_time'] = time();
        $refund['oper'] = $is_oper;
        $refund['status'] = 0;
        $refund['is_manual'] = $is_manual;
        $refund['reason'] = $reason;
        refund_order_tmp::create($refund);
        //清除累计总额
        buyer::where('buyerid', $val['buyerid'])->where('selledid', $val['selledid'])->decrement('payed', $val['price']);

        $this->sendMsg($refund, 2);
      }
    }

    private function getRefundTime($refundTotal){
        // 获取最后记录的退款时间
        $fildPath = __DIR__ . '/refundLastTime.log';
        //当前时间
        $nowTime = time();

        if (!file_exists($fildPath)) {
            $lastFoundTime = $nowTime;
            file_put_contents($fildPath, $nowTime);
        } else {
            $lastFoundTime = file_get_contents($fildPath);
            $lastFoundTime = intval($lastFoundTime);
            if ($lastFoundTime < $nowTime) {
                $lastFoundTime = $nowTime;
            }
        }

        $refund_time = [];
        $refundTime = 30;
        $lastTime = $lastFoundTime + $refundTime;

        for ($i = 0; $i < $refundTotal; $i++) {
            if (date('H', $lastTime) < 8) {
                $lastTime = strtotime(date('Y-m-d', $lastTime) . ' 08:00:00');
            } else if (date('H', $lastTime) > 22) {
                $lastTime = strtotime(date('Y-m-d', $lastTime) . ' 08:00:00') + 24 * 3600;
            }
	
	          // file_put_contents(__DIR__ . '/refundTimeList.log', date("Y-m-d H:i:s", $lastTime) . "\n", FILE_APPEND);
            $refund_time[$i] = $lastTime;
            $lastTime = $lastTime + $refundTime;
        }
	
	      // file_put_contents(__DIR__ . '/refundTimeList.log', 'last: ' . date("Y-m-d H:i:s", $lastTime) . "\n", FILE_APPEND);
        return $refund_time;
    }

    private function getRefundTimeBak($refundTotal){
        //当前时间
        $nowTime = time();

        //退款总耗时
        $totalTimes = 14 * 3600;

        $tomorrowHoures8 = strtotime(date("Y-m-d", strtotime('+1 day')),$nowTime) + 8 * 3600;

        //平均每单退款时间
        $refundTime = $totalTimes/$refundTotal;

        $timeNext24 = $nowTime + 24 * 3600;

        $refund_time = [];

        for ($i = $nowTime + $refundTime; $i <= $timeNext24; $i = $i + $refundTime ){
            if(date("H", $i) >= 8 && date("H", $i)  < 22){
                $refund_time[] = $i;
            } elseif ($i < $tomorrowHoures8) {
                $i = $tomorrowHoures8 - $refundTime;
            }
        }
        return $refund_time;
    }

    private function refund($data) {
      //退款单号
      $refund = array();
      $refund['sid'] = $data['sid'];
      $refund['is_batch_order'] = $data['is_batch_order'];
      $refund['batch_ordernum'] = $data['batch_ordernum'];
      $refund['order'] = $data['order'];
      $refund['buyerid'] = $data['buyerid'];
      $refund['selledid'] = $data['selledid'];
      $refund['refund'] = $refundOrder = $data['refund'];
      $refund['price'] = $data['price'];
      $refund['oper'] = $data['oper'];
      $refund['edit_time'] = $data['refund_time'];
      $refund['time'] = time();
      $refund['status'] = 0;
      $refund['is_manual'] = $data['is_manual'];
      $refund['reason'] = $data['reason'];
      $refund['assumed_host'] = $data['assumed_host'];
      $refund['mch_account'] = $data['mch_account'];
      refund_order::create($refund);

      refund_order_tmp::where('id', $data['id'])->update(['status'=>1]);
      if($data['payment'] == 1){
        //微信支付退款
        $input = new WxPayRefund();
        $mch_ordernum = $refund['is_batch_order'] ? $refund['batch_ordernum'] : $refund['order'];
        $input->SetOut_trade_no($mch_ordernum);
	      $total_fee = $data['price'];
	      if ($refund['is_batch_order'] == 1) {
	        $total_fee = order::select()->where('batch_order_num', $refund['batch_ordernum'])->where('orderstatus', 1)->sum('price');
	      }
        $input->SetTotal_fee($total_fee * 100);
        $input->SetRefund_fee($data['price'] * 100);
        $input->SetNotify_url(config('pay.wxpay.refund_notify_url'));
        $input->SetOut_refund_no($refundOrder);
        $input->SetOp_user_id("1487651632");
        $input->SetNonce_str("1487651632");

	      $mch_config_key = ($refund['mch_account'] == 0) ? 'master' : 'slave_' . $refund['mch_account'];
        $mch_config = config('pay.wxpay.' . $mch_config_key);
        
        $config['appid'] = config('wxxcx.wechat_appid');
        $config['secret'] = config('wxxcx.wechat_appsecret');
        $config = array_merge($config, $mch_config);

        //$config['mchid'] = config('pay.wxpay.mchid');
        //$config['mch_secret_key'] = config('pay.wxpay.mch_secret_key');

        $result = \WxPayApi::refund($input, $config);
        if ($result['result_code'] == "SUCCESS") {
          return true;
        } else {
            $error_info = 'order_num:['. $refund['order'] . ']【' . json_encode($result) . '】';
            //\Log::info($error_info);
            var_dump($error_info);
          return false;
        }
      } elseif($data['payment'] == 2) {
        //华移支付退款
        $params = [];
        $params['orderNo'] = $data['order'];     //唯一订单号，每次请求必须不同(请全部使用数字组合)
        $params['merchantNo'] = config('pay.hypay.merchantNo'); //获取配置文件里面的商户号
        $params['refundFee'] = $data['price'] * 100;
        $params['refundReson'] = '判定黑单';
        $params['timestamp'] = time();
        $order = $this->payHttpPost('order/refundOrder', $params);

        $wx_pre = json_decode($order, true);
        if($wx_pre['code'] == 1){
          return TRUE;
        } else {
          return FALSE;
        }
      } elseif($data['payment'] == 3) {
        //支付宝支付退款
        $mch_ordernum = $data['is_batch_order'] ? $data['batch_ordernum'] : $data['order'];
        $notify_url = config("pay.alipay.notify_url");
        $return_url = config("pay.alipay.return_url");
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
        $request = new \AlipayTradeRefundRequest();
        $request->setBizContent("{" .
            "\"refund_reason\":\"判定黑单\"," .
            "\"out_trade_no\":\"" . $mch_ordernum . "\"," .
            "\"out_request_no\":\"" . $data['order'] . "\"," .
            "\"refund_amount\":\"" . $data['price'] . "\"" .
            //"\"notify_url\":\"" . $notify_url . "\"," .
            //"\"return_url\":\"" . $return_url . "\"," .
            "}");
        //$config = [
        //  //应用ID,您的APPID。
        //  'app_id' => config("pay.alipay.appid"),
        //  //商户私钥，您的原始格式RSA私钥
        //  'merchant_private_key' => config("pay.alipay.private_key"),
        //  //异步通知地址
        //  'notify_url' => config("pay.alipay.notify_url"),
        //  //同步跳转
        //  'return_url' => config("pay.alipay.return_url"),
        //  //编码格式
        //  'charset' => "UTF-8",
        //  //签名方式
        //  'sign_type' => "RSA2",
        //  //支付宝网关
        //  'gatewayUrl' => "https://openapi.alipay.com/gateway.do",
        //  //支付宝公钥,查看地址：https://openhome.alipay.com/platform/keyManage.htm 对应APPID下的支付宝公钥。
        //  //'alipay_public_key' => config("pay.alipay.alipay_publick_key"),
        //    'alipay_public_key' => $alipay_public_key,
        //];
        //$RequestBuilder = new \AlipayTradeRefundContentBuilder();
        //$RequestBuilder->setOutTradeNo($mch_ordernum);
        //$RequestBuilder->setRefundAmount($data['price']);
        //$RequestBuilder->setOutRequestNo($data['refund']);
        //$RequestBuilder->setRefundReason('判定黑单');
        //$request->setNotifyUrl($notify_url);
        //$request->setReturnUrl($return_url);

			  //$Response = new \AlipayTradeService($config);
			  //$result=$Response->Refund($RequestBuilder);
        $result = $aop->execute($request);
        //var_dump($request->getBizContent());
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode = $result->$responseNode->code;
            if(!empty($resultCode)&&$resultCode == 10000){
                  refund_order::where('order', $data['order'])->update(['status' => 2]);
				  return true;
			  } else {
				  return false;
			  }
		  }elseif($data['payment'] == 4){
			  return $this->qfRefund($data);
		  }
	  }

    private function refundOrder(){
        return md5(time().rand(0,10000));
    }
    public function getOpenId($uid) {
        $client = client::select('openid', 'serviceid')->where('id', $uid)->first();
        return $client;
    }

    public function sendMsg($order, $mark) {
        //换取openid
        $userInfo = $this->getOpenId($order['buyerid']);
        if (isset($userInfo['serviceid']) && $userInfo['serviceid'] != null) {
            $type = 2;
        } else {
            $type = 1;
        }

        $refund_time = date("m月d日H:i", $order['refund_time']);
        $price = $order['price'];

        $time = date("Y-m-d H:i:s", time());

        if ($type == 1) {

            $token = $this->msg_access_token($type);

            //小程序
            $noticeTemplateId = "TSzfcY9CuEd-jYzBoPpfji4Bzmxu_W8IUuUgok9F4SE";
            $openid = $userInfo['openid'];
            $api = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=$token";
            $params['touser'] = $openid;
            $params['template_id'] = $noticeTemplateId;
            //支付信息
            $params['form_id'] = $order['prepay_id'];
            $msg = array();

            if ($mark == 2) {
                $msg['keyword1'] = [
                    'value' => "您购买的料已确认为黑，钱款将于24小时内退回您的支付账户。",
                ];//信息详情

            } else {
                $msg['keyword1'] = [
                    'value' => "您购买的料卖家已确认为红，如有疑问请联系客服",
                ];//信息详情
            }

            $msg['keyword2'] = [
                'value' => $time,
            ];//发布时间
            $params['data'] = $msg;

            if ($openid != null) {
                $result = $this->postCurl($api, $params, 'json');
                return ($result);
            } else {
                return null;
            }
        } else {

            $token1 = $this->msg_access_token($type, 1);
            $token2 = $this->msg_access_token($type, 2);

            // 给两个公众号同时发送消息
            //支付信息
            $msg = array();
            if ($mark == 2) {
                $msg['first'] = [
                    'value' => "您购买的料已确定为黑，钱款将原路退回您的支付账户。",
                ];
                $msg['reason'] = [
                    'value' => "不对返还退款",
                ];
                $msg['refund'] = [
                    'value' => $price . '元',
                ];
            } else {
                $msg['keyword1'] = [
                    'value' => "您购买的料卖家已确认为红，如有疑问请联系客服",
                ];//信息详情
                $msg['keyword2'] = [
                    'value' => "红",
                ];//发布时间
            }

            $msg['remark'] = [
                'value' => "预计退款到账时间为" . $refund_time . "， 请您注意查收。",
            ];//发布时间
            $params['data'] = $msg;

            $api = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=";

            //公众号 1
            $params1 = $params;

            $noticeTemplateId1 = "kXgM6rOnp_t-rhjBZVuu4bBIcsG4cpVFIZP9b3cWsso";
            $openid1 = $userInfo['serviceid'];

            $params1['touser'] = $openid1;
            $params1['template_id'] = $noticeTemplateId1;

            // 公众号 2
            $params2 = $params;

            $openid2 = null;
            $noticeTemplateId2 = "OIKxJxL18IYs2h2SlRAWKihE5IwQJDqKs6GSio8mKDg";
            $subscribeInfo = client_subscribe::select('openid')->where('user_id', $order['buyerid'])->where('status', 1)->first();
            if(!empty($subscribeInfo)){
                $openid2 = $subscribeInfo['openid'];
            }

            $params2['touser'] = $openid2;
            $params2['template_id'] = $noticeTemplateId2;

            if (!empty($openid1)) {
                $result1 = $this->postCurl($api . $token1, $params1, 'json');
            }
            if (!empty($openid2)) {
                $result2 = $this->postCurl($api . $token2, $params2, 'json');
            }

            if ( ! empty($result1) || ! empty($result2)) {
                return ['result1' => (isset($result1) ? $result1 : []), 'result2' => (isset($result2) ? $result2 : [])];
            } else {
                return null;
            }
        }
    }

    public function postCurl($url, $data, $type) {
        if ($type == 'json') {
            $data = json_encode($data);//对数组进行json编码
            $header = array(
                "Content-type: application/json;charset=UTF-8",
                "Accept: application/json",
                "Cache-Control: no-cache",
                "Pragma: no-cache"
            );
        }
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        $res = curl_exec($curl);
        if (curl_errno($curl)) {
            echo 'Error+' . curl_error($curl);
        }
        curl_close($curl);
        return $res;
    }

    public function msg_access_token($type = 1, $gzh = 1) {
        if ($type == 1) {
            $key = 'xcx_access_token';
        } else {
            if ($gzh == 1) {
                $key = 'gzh_access_token_subscribe';
            } else {
                $key = 'gzh_access_token_subscribe';
            }
        }
        $re = Redis::exists($key);
        if ($re) {
            return Redis::get($key);
        } else {
            //小程序
            if ($type == 1) {
                $appid = 'wx1ad97741a12767f9';
                $appsecret = '001b7d3059af1a707a5d4e432aa45b7a';
            } else {
                //公众号
                if ($gzh == 1) {
                    $appid = config("wxxcx.wechat_appid");
                    $appsecret = config("wxxcx.wechat_appsecret");
                } else {
                    $appid = config("wxxcx.wechat_subscribe_appid");
                    $appsecret = config("wxxcx.wechat_subscribe_appsecret");
                }
            }
            $action_url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$appsecret}";
            $outopt = file_get_contents($action_url);
            $data = json_decode($outopt, True);
            Redis::setex($key, 7000, $data['access_token']);
            return $data['access_token'];
        }
    }


    /**
     * 三方支付
     * CURL 三方支付请求接口
     * @param $url
     * @param $param
     * @return mixed
     */
    public function payHttpPost($url, $param)
    {
        $param['timestamp'] = 	time()*1000;  //统一给参数添加时间戳参数
        $reqUrl = config('pay.hypay.reqUrl');
        $param['sign'] = $this->createSign($param); //生成签名参数
        $ch = curl_init();//启动一个CURL会话
        curl_setopt($ch, CURLOPT_URL, $reqUrl.$url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); //设置请求超时时间
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST"); //设置请求方式为POST请求
        curl_setopt($ch, CURLOPT_POST, 1); //发送一个常规的POST请求
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($param)); //将params 转成 a=1&b=2&c=3的形式
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //curl获取页面内容, 不直接输出
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // https请求 不验证证书和hosts
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        $data = curl_exec($ch); // 已经获取到内容，没有输出到页面上。
        curl_close($ch);
        return $data;
    }


    /**
     * 三方支付
     * 获取sign签名方法
     * @return string
     */
    public function createSign($parms)
    {
        $signPars = "";
        ksort($parms);
        foreach ($parms as $k => $v) {
            if ("" != $v && "sign" != $k) {
                $signPars .= $k . "=" . $v;
            }
        }
        $secret = config('pay.hypay.secret');
        $sign = md5($signPars . $secret); //sign签名生成
        return $sign;
    }


	public function qfRefund($data) {
		$url = "https://openapi.qfpay.com/trade/v1/refund";
		$params['txamt'] = $data['price'] * 100;
		$params['out_trade_no'] = $data['refund'];

    $orderInfo = order::select('porder')->where('ordernum', $data['order'])->where('orderstatus', 1)->first();
		$params['syssn'] = $orderInfo['porder'];
//		$params['mchid'] = "V7WLNuJwDa";
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
				return true;
			} else {
				return false;
			}
		}else{
			return false;
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

}
