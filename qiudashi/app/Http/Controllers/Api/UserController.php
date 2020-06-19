<?php

namespace App\Http\Controllers\Api;

use App\Models\hl_channel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\UsersChannel;
use App\Models\LoginLog;
use App\Models\UsersWechat;
use App\Models\hl_user;
use App\Models\hl_user_balance;
use App\Models\hl_device_info;

use App\Respository\Jwt;
use App\Http\Controllers\Controller;
use App\Respository\UcloudSms;
use App\Respository\LoginRespository;
use App\Respository\FaceUtility;
use App\Comment\WxOauth;

class UserController extends Controller
{
    public function __construct() {
        //parent::__construct();
        //$this->middleware('auth')->except('test');
    }
    //
    //public function test(Request $request) {
    //    $user_info = $request->user_info;
    //    return $this->rtJson($user_info);
    //    return $this->rtJsonError(10001);
    //}

    //public function test1() {
    //    $result = User::first();
    //    var_dump(jwt::setUserToken($result));die;

    //}


    /*
     * 获取用户信息接口
     */
    public function userInfo(Request $request){
        $user_id = $request->input('user_id','');
        if(!$user_id){
            $user_id = $request->user_info['user_id'];
        }

        if(!$user_id){
            return $this->rtJsonError(102);
        }
        $user_info=hl_user::userInfo(['user_id'=>$user_id]);
        if($user_info){
            $user_info['wx']='';
            $user_info['apple_nickname']='';
            $user_info['apple_id']='';
            // $wechatInfo=UsersWechat::getWechatInfo(['user_id'=>$user_id]);
            $wechatInfo=hl_channel::channelInfo(['cid'=>$user_info['cid']]);
            if($wechatInfo){
                $user_info['wx']=$wechatInfo['nickname'];
            }
            if($wechatInfo){
                $user_info['apple_nickname']=$wechatInfo['apple_nickname'];
                $user_info['apple_id']=$wechatInfo['apple_id'];
            }
            $balance=hl_user_balance::getUserBalanceInfo($user_id);
            $user_info['vc_balance']=(new FaceUtility())->ncPriceFen2YuanInt($balance);
        }

        return $this->rtJson_($user_info);
    }


    /*
     * target mobile手机号登陆，wx:微信登陆,bind：手机号绑定
     * platform 平台
     * channel 渠道
     * mobile 手机号
     * mobile_code 验证码
     * device 设备号
     * code 微信登陆code
     */

    public function login(Request $request) {
        $target = $request->input('target', '');
        $platform = $request->input('platfrom', '');
        $channel = $request->input('channel', '');
        $phone = $request->input('mobile', '');
        $phone_code = $request->input('mobile_code', '');
        $device = $request->input('device', '');//手机IMEI
        $device_token = $request->input('device_token', '');//友盟

        if ($target == 'mobile') {
            $uc_sms = new UcloudSms();
            if (!$uc_sms->mobileCheck($phone)) {
                return $this->rtJsonError(1000201);
            }


            if (!$uc_sms->checkCode($phone, $phone_code)) {
                return $this->rtJsonError(1000101);
            }
            $existsUser=User::existsPhone($phone);
            //开启事务
            DB::connection('mysql_origin')->beginTransaction();
            DB::connection('mysql')->beginTransaction();
            $user_info = User::phoneLogin($phone);

            //注册
            if(!$existsUser){
                //向老数据库同步用户信息
                $oldUserData=$user_info;
                $oldUserData['phone']=$phone;

                $oldUserData['openid']=$user_info['id'];
                $oldUserData['unionid']=$user_info['id'];
                $oldUserData['sex']=0;

                // $oldUserData['cid']=$user_info['id'];
                $oldUserData['target']='mobile';
                $oldUserData['platform']=$platform;
                $oldUserData['channel']=$channel;
                $oldUserData['city']='';
                $oldUserData['province']='';
                $oldUserData['birthday']='';
                $oldUserData['device_token']=$device_token;
                $oldUserData['ip']=(new FaceUtility())->clientIpAddress();

                $hl_res=hl_user::regUser($oldUserData);

                if($hl_res===2){
                    DB::connection('mysql_origin')->rollback();  //回滚
                    DB::connection('mysql')->rollback();  //回滚
                    return $this->rtJsonError(1000109);
                }
            }
            //$token = Jwt::setUserToken($user_info);
            //写入渠道数据
            /*$channel_data = [
                'user_id' => $user_info['user_id'],
                'target' => strtolower($target),
                'platform' => strtolower($platform),
                'channel' => strtolower($channel),

            ];
            UsersChannel::addChannel($channel_data);
            */


            $this->insertChannel($user_info,$target,$platform,$channel,$device);
            DB::connection('mysql_origin')->commit();
            DB::connection('mysql')->commit();  //提交

            //最后登陆时间
            $this->lastLoginTime($user_info['user_id'],$device_token);

        }
        /*
        $token = $this->getOldToken($user_info['user_id'], $user_info['nick_name']);
        //$user_info['token'] = $token;
        LoginLog::doLog($user_info['user_id'], $channel);
        $response = $this->rtJson($user_info);
        return $response->withHeaders(['authorization' => $token]);
        */
        return $this->dologin($user_info,$channel);

    }


