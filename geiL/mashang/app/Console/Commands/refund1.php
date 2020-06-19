<?php
/**
 * User: WangHui
 * Date: 2018/7/26
 * Time: 15:24
 */

namespace App\Console\Commands;


use App\models\client;
use App\models\order;
use App\models\refund_order;
use App\models\source;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

use WxPayRefund;

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
                $clock = 1;
                $refundFalseClock = 1;
                $offset = 0;
                $query = order::select()->where('sid', $sid)->where('orderstatus', 1);
                $result = $query->offset($offset)->limit(1)->first();
                while ($result) {
                    //发起退款请求
                    if ($result['price'] > 0) {
                        //检查退款表是否有记录
                        $refundOrder = refund_order::where('order', $result['ordernum'])->first();
                        if ($refundOrder) {
                            //已退款,跳过
                        } else {
                            $is_oper = 0;
                            $is_oper = $val == 'refund_list_admin' ? 1 : 0;
                            $refund = $this->refund($result, $is_oper);
                            $this->sendMsg($result, 2);
                            if (!$refund) {
                                $refundFalseClock++;
                            }
                        }
                    }
                    $offset++;
                    $query = order::select()->where('sid', $sid)->where('orderstatus', 1);
                    $result = $query->offset($offset)->limit(1)->first();
                    $clock++;
                    if ($clock % 100 == 0) {
                        sleep(1);
                    }
                    if ($refundFalseClock % 5 == 0) {
                        sleep(1);
                    }
                }
            }
        }

	}


	private function refund($data, $is_oper) {
        //退款单号
        $refund = array();
        $refund['sid'] = $data['sid'];
        $refund['order'] = $data['ordernum'];
        $refund['buyerid'] = $data['buyerid'];
        $refundOrder = $this->refundOrder();
        $refund['refund'] = $refundOrder;
        $refund['price'] = $data['price'];
        $refund['oper'] = $is_oper;
        $refund['time'] = time();
        $refund['status'] = 0;

        refund_order::create($refund);

	    if($data['payment'] == 1){
	        //微信支付退款
            $input = new WxPayRefund();
            $input->SetOut_trade_no($data['ordernum']);
            $input->SetTotal_fee($data['price'] * 100);
            $input->SetRefund_fee($data['price'] * 100);
            $input->SetNotify_url(config('pay.wxpay.refund_notify_url'));


            $input->SetOut_refund_no($refundOrder);
            $input->SetOp_user_id("1487651632");
            $input->SetNonce_str("1487651632");

            $config['appid'] = config('wxxcx.wechat_appid');
            $config['secret'] = config('wxxcx.wechat_appsecret');
            $config['mchid'] = config('pay.wxpay.mchid');
            $config['mch_secret_key'] = config('pay.wxpay.mch_secret_key');

            $result = \WxPayApi::refund($input, $config);

            if ($result['result_code'] == "SUCCESS") {
                //更新状态
//			refund_order::where('order', $data['ordernum'])->update([
//				'status' => 2
//			]);
                return true;
            } else {
                return false;
            }
        } elseif($data['payment'] == 2) {
            //华移支付退款
            $params = [];
            $params['orderNo'] = $data['ordernum'];     //唯一订单号，每次请求必须不同(请全部使用数字组合)
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
					'value' => "您购买的料已确认为黑，钱款将退还至微信零钱",
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
			$noticeTemplateId = "23rQY_hmlkQe5TMULTjGkB-cSTX-1fGkNzbvDWLjKR8";
			$openid = $userInfo['serviceid'];
			$api = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=$token";

			$params['touser'] = $openid;
			$params['template_id'] = $noticeTemplateId;
//			$params['appid'] = "wx1ad97741a12767f9";
			//支付信息
			$msg = array();
			if ($mark == 2) {
				$msg['keyword1'] = [
					'value' => "您购买的料已确认为黑，钱款将退还至微信零钱",
				];//信息详情
				$msg['keyword2'] = [
					'value' => "黑",
				];//发布时间

			} else {
				$msg['keyword1'] = [
					'value' => "您购买的料卖家已确认为红，如有疑问请联系客服",
				];//信息详情
				$msg['keyword2'] = [
					'value' => "红",
				];//发布时间

			}

			$msg['keyword3'] = [
				'value' => $time,
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

}
