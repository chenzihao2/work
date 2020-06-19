<?php

namespace App\Http\Controllers;

use App\models\client;
use App\models\order;
use App\models\source;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Redis;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

	/**
	 * 给一个料所有的买家发送消息
	 * @param $sid
	 */
	public function sendNoticeMsg($sid) {
		//获取所有订单
		$offset = 0;
		$query = order::select()->where('sid',$sid)->where('orderstatus',1);
		$result = $query->offset($offset)->limit(1)->first();
		while ($result) {
			//发送通知
			$this->sendUpdateMsg($result);
			$offset++;
			$query = order::select()->where('sid',$sid)->where('orderstatus',1);
			$result = $query->offset($offset)->limit(1)->first();
		}
		
    }


	public function sendUpdateMsg($order) {
		//换取openid
		$userInfo = $this->getOpenId($order['buyerid']);

		$token = $this->msg_access_token(2);

		$time = date("Y-m-d H:i:s", time());

		//公众号
		//$noticeTemplateId = "23rQY_hmlkQe5TMULTjGkB-cSTX-1fGkNzbvDWLjKR8";
		$noticeTemplateId = "Vunrao8nVjWqdnotleBksNtOMGcEoCdfAK8wD4gsqFw";
		$openid = $userInfo['serviceid'];
		$api = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=$token";

		$params['touser'] = $openid;
		$params['template_id'] = $noticeTemplateId;
		$params['url'] = "https://glm9.qiudashi.com/sourcedetail?sid=".$order['sid'];
//			$params['appid'] = "wx1ad97741a12767f9";
		//支付信息
		$msg = array();
		if ($order['pack_type'] == 1) {
			$msg['first'] = [
				'value' => "您好，您购买的包时段料已更新，点击【详情】查看更新内容。",
			];//信息详情
			$msg['keyword1'] = [
				'value' => "卖家更新",
			];//信息详情
			$msg['keyword2'] = [
				'value' => "",
			];//发布时间

		} else {
			$msg['first'] = [
				'value' => "您好，您购买的料内容已修改，请您点击【详情】查看修改内容。",
			];//信息详情
			$msg['keyword1'] = [
				'value' => "卖家更新",
			];//信息详情
			$msg['keyword2'] = [
				'value' => "",
			];//发布时间
		}

		$msg['keyword3'] = [
			'value' => "已更新",
		];//发布时间
		$params['data'] = $msg;


		if ($openid != null) {
			$result = $this->postCurl($api, $params, 'json');
	\Log::INFO($result);
			return ($result);
		} else {
			return null;
		}
	}

	public function getOpenId($uid) {
		$client = client::select('openid', 'serviceid')->where('id', $uid)->first();
		return $client;
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
}