    /*
     * 微信登陆
     * target wx：微信登陆，bind 手机号绑定，bindwx 绑定微信
     */
    public function wxLogin(Request $request)
    {

        $target = $request->input('target', '');
        $target='wx';
        $platform = $request->input('platform', '');
        $channel = $request->input('channel', '');
        $device = $request->input('device', '');
        $device_token = $request->input('device_token', '');
        $code = $request->input('code', '');
        $rowData = $request->input('rowData', '');
        $user_id = $request->input('user_id', 0);

        $appid = "wx9cc12e1169da2064";
        $appsecret = "af0feedd603075237338cc8b1ad010e6";

        //ios
        $wechatUser = [];//安卓
        $wechatIosUser=[];//ios
        if ($code) {
            // 移动端使用
            $WxOauth = new WxOauth($appid, $appsecret); // 传入appid和appsecret

            // 通过移动端传来的code或者微信回调返回的code获取用户信息
            $wechatUser = $WxOauth->wxLogin($code);

            if ($wechatUser) {
                $openid = $wechatUser['openid'];
                $unionid = $wechatUser['unionid'];

            } else {
                // file_put_contents('logs.txt', $WxOauth->error);
                return $this->rtJsonError(1000103, '', $WxOauth->error);
            }

        } else {
            $wechatIosUser = json_decode($rowData, true);
            $openid = $wechatIosUser['openid'];
            $unionid = $wechatIosUser['unionid'];


        }


        //微信登陆
        if(!$user_id){
            //$wechatInfo = UsersWechat::existsWechat($openid);
            $wechatInfo = UsersWechat::existsUnWechat($unionid);
            //file_put_contents('logs.txt',json_encode($wechatInfo));die;

            //注册
            if (!$wechatInfo) {
                return $this->rtJsonError(1000102, '', ['rowData' => $wechatUser, 'is_reg' => 1]);
            }

            //修改最新头像和昵称
            $update = array(
                'nick_name' => isset($wechatIosUser['name']) ? $wechatIosUser['name'] : $wechatUser['nickname'],
                'headimgurl' => isset($wechatIosUser['iconurl']) ? $wechatIosUser['iconurl'] : $wechatUser['headimgurl'],
                'openid' => $openid,
            );
            UsersWechat::updateWechat($wechatInfo['id'], $update);
            //登陆
            $user_info = User::userInfo($wechatInfo['user_id']);

            if (!$user_info) {

                return $this->rtJsonError(1000108, '该账号数据有误');
            }
            //最后登陆时间
            $this->lastLoginTime($user_info['user_id'],$device_token);

            $user_info['is_reg'] = 0;
            if (!$user_info['phone']) {
                return $this->rtJsonError(1000102, '', ['rowData' => $wechatUser, 'is_reg' => $user_info['is_reg']]);
            }
            $this->insertChannel($user_info, $target, $platform, $channel, $device);
            return $this->dologin($user_info,$channel);
        }
        //绑定微信
        if($user_id){
            $wechatUserInfo=$wechatIosUser?$wechatIosUser:$wechatUser;
            if(empty($wechatUserInfo)){
                return $this->rtJsonError(1000103);
            }
            return $this->bindwx($user_id,$channel,$wechatUserInfo);
        }

    }

