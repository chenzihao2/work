<?php
/**
 * 查询用户是否关注公众号
 * User: zyj
 * Date: 2019/10/25
 */
namespace App\Console\Commands;


use App\models\client_subscribe;
use App\models\client_log;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class subscribe extends Command {

    protected $signature = 'subscribe';

    protected $description = 'subscribe';

    public function __construct() {
        parent::__construct();
    }
	public function handle() {
      $this->subscribe();
    }

    public function subscribe(){
        $token = $this->msg_access_token(2);
        //client_subscribe 表
        $count = client_subscribe::select('openid')->where('status', 1)->where('subscribe',1)->count();
        //$count = client_subscribe::select('openid')->count();
		$pageSize=50;
        $to_pages=ceil($count/$pageSize);
		
		$to_p='to_p';
        $re = Redis::exists($to_p);
        if(!$re){
            $start=1;
        }else{
            $start=(int)Redis::get($to_p);
			
        }
		
		
		$t=false;
        for($i=$start;$i<$to_pages;$i++){
			 $startPage=($i-1)*$pageSize;//开始记录
             //$subscribeInfo = client_subscribe::select('openid','user_id')->offset($startPage)->limit(50)->orderBy('user_id','desc')->get();
           $subscribeInfo = client_subscribe::select('openid','user_id')->where('status', 1)->where('subscribe',0)->offset($startPage)->limit($pageSize)->orderBy('user_id','desc')->get();
            if(!empty($subscribeInfo)){
                foreach($subscribeInfo as $v){
                    $openid = $v['openid'];
					
                    $subscribe_msg = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=$token&openid=$openid";
                    $subscribe = json_decode(file_get_contents($subscribe_msg));
					
					
					//file_put_contents('subscribe.txt',file_get_contents($subscribe_msg));
					if(isset($subscribe->errcode)&&$subscribe->errcode==42001){
						//$token = $this->msg_access_token(2);
						$t=true;
						break;
					}
					
                    $gzxx = isset($subscribe->subscribe)?$subscribe->subscribe:0;
					if(!isset($subscribe->subscribe)){
						dump($subscribe);
						continue;
					}
					
					
                    //dump($subscribe);
                    //已关注
                    if($gzxx==1){
                        //执行修改操作
						//if($res){
						//	$str=$v['user_id'].' 更新成功 ';
						//}else{
							$str=$v['user_id'].' 已关注 ';
						//}
						
						dump($str);
						
                    }else{
						$res=client_subscribe::where('user_id',$v['user_id'])->update(['subscribe'=>0]);
						dump($v['user_id'].'未关注');
					}

                }

            }
			echo '第'.$i.'页<br>';
			Redis::set($to_p,$i);
        }
		if($t){
			$this->subscribe();
			die;
		}
		  Redis::set($to_p,1);
		dump('执行完毕');

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

    public function check_subscribe($openid, $user_id) {
        $is_subscribe = 0;
        $token = $this->msg_access_token(2);
        $subscribe_msg = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=$token&openid=$openid";
        $subscribe = json_decode(file_get_contents($subscribe_msg));
        $is_subscribe = isset($subscribe->subscribe) ? $subscribe->subscribe : 0;
        if ($is_subscribe) {
            client_subscribe::where('user_id', $user_id)->update(['subscribe'=> 1]);
        }
        return $is_subscribe;
    }

    public function msg_access_token($type = 1) {
        if ($type == 1) {
            $key = 'xcx_access_token';
        } else {
            $key = 'send_access_token';

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
                $appid = config("wxxcx.wechat_subscribe_appid");
                $appsecret = config("wxxcx.wechat_subscribe_appsecret");

                //$key = 'gzh_access_token_subscribe';
            }
            $action_url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$appsecret}";
            $outopt = file_get_contents($action_url);
            $data = json_decode($outopt, True);
            Redis::setex($key, 7000, $data['access_token']);
            return $data['access_token'];
        }
    }


}
