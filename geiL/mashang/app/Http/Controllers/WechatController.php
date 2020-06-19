<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\extract;
use App\Clients;
use App\smsLog;
use Illuminate\Support\Facades\DB;

class WechatController extends Controller
{
    //微信公众号

    public function getLogin(Request $request)
    {
        // 根据code信息判断是否为回调
        $code = $request->input("code", "");

        $appid = config("wxxcx.wechat_appid");
        $appsecret = config("wxxcx.wechat_appsecret");

        if($code == "") {
            $redirect_uri = urlencode("https://yxapi.qiudashi.com/wechat/login");
            $response_type = 'code';
            $scope = 'snsapi_userinfo';
            $state = 'STATE';
            $url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$appid."&redirect_uri=".$redirect_uri."&response_type=".$response_type."&scope=".$scope."&state=".$state."#wechat_redirect";
            Header("Location:".$url);
        } else {
            $action_url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid={$appid}&secret={$appsecret}&code={$code}&grant_type=authorization_code";

            $res = $this->seedRequest($action_url);
            $json_obj = json_decode($res, true);
            print_r($json_obj);
            $access_token = $json_obj['access_token'];
            $openid = $json_obj['openid'];

            $get_user_info_url = 'https://api.weixin.qq.com/sns/userinfo?access_token='.$access_token.'&openid='.$openid.'&lang=zh_CN';

            $data = $this->seedRequest($get_user_info_url);
            $data_obj = json_decode($data, True);
            echo '<\hr>';
            print_r($data_obj);
        }


    }


    /**
     * 提现页面
     */
    public function getExtractList()
    {
        $data = Clients::select('clients.balance', 'extract.status', 'font_balance', 'in_balance', 'server_balance', 'extract.created_at', 'clients.mobile', 'profile.profit', 'clients.id')
            ->leftJoin("extract", 'clients.id', 'extract.uid')
            ->leftJoin("profile", 'clients.id', 'profile.uid')
            ->where("clients.id", 95)
            ->OrderBy("extract.id", 'desc')
            ->first();

        if($data['status'] == 0 || $data['status'] == 1) {
            $data['status'] = 1;    // 正在提现
        } else {
            $data['status'] = 0;    // 没有
        }

        return response()->json($data);

    }


    /**
     * 短信发送
     */
    public function postSeedSms(Request $request)
    {
        $uid = (int)$request->input("uid", "95");
        $mobile = (string)$request->input("mobile", "15710012821");
        $clients = Clients::where("id", $uid)->first();
        if( empty($clients) ) {
            $return['status'] = "10001";
            $return['message'] = "用户标识不存在";
            return response()->json($return);
        }
        if( $clients['mobile'] != "" ) {
            $mobile = $clients['mobile'];
        }

        $n = preg_match('#^13[\d]{9}$|^14[5,7]{1}\d{8}$|^15[^4]{1}\d{8}$|^17[0,6,7,8]{1}\d{8}$|^18[\d]{9}$#', $mobile) ? true : false;
        if( !$n ) {
            $return['status'] = "10001";
            $return['message'] = "请填写正确的手机号格式";
            return response()->json($return);
        }

        // 生成验证码
        $code = mt_rand(100000,999999);
        Redis::set('code_'.$uid, $code);
        Redis::expire('code_'.$uid, 1800);
        $content = '【给料小程序】提现验证码'.$code.'，首次提现免费！半小时内有效！为保障资金安全，请勿将验证码透露给他人。';
        $day = date("Y-m-d");

        $count = smsLog::where("uid", $uid)->where("created_at", 'like', $day.'%')->count();
        if( $count >= 3 ) {
            $return['status'] = "10002";
            $return['message'] = "每天只能发送三次短信， 请隔天发送";
            return response()->json($return);
        }

        $sms = $this->seed_sms($mobile, $content);

        // 短信记录添加
        $smsLog['uid'] = $uid;
        $smsLog['mobile'] = $mobile;
        $smsLog['content'] = $content;
        smsLog::create($smsLog);

        $return['status'] = '201';
        $return['message'] = '发送成功';
        return response()->json($return);

    }