    /*
     * 绑定微信
     */
    public function bindwx($user_id,$channel,$wechatUser){

        $wechatInfo = UsersWechat::existsWechat($wechatUser['openid']);
        //$wechatInfo =UsersWechat::existsUnWechat($wechatUser['unionid']);
        //file_put_contents('logs.txt',json_encode($wechatInfo));
        $created_at=date('Y-m-d H:i:s');
        //开启事务
        DB::connection('mysql_origin')->beginTransaction();
        DB::connection('mysql')->beginTransaction();
        if(!$wechatInfo){
            $data=array(
                'user_id'=>$user_id,
                'unionid'=>$wechatUser['unionid'],
                'openid'=>$wechatUser['openid'],
                'nick_name'=>isset($wechatUser['name'])?$wechatUser['name']:$wechatUser['nick_name'],
                'headimgurl'=>isset($wechatUser['iconurl'])?$wechatUser['iconurl']:$wechatUser['headimgurl'],
                'sex'=>isset($wechatUser['gender'])?($wechatUser['gender'] == '男' ? 1 : 2):$wechatUser['sex'],
                'country'=>$wechatUser['country'],
                'province'=>$wechatUser['province'],
                'city'=>$wechatUser['city'],
                'created_at'=>$created_at,
                'updated_at'=>$created_at,
            );
            $userInfo=UsersWechat::insertWechat($data);//创建微信用户

            //修改老库微信信息
            $oldchannelData=array(
                'unionId'=>$wechatUser['unionid'],
                'openId'=>$wechatUser['openid'],
                'sex'=>$data['sex'],
                'country'=>$wechatUser['country'],
                'province'=>$wechatUser['province'],
                'city'=>$wechatUser['city'],
                'utime'=>time(),
            );
            $oldchannel=hl_user::updateChannel($user_id,$oldchannelData);

            if($userInfo && $oldchannel){
                DB::connection('mysql_origin')->commit();
                DB::connection('mysql')->commit();  //提交
                $user_info=User::userInfo($user_id);
                $user_info['is_reg']=0;
                return $this->dologin($user_info,$channel);
            }else{
                DB::connection('mysql_origin')->rollback();  //回滚
                DB::connection('mysql')->rollback();  //回滚
                return $this->rtJsonError(1000106);
            }
        }else{
            return $this->rtJsonError(1000110);
        }

    }



