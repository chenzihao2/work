<?php
/**
 * User: zhangweihong
 * Date: 2019/10/8
 */

namespace App\Console\Commands;

use App\models\client;
use App\models\client_withdraw;
use App\models\client_subscribe;
use App\models\follow;
use App\models\source;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class sendAdminNotice extends Command {

    protected $signature = 'sendAdminNotice';
    protected $description = 'sendAdminNotice';

    public function __construct() {
        parent::__construct();
    }

    public function handle() {
      //$types_admin = ['jinpai', 'shenhe', 'tixian', 'qianyifans', 'sell_data'];   //发送给卖家
      //$types_user  =['create', 'update', 'refund', 'recall'];     //发送给粉丝

      while ($uid = Redis::rpop('admin_user_setvip')) {   //设置金牌卖家
        $this->setUserVip($uid);
      }

      while ($wid = Redis::rpop('admin_withdraw')) {    //支付宝提现
        $this->withdraw($wid);
      }

      while ($sid = Redis::rpop('admin_check_source')) {    //料审核
        $this->checkSourceContent($sid);
      }

      while ($userInfo = Redis::rpop('admin_move_followers')) {   //粉丝迁移成功
        $this->moveFollowers($userInfo);
      }
    }

    public function setUserVip($uid) {
      $templateId = "n3VAVR6XwcvspLCRU8aWP8cR_f-yOz-P3TQ8ogUX4Vw";
      $url = "https://glm9.qiudashi.com/";

      $userInfo = client::select('serviceid', 'nickname')->where('id', $uid)->first();
      $msg = array(
        'first'     => ['value' => "恭喜您成为新给料金牌卖家，无需审核，极速发料。"],
        'keyword1'  => ['value' => $userInfo['nickname']],
        'keyword2'  => ['value' => "金牌卖家"],
        'keyword3'  => ['value' => date('Y-m-d H:i', time())],
        'remark'    => ['value' => "感谢您对给料的支持"]
      );
      $this->sendMessages($uid, $url, $templateId, $msg, 'setUserVip>' . $uid);
    }

    public function withdraw($wid) {
      $templateId = "8QGR_Z3-zMq1uoCN7s0rhhokJMst4ygRXQ1A4dcwGxk";
      $url = "";

      $withdraw = client_withdraw::where('id', $wid)->first();
      $money_total = sprintf("%.2f", $withdraw['balance'] - $withdraw['service_fee']);
      $msg = array(
        'first'     => ['value' => "您的提现已到账！"],
        'keyword1'  => ['value' => date('Y-m-d H:i', time())],
        'keyword2'  => ['value' => "支付宝"],
        'keyword3'  => ['value' => "$money_total 元"],
        'remark'    => ['value' => "感谢您对给料的支持！"]
      );

      $this->sendMessages($withdraw['uid'], $url, $templateId, $msg, 'withdraw>' . $wid);
    }

    public function checkSourceContent($sid) {
      $templateId = "5SutqB_KWCsbfbNkT-wq7Xt-Q70LL33jESXmUnIehlc";
      $url = "https://glm9.qiudashi.com/home/sources";

      $sourceInfo = source::where('id', $sid)->first();
      $msg_text = ($sourceInfo['is_check'] == 1) ? "审核通过" : "审核未通过";
      $msg = array(
        'first'     => ['value' => "您好，您的料已审核完毕"],
        'keyword1'  => ['value' => $sourceInfo['title']],
        'keyword2'  => ['value' => $msg_text]
      );
      $this->sendMessages($sourceInfo['uid'], $url, $templateId, $msg, 'checkSourceContent>' . $sid);
    }

    public function moveFollowers($userInfo) {
      $templateId = "A3chDKOE6MKb0hlIXQqS9pB31ZvWq4jnaWldUsFThLo";
      $url = "";

      $userInfo = json_decode($userInfo, true);
      $old_nickname = $userInfo['old_name'];
      $new_nickname = $userInfo['new_name'];
      $msg = array(
        'first' => ['value' => "尊敬的【" .$new_nickname . "】，您【". $old_nickname ."】账号上的粉丝已迁移完成！"],
        'keyword1' => ['value' => date('Y-m-d H:i')],
        'keyword2' => ['value' => '给料']
      );
      $this->sendMessages($userInfo['uid'], $url, $templateId, $msg, "moveFollowers>" . $userInfo['uid']);

    }

    public function sendMessages($uid, $url, $templateId, $msg, $subject = "") {

      $openid = null;
      $subscribeInfo = client_subscribe::select('openid')->where('user_id', $uid)->where('status', 1)->first();
      if(!empty($subscribeInfo)){
        $openid = $subscribeInfo['openid'];
      }
      if(!$openid){
        return null;
      }

      $token = $this->msg_access_token(1);
      $api = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=$token";

      $params['touser'] = $openid;

      $params['template_id'] = $templateId;
      $params['data'] = $msg;
      if (!empty($url)) {
        $params['url'] = $url;
      }

      $result = $this->postCurl($api, $params, 'json');
      var_dump($subject . json_encode($result));
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
      $key = 'send_access_token';
      $re = Redis::exists($key);
      if ($re) {
        return Redis::get($key);
      } else {
        //公众号
        $appid = config("wxxcx.wechat_subscribe_appid");
        $appsecret = config("wxxcx.wechat_subscribe_appsecret");

        $action_url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$appsecret}";
        $outopt = file_get_contents($action_url);
        $data = json_decode($outopt, True);
        Redis::setex($key, 7000, $data['access_token']);
        return $data['access_token'];
      }
    }

}