    /**
     * 手机号添加， 提现添加
     */
    public function postExtractAdd(Request $request)
    {
        // 短信验证码判断
        $code = $request->input("code", "");
        $uid = $request->input("uid", "");
        if( empty($code) || empty($uid)) {
            $return['status'] = "10007";
            $return['message'] = "缺少参数用户标示或短信验证码";
            return response()->json($return);
        }

        $get_code = Redis::get("code_".$uid);
        if($code != $get_code) {
            $return['status'] = "10004";
            $return['message'] = "验证码不对";
            return response()->json($return);
        }

        //判断用户是否为第一次提现, 则修改手机号
        $clients = Clients::where("id", $uid)->first();
        if( $clients['mobile'] == "") {
            $mobile = $request->input("mobile");
            $n = preg_match('#^13[\d]{9}$|^14[5,7]{1}\d{8}$|^15[^4]{1}\d{8}$|^17[0,6,7,8]{1}\d{8}$|^18[\d]{9}$#', $mobile) ? true : false;
            if( !$n ) {
                $return['status'] = "10001";
                $return['message'] = "请填写正确的手机号格式";
                return response()->json($return);
            }
            Clients::where("id", $uid)->update(['mobile' => $mobile]);
        }

        if($clients['balance'] < 10) {
            $return['status'] = "10005";
            $return['message'] = "提现金额不足，最少为十元";
            return response()->json($return);
        }

        $repeat = Extract::where("status", 0)->orWhere("status", 1)->where('uid', $clients['id'])->orderBy("id", "desc")->first();
        if(count($repeat) > 0) {
            $return['status'] = "10006";
            $return['message'] = "已有正在提现的记录，请勿重复申请";
            return response()->json($return);
        }

        // 提现记录添加
        $balance = $clients['balance'];
        if( $balance > 2000){
            $balance = 2000;
        }

        $server = number_format($balance * 0.05, 2);    // 服务费
        $in = $balance - $server;   // 到手金额
        $extract['uid'] = $clients['id'];
        $extract['font_balance'] = $balance;
        $extract['in_balance'] = $in;
        $extract['server_balance'] = $server;
        $extract['status'] = '0';

        DB::beginTransaction();
        try{
            // 添加提现申请， 减去账户余额
            DB::table("extract")->insert($extract);
            DB::table("clients")->where("id", $clients['id'])->decrement('balance', $balance);
            DB::commit();
        }catch (Exception $e) {
            DB::rollback();
            throw $e;
        };
        $return['status'] = "201";
        $return['message'] = "提现成功";
        return response()->json($return, 201);
    }

    /**
     * 短信接口
     * zzq 11-09
     * @param string $mobile 手机号
     * @param string $content 发送内容
     * @return string rrid 返回的唯一标示
     */
    public function seed_sms($mobile, $content)
    {
        if( empty($mobile) or empty($content)) {
            return "参数不可为空";
        }
        $sn = "SDK-666-010-03413";
        $pwd = strtoupper(md5("SDK-666-010-03413392325"));
        $url = 'http://sdk.entinfo.cn:8061/webservice.asmx/mdsmssend?sn='.$sn.'&pwd='.$pwd.'&mobile='.$mobile.'&content='.$content.'&ext=&stime=&rrid=&msgfmt=';

        $opts = [
            'http' => [
                'method' => 'GET',
                'timeout' => 1,
            ]
        ];
        $cnt = 0;
        $rrid = False;
        while( $cnt < 3 && ($rrid = file_get_contents($url, false, stream_context_create($opts))) === False ) {
            $cnt++;
        }
        return $rrid;
    }


    /**
     * 发送请求
     */
    public function seedRequest($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }

}