    /*
     * 微信和手机号绑定
     */
    public function bind(Request $request){

        $target = $request->input('target', '');
        $platform = $request->input('platform', '');
        $channel = $request->input('channel', '');
        $phone = $request->input('mobile', '');
        $phone_code = $request->input('mobile_code', '');
        $rowData = $request->input('rowData', '');
        $device = $request->input('device', '');
        $device_token = $request->input('device_token', '');
        $uc_sms = new UcloudSms();
        if (!$uc_sms->mobileCheck($phone)) {
            return $this->rtJsonError(1000201);
        }

        if (!$uc_sms->checkCode($phone, $phone_code)) {
            return $this->rtJsonError(1000101);
        }
        if(!$rowData){
            return $this->rtJsonError(1000107);
        }
        $phoneUser=User::existsPhone($phone);

        if($phoneUser){
            return $this->rtJsonError(1000104);
        }

        $wechatUser = json_decode($rowData, true);

        $openid=$wechatUser['openid'];
        $unionid=$wechatUser['unionid'];
        //  $wechatInfo=UsersWechat::existsWechat($openid);
        $wechatInfo =UsersWechat::existsUnWechat($unionid);
        //开启事务
        DB::connection('mysql_origin')->beginTransaction();
        DB::connection('mysql')->beginTransaction();
        //注册
        if(!$wechatInfo){
            $wechatUser = json_decode($rowData, true);
            $created_at=date('Y-m-d H:i:s');
            $data=array(
                'unionid'=>$wechatUser['unionid'],
                'openid'=>$wechatUser['openid'],
                'nick_name'=>isset($wechatUser['name'])?$wechatUser['name']:$wechatUser['nickname'],
                'headimgurl'=>isset($wechatUser['iconurl'])?$wechatUser['iconurl']:$wechatUser['headimgurl'],
                'sex'=>isset($wechatUser['gender'])?($wechatUser['gender'] == '男' ? 1 : 2):$wechatUser['sex'],
                'country'=>$wechatUser['country'],
                'province'=>$wechatUser['province'],
                'city'=>$wechatUser['city'],
                'created_at'=>$created_at,
                'updated_at'=>$created_at,
            );
            //用户表数据
            $userDdata['nick_name']=$data['nick_name'];
            $userDdata['phone']=$phone;
            $userDdata['headimgurl']=$data['headimgurl'];
            $userDdata['source']=2;
            $userDdata['created_at']=$created_at;
            $userDdata['device_token']=$device_token;


            $user_id=User::createUser($userDdata);//创建用户
            $data['user_id']=$user_id;

            $userInfo=UsersWechat::insertWechat($data);//创建微信用户
            //向老数据库同步用户信息
            $oldUserData=$data;
            $oldUserData['phone']=$phone;
            $oldUserData['cid']=$userInfo['id'];
            $oldUserData['target']='wx';
            $oldUserData['platform']=$platform;
            $oldUserData['channel']=$channel;
            $oldUserData['birthday']='';
            $oldUserData['device_token']=$device_token;
            $oldUserData['ip']=(new FaceUtility())->clientIpAddress();
            $hl_res= hl_user::regUser($oldUserData);

            if($hl_res===2){
                DB::connection('mysql_origin')->rollback();  //回滚
                DB::connection('mysql')->rollback();  //回滚
                return $this->rtJsonError(1000109);
            }
            $is_reg=1;
            if(!$user_id || !$userInfo || $hl_res!==true){
                DB::connection('mysql_origin')->rollback();  //回滚
                DB::connection('mysql')->rollback();  //回滚
                return $this->rtJsonError(1000106);
            }


        }else{

            //绑定手机号
            $user_id=$wechatInfo['user_id'];
            $data['phone']=$phone;
            $data['updated_at']=date('Y-m-d H:i:s');
            $resUpdate=User::updateUser($user_id,$data);
            //老库同步
            $olduser=hl_user::updateHlUser($user_id,['phone'=>$phone]);
            $oldchannel=hl_user::updateChannel($user_id,['mobile'=>$phone]);

            $is_reg=0;
            if(!$resUpdate || !$olduser || !$oldchannel){
                DB::connection('mysql_origin')->rollback();  //回滚
                DB::connection('mysql')->rollback();  //回滚
                return $this->rtJsonError(1000106);
            }
        }
        DB::connection('mysql_origin')->commit();
        DB::connection('mysql')->commit();  //提交
        //登陆
        $user_info=User::userInfo($user_id);
        $user_info['is_reg']=$is_reg;
        $this->insertChannel($user_info,$target,$platform,$channel,$device);//创建注册渠道
        //最后登陆时间
        $this->lastLoginTime($user_info['user_id'],$device_token);
        return $this->dologin($user_info,$channel);
    }






