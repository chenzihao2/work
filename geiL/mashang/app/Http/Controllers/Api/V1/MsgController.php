<?php
/**
 * User: WangHui
 * Date: 2018/5/14
 * Time: 15:02
 */


/**公众号
 * 服务进度提醒
 * 23rQY_hmlkQe5TMULTjGkB-cSTX-1fGkNzbvDWLjKR8
 *
 * {{first.DATA}}{{fi
 * 服务类型：{{keyword1.DATA}}
 * 服务状态：{{keyword2.DATA}}
 * 服务时间：{{keyword3.DATA}}
 * {{remark.DATA}}
 *
 *退款通知
 * NCW-au_2yNl7TcaFycH4-_olLjzFG9lrLyGZPSJ-k8I
 *
 * {{first.DATA}}
 *
 * 退款原因：{{reason.DATA}}
 * 退款金额：{{refund.DATA}}
 * {{remark.DATA}}
 */

namespace App\Http\Controllers\Api\V1;


use App\models\client;
use App\models\order;
use App\models\source;
use Illuminate\Support\Facades\Redis;

class MsgController extends BaseController {

	public function test(){
		$result = $this->access_token(2);
		var_dump($result);
	}
	/**
	 * 给购买比赛料的玩家在料可看时推送消息
	 */
	public function checkTime() {
		$offset = 0;
		$query = source::select('source.sid', 'source.id', 'source.pack_type', 'source.play_time', 'source.play_start', 'source.order_status');

		$query->where('status', '<=', 1);
		$query->where('pack_type', 3);
		$query->where('notice', 0);
		$result = $query->where('play_start', 1)->offset($offset)->limit(1)->first();
		while ($result) {
//			var_dump($result['sid']);
			$now = time();
			$noticeTime = $result['play_time'] - 60 * 60;

			if ($now >= $noticeTime) {
				//通知用户，修改通知状态
				//获取购买此资源的所有订单
				$orderListQuery = order::select('buyerid', 'prepay_id');
				$orderListQuery->where('orderstatus', 1);
				$orderList = $orderListQuery->where('sid', $result['sid'])->get();
				foreach ($orderList as $order) {
					$this->msgWatchNotice($order);
				}
				$edit['notice']=1;
				source::where('sid',$result['sid'])->update($edit);
			}

			$offset++;
			$query = source::select('source.sid', 'source.id', 'source.pack_type', 'source.play_time', 'source.play_start', 'source.order_status');
			$query->where('status', '<=', 1);
			$query->where('pack_type', 3);
			$query->where('notice', 0);
			$result = $query->where('play_start', 1)->offset($offset)->limit(1)->first();
		}
	}

	/**
	 * 提醒 黑、红单的卖家判定黑红单
	 */
	public function checkRed() {
		$offset = 0;
		$query = source::select('source.sid', 'source.id', 'source.uid', 'source.form_id', 'source.pack_type', 'source.play_time', 'source.play_start', 'source.order_status');

		$query->where('status', '<=', 1);
		$query->where('pack_type', 2);
//		$query->where('id', 97108);
		$query->where('order_status', 0);
		$query->where('notice', 0);
		$result = $query->where('notice', 0)->offset($offset)->limit(1)->first();

		while($result){
			$check = order::where('sid',$result['sid'])->where('orderstatus',1)->count();
			if($check>0){
				$edit['notice']=1;
				source::where('sid',$result['sid'])->update($edit);

				$this->sendMsg($result);
			}


			$offset++;
			$query = source::select('source.sid', 'source.id', 'source.uid', 'source.form_id', 'source.pack_type', 'source.play_time', 'source.play_start', 'source.order_status');

			$query->where('status', '<=', 1);
			$query->where('pack_type', 2);
			$query->where('order_status', 0);
			$query->where('notice', 0);
			$result = $query->where('notice', 0)->offset($offset)->limit(1)->first();
		}
	}

	public function sendMsg($source) {
		//换取openid
		$userInfo = $this->getOpenId($source['uid']);
		if (isset($userInfo['serviceid']) && $userInfo['serviceid'] != null) {
			$type = 2;
		} else {
			$type = 1;
		}
		$token = $this->access_token($type);
		if ($type == 1) {
			//小程序
			$noticeTemplateId = "TSzfcY9CuEd-jYzBoPpfji4Bzmxu_W8IUuUgok9F4SE";
			$openid = $userInfo['openid'];
			$api = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=$token";
			$params['touser'] = $openid;
			$params['template_id'] = $noticeTemplateId;
			//支付信息

			$params['form_id'] = $source['form_id'];
			$msg = array();
			$msg['keyword1'] = [
				'value' => "您出售的料已经有人购买，请及时设置黑红单",
				//				'value' => '11',
			];//信息详情
			$msg['keyword2'] = [
				'value' => date("Y-m-d H:i:s", time()),
			];//发布时间
			$params['data'] = $msg;
		} elseif ($type == 2) {
			//公众号
			$noticeTemplateId = "23rQY_hmlkQe5TMULTjGkB-cSTX-1fGkNzbvDWLjKR8";
			$openid = $userInfo['serviceid'];
			$api = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=$token";

			$params['touser'] = $openid;
			$params['template_id'] = $noticeTemplateId;
			$params['appid'] = "wx1ad97741a12767f9";
			//支付信息
			$msg = array();
			$msg['keyword1'] = [
				'value' => "您出售的料已经有人购买，请及时设置黑红单",
			];//信息详情
			$msg['keyword2'] = [
				'value' => date("Y-m-d H:i:s", time()),
			];//信息详情
			$params['data'] = $msg;
		}
		if ($openid != null) {
			$result = $this->postCurl($api, $params, 'json');

//			$content = date("Y-m-d H:i:s",time())." Send set notice To:params:".json_encode($params)."\r\n";
//			file_put_contents('/data/wwwroot/geiliao_wx_app/mashang/Cron/setNotice.log',$content,FILE_APPEND);
//			$content = date("Y-m-d H:i:s",time())." Send set notice To Result:".json_encode($result)."\r\n";
//			file_put_contents('/data/wwwroot/geiliao_wx_app/mashang/Cron/setNotice.log',$content,FILE_APPEND);

			return ($result);
		} else {
			return null;
		}
	}

