<?php

namespace App\Console\Commands;


use App\models\buyer;
use App\models\client;
use App\models\order;
use App\models\refund_order;
use App\models\refund_order_tmp;
use App\models\client_money_change;
use App\models\source;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;

use WxPayRefund;


include_once(app_path() . "/alipay/wappay/service/AlipayTradeService.php");
include_once(app_path() . "/alipay/wappay/buildermodel/AlipayTradeRefundContentBuilder.php");

class refund_manual extends Command {

    protected $signature = 'refund_manual';
    protected $description = 'refund_manual';

    public function __construct() {
        parent::__construct();

    }

    public function handle() {
        //纠纷类手动退款订单
        while($manualRefundInfo = Redis::rpop('refund_list_manual')) {
          $manualRefundInfo = json_decode($manualRefundInfo, true);
          $result = order::select()->where('id', $manualRefundInfo['orderId'])->get();
          if(!empty($result)){
	    $assumed_host = isset($manualRefundInfo['assumed_host']) ? $manualRefundInfo['assumed_host'] : 0;
            $this->refund_tmp($result, 1, 1, $manualRefundInfo['reason'], $assumed_host);
          }
        }
    }

    private function refund_tmp($data, $is_oper = 0, $is_manual = 0, $reason = '', $assumed_host = 0) {
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
	$refund['assumed_host'] = $assumed_host;
        refund_order_tmp::create($refund);

	if ($assumed_host == 0) {
	  //卖家余额
          DB::table('client_extra')->where('id', $val['selledid'])->decrement('balance', $val['price']);   // 销售者余额减除退款金额
          DB::table('client_extra')->where('id', $val['selledid'])->decrement('total',  $val['price']);  // 收入减除退款金额
          //记录金额变更
          client_money_change::setChange($val['selledid'], $val['price'], 2, 3);

          //清除累计总额
          buyer::where('buyerid', $val['buyerid'])->where('selledid', $val['selledid'])->decrement('payed', $val['price']);
          //$this->sendMsg($refund, 2);
	}
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

            $refund_time[$i] = $lastTime;
            $lastTime = $lastTime + $refundTime;
        }
        return $refund_time;
    }

    /*private function getRefundTime($refundTotal){
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
//                $refund_time[] = date("Y-m-d H:i:s", $i);
                $refund_time[] = $i;
            } elseif ($i < $tomorrowHoures8) {
                $i = $tomorrowHoures8 - $refundTime;
            }
        }
        return $refund_time;
    }*/

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

        $token = $this->msg_access_token($type);

        $time = date("Y-m-d H:i:s", time());
        if ($type == 1) {
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

        } else {
            //公众号
            $noticeTemplateId = "kXgM6rOnp_t-rhjBZVuu4bBIcsG4cpVFIZP9b3cWsso";
            $openid = $userInfo['serviceid'];
            $api = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=$token";

            $params['touser'] = $openid;
            $params['template_id'] = $noticeTemplateId;
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
        }

        if ($openid != null) {
            $result = $this->postCurl($api, $params, 'json');
            return ($result);
        } else {
            return null;
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

    public function msg_access_token($type = 1) {
        if ($type == 1) {
            $key = 'xcx_access_token';
        } else {
            $key = 'gzh_access_token_subscribe';

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
                $appid = config("wxxcx.wechat_appid");
                $appsecret = config("wxxcx.wechat_appsecret");

                $key = 'gzh_access_token_subscribe';
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