    /*
     * 获取登陆用户信息
     */
    public function dologin($user_info,$channel){
        $user_info['wx']='';
        $wechatInfo=UsersWechat::getWechatInfo(['user_id'=>$user_info['user_id']]);
        if($wechatInfo){
            $user_info['wx']=$wechatInfo['nick_name'];
        }
        $balance=hl_user_balance::getUserBalanceInfo($user_info['user_id']);
        $user_info['vc_balance']=(new FaceUtility())->ncPriceFen2YuanInt($balance);
        $token = $this->getOldToken($user_info['user_id'], $user_info['nick_name']);
        LoginLog::doLog($user_info['user_id'], $channel);
        $response = $this->rtJson_($user_info);
        return $response->withHeaders(['authorization' => $token]);

    }




    /*
     * 友盟device_token
     */
    public function updateDevice(Request $request){
        $device_token=$request->input('device_token');
        $user_id=$request->input('user_id');
        if($device_token && $user_id){
            (new hl_user())->userUpdate($user_id,['device_token'=>$device_token]);
        }
        return $this->rtJson();
    }

    /*
     * 修改最后登陆时间
     */

    public function lastLoginTime($user_id,$device_token=''){
        $lastTime['last_login_time']=date('Y-m-d H:i:s');
        //修改友盟token
        if($device_token){
            $lastTime['device_token']=$device_token;
        }

        if($user_id){
           // $ymRes= User::updateUser($user_id,$lastTime);//新库
            $lastTime['last_login_time']=time();
            $olduser=hl_user::updateHlUser($user_id,$lastTime);//老库
        }
    }


    /*
     * 写入渠道数据
     * $user_info 用户信息
     * $target 标签
     * $platform 平台
     * $channel 渠道
     * $device 版本号
     */

    public function insertChannel($user_info,$target,$platform,$channel,$device){
        $channel_data = [
            'user_id' => $user_info['user_id'],
            'target' => strtolower($target),
            'platform' => strtolower($platform),
            'channel' => strtolower($channel),
            'is_register'=>(isset($user_info['is_reg'])&&$user_info['is_reg']==1)?1:0,
            'device'=>$device,
        ];
        $res=UsersChannel::insertChannel($channel_data);
        //新注册的用户 绑定一下渠道
        if($user_info['is_reg']==1){
            User::updateUser($user_info['user_id'],['cid'=>$res,'updated_at'=>date('Y-m-d H:i:s')]);
        }
        return $res;
    }



    public function sendMessage(Request $request) {
        $phone = $request->input('mobile', '');
        try{
            $uc_sms = new UcloudSms();
            $uc_sms->sendCode($phone);
        } catch (\Exception $e) {
            $code = $e->getcode();
            return $this->rtJsonError($code);
        }
        return $this->rtJson();
    }






    /*
     * 设备信息
     */

    public function deviceInfo(Request $request){
        $param = $request->all();
        $user_info = $request->user_info;
        $param['user_id'] = $user_info['user_id'];
        $param['Address']=(new FaceUtility())->clientIp();

        //根据ip 获取城市
        $url= "https://apis.map.qq.com/ws/location/v1/ip?ip={$param['Address']}&key=AO6BZ-7HV6W-26IRA-RWAZB-DYIO5-O6B3P";
        $locationInfo=(new FaceUtility())->httpRequestOnce($url);

        $locationInfo=json_decode($locationInfo,true);
        if($locationInfo['status']==0){
            $param['country']=$locationInfo['result']['ad_info']['nation'];
            $param['province']=$locationInfo['result']['ad_info']['province'];
            $param['city']=$locationInfo['result']['ad_info']['city'];
            $param['district']=$locationInfo['result']['ad_info']['district'];


        }
        hl_device_info::addDeviceInfo($param);
        return $this->rtJson_();
    }

}