	/**
	 * 信息查看提醒
	 * @param $order
	 * @return mixed|null
	 */
	public function msgWatchNotice($order) {
		//换取openid
		$userInfo = $this->getOpenId($order['buyerid']);

		if (isset($userInfo['serviceid']) && $userInfo['serviceid'] != null) {
			$type = 2;
		} else {
			$type = 1;
		}

		//支付走的公众号参数，使用小程序推送会报form id错误，判断原因是 form id 与小程序参数不拼配，故小程序直接返回
		if($type==1){
			return true;
		}
		$token = $this->access_token($type);

		$time = date("Y-m-d H:i:s", time());
		if ($type == 1) {
			//小程序
			$noticeTemplateId = "TSzfcY9CuEd-jYzBoPpfji4Bzmxu_W8IUuUgok9F4SE";
			$openid = $userInfo['openid'];
			$api = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=$token";


			$params['touser'] = $openid;
			$params['template_id'] = $noticeTemplateId;
			$params['url'] = config('constants.frontend_domain') . "/sourcedetail?sid=".$order['sid'];
			//支付信息

			$params['form_id'] = $order['prepay_id'];
			$msg = array();
			$msg['keyword1'] = [
				'value' => "您好，您购买的限时料已可以查看，点击【详情】查看限时料内容。",
				//				'value' => '11',
			];//信息详情
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
			$params['url'] = config('constants.frontend_domain') . "/sourcedetail?sid=".$order['sid'];
//			$params['appid'] = "wx1ad97741a12767f9";
			//支付信息
			$msg = array();
			$msg['keyword1'] = [
				'value' => "您好，您购买的限时料已可以查看，点击【详情】查看限时料内容。",
			];//信息详情
			$msg['keyword2'] = [
				'value' => "限时料设置",
			];//发布时间
			$msg['keyword3'] = [
				'value' => '比赛前一小时限时料可查看',
			];//发布时间
			$params['data'] = $msg;
		}

		if ($openid != null) {
			$result = $this->postCurl($api, $params, 'json');

//			$content = date("Y-m-d H:i:s",time())." Send watch notice To:params:".json_encode($params)."\r\n";
//			file_put_contents('/data/wwwroot/geiliao_wx_app/mashang/Cron/watchNotice.log',$content,FILE_APPEND);
//			$content = date("Y-m-d H:i:s",time())." Send watch notice To Result:".json_encode($result)."\r\n";
//			file_put_contents('/data/wwwroot/geiliao_wx_app/mashang/Cron/watchNotice.log',$content,FILE_APPEND);
			return ($result);
		} else {
			return null;
		}
	}

	/**
	 * 退款
	 * @param $uid
	 * @param $price
	 */
	private function sendToUser($uid, $price, $type) {
		$token = $this->access_token($type);

		$api = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=$token";
		$noticeTemplateId = "wxygKKV54YTNAhsNGFEWIfEZGto2POU5XLp0ulMqWLE";
		$params['touser'] = "";
		$params['template_id'] = $noticeTemplateId;
		//支付信息
		$params['form_id'] = $noticeTemplateId;
		$refund = array();
		$refund['keyword1'] = $price . "元";//退款金额
		$refund['keyword2'] = "";//退款原因
		$refund['keyword3'] = "";//退款时间
		$params['data'] = $noticeTemplateId;
		$result = $this->postCurl($api, $params, 'json');
		var_dump($result);

	}

	public function getOpenId($uid) {
		$client = client::select('openid', 'serviceid')->where('id', $uid)->first();
		return $client;
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

	public function access_token($type = 1) {
		if ($type == 1) {
			$key = 'xcx_access_token';
		} else {
			$key = 'gzh_access_token_subscribe';

		}
		$re = Redis::exists($key);
		if ($re) {
//		if (false) {
//			$content = date("Y-m-d H:i:s",time())." Cache Token Result:".Redis::get($key)."\r\n";
//			file_put_contents('/data/wwwroot/geiliao_wx_app/mashang/Cron/Token.log',$content,FILE_APPEND);

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
			Redis::setex($key,7000, $data['access_token']);



//			$content = date("Y-m-d H:i:s",time())." Token Result:".$data['access_token']."\r\n";
//			file_put_contents('/data/wwwroot/geiliao_wx_app/mashang/Cron/Token.log',$content,FILE_APPEND);
			return $data['access_token'];
		}
	}

}
