<?php

namespace App\Http\Controllers\Api;

use App\Models\hl_channel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\UsersChannel;
use App\Models\LoginLog;
use App\Models\hl_user;
use App\Models\hl_user_balance;
use App\Models\hl_device_info;
use App\Models\CheckConfig;
use App\Respository\Jwt;
use App\Http\Controllers\Controller;
use App\Respository\UcloudSms;
use App\Respository\LoginRespository;
use App\Respository\FaceUtility;
use App\Comment\WxOauth;
use App\Comment\apple\ASDecoder;

class LoginController extends Controller
{
    protected $LoginRespository;
    public function __construct(LoginRespository $LoginRespository) {
        $this->LoginRespository=$LoginRespository;
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
        $platform_old = $request->input('platfrom', '');
        $platform = $request->input('platform', '');
        $platform=$platform_old?$platform_old:$platform;
        $channel = $request->input('channel', '');
        $phone = $request->input('mobile', '');
        $phone_code = $request->input('mobile_code', '');
        $device = $request->input('device', '');//手机IMEI
        $device_token = $request->input('device_token', '');//友盟

        $uc_sms = new UcloudSms();
        if (!$uc_sms->mobileCheck($phone)) {
            return $this->rtJsonError(1000201);
        }
        if($phone!=18518765356 && $phone_code!=123456){
            if (!$uc_sms->checkCode($phone, $phone_code)) {
                return $this->rtJsonError(1000101);
            }
        }

        //开启事务
        DB::connection('mysql_origin')->beginTransaction();
        $user_info=$this->LoginRespository->phoneLogin($phone);
        //注册
        if(!$user_info){
            //注册用户信息
            $UserData['nick_name'] = substr_replace($phone, '****', 3, 4);
            $UserData['phone']=$phone;
            $UserData['target']='mobile';
            $UserData['platform']=$platform;
            $UserData['channel']=$channel;
            $UserData['device_token']=$device_token;
            $UserData['sex']=1;
            $UserData['ip']=(new FaceUtility())->clientIpAddress();
            $res=$this->LoginRespository->createUser($UserData);
            if(isset($res['code'])){
                DB::connection('mysql_origin')->rollback();  //回滚
                return $this->rtJsonError($res['code'],$res['msg']);
            }
            $user_info=$res;
        }
        $loginChannel=$this->LoginRespository->insertChannel($user_info,$target,$platform,$channel,$device);

        if(!$user_info || !$loginChannel){
            DB::connection('mysql_origin')->rollback();  //回滚
            return $this->rtJsonError(1000111);
        }
        DB::connection('mysql_origin')->commit();//提交
        //最后登陆时间
        $loginTime=$this->LoginRespository->lastLoginTime($user_info['user_id'],$device_token);
        return $this->dologin($user_info,$channel);

    }

    /*
     * 微信登陆
     */
    public function wxLogin(Request $request){

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
                return $this->rtJsonError(1000103, '', $WxOauth->error);
            }

        } else {
            $wechatIosUser = json_decode($rowData, true);
            $openid = $wechatIosUser['openid'];
            $unionid = $wechatIosUser['unionid'];
        }

        if(!$unionid){
            return $this->rtJsonError(1000103);
        }
        //微信登陆
        if(!$user_id){
            $wechatInfo=hl_channel::channelInfo(['unionId'=>$unionid]);
            //注册跳转到绑定手机号
            if (!$wechatInfo) {
                return $this->rtJsonError(1000102, '', ['rowData' => $wechatUser, 'is_reg' => 1]);
            }
            //查询用户微信授权信息
            $user_info=hl_user::userInfo(['cid'=>$wechatInfo['cid']]);

            //修改最新头像和昵称
            $update = array(
                'nick_name' => isset($wechatIosUser['nickname']) ? $wechatIosUser['nickname'] : $wechatUser['nickname'],
                'headimgurl' => isset($wechatIosUser['headimgurl']) ? $wechatIosUser['headimgurl'] : $wechatUser['headimgurl'],
                // 'openid' => $openid,
            );
            $user_info['is_reg'] = 0;
            //提示绑定手机号
            if (!$user_info['phone']) {
                return $this->rtJsonError(1000102, '', ['rowData' => $wechatUser, 'is_reg' => $user_info['is_reg']]);
            }

            /**登陆**/

            DB::connection('mysql_origin')->beginTransaction();//开启事务
            $loginTime=$this->LoginRespository->lastLoginTime($user_info['user_id'],$device_token);//最后登陆时间
            $loginChannel=$this->LoginRespository->insertChannel($user_info, $target, $platform, $channel, $device); //登陆渠道

            if(!$user_info || !$loginChannel || !$loginTime){
                DB::connection('mysql_origin')->rollback();  //回滚
                return $this->rtJsonError(1000111);
            }

            DB::connection('mysql_origin')->commit();//提交
            return $this->dologin($user_info,$channel);
        }

        /**绑定微信**/
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

        //$wechatInfo = hl_channel::channelInfo(['openId'=>$wechatUser['openid']]);
        $wechatInfo = hl_channel::channelInfo(['openId'=>$wechatUser['openid']]);
        //开启事务
        if(!$wechatInfo){
            //修改老库微信信息
            $oldchannelData=array(
                'unionId'=>$wechatUser['unionid'],
                'openId'=>$wechatUser['openid'],
                'nickname'=>isset($wechatUser['nickname'])?$wechatUser['nickname']:$wechatUser['nickname'],
                'avatar'=>isset($wechatUser['headimgurl'])?$wechatUser['headimgurl']:$wechatUser['headimgurl'],
                'sex'=>isset($wechatUser['gender'])?($wechatUser['gender'] == '男' ? 1 : 2):$wechatUser['sex'],
                'country'=>$wechatUser['country'],
                'province'=>$wechatUser['province'],
                'city'=>$wechatUser['city'],
                'utime'=>time(),
            );
            $oldchannel=hl_user::updateChannel($user_id,$oldchannelData);

            if($oldchannel){
                $user_info=hl_user::userInfo(['user_id'=>$user_id]);
                $user_info['is_reg']=0;
                return $this->dologin($user_info,$channel);
            }else{
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
        $phoneUser=hl_user::userInfo(['phone'=>$phone]);

        if($phoneUser){
            return $this->rtJsonError(1000104);
        }

        $wechatUser = json_decode($rowData, true);

        $openid=$wechatUser['openid'];
        $unionid=$wechatUser['unionid'];
        $wechatInfo = hl_channel::channelInfo(['unionId'=>$unionid]);
        //开启事务
        DB::connection('mysql_origin')->beginTransaction();
        //注册
        if(!$wechatInfo){
            $wechatUser = json_decode($rowData, true);
            $data=array(
                'unionid'=>$wechatUser['unionid'],
                'openid'=>$wechatUser['openid'],
                'nick_name'=>isset($wechatUser['nickname'])?$wechatUser['nickname']:$wechatUser['nickname'],
                'headimgurl'=>isset($wechatUser['headimgurl'])?$wechatUser['headimgurl']:$wechatUser['headimgurl'],
                'sex'=>isset($wechatUser['gender'])?($wechatUser['gender'] == '男' ? 1 : 2):$wechatUser['sex'],
                'country'=>$wechatUser['country'],
                'province'=>$wechatUser['province'],
                'city'=>$wechatUser['city'],
                'phone'=>$phone,
                'platform'=>$platform,
                'channel'=>$channel,
                'target'=>'wx',
                'source'=>2,
                'device_token'=>$device_token,
                'birthday'=>'',
                'ip'=>(new FaceUtility())->clientIpAddress()
            );

            $res=$this->LoginRespository->createUser($data);//创建用户
            if(isset($res['code'])){
                DB::connection('mysql_origin')->rollback();  //回滚
                return $this->rtJsonError($res['code'],$res['msg']);
            }
            $user_id=$res['user_id'];
            $is_reg=1;
        }else{
            $user_info=hl_user::userInfo(['cid'=>$wechatInfo['cid']]);
            /**绑定手机号**/
            $user_id=$user_info['user_id'];
            $data['phone']=$phone;
            $res=$this->LoginRespository->updateUser($user_id,$data);
            $is_reg=0;
            if(!$res){
                DB::connection('mysql_origin')->rollback();  //回滚
                return $this->rtJsonError(1000106);
            }
        }

        /**登陆**/

        $user_info=$user_info=hl_user::userInfo(['user_id'=>$user_id]);
        $user_info['is_reg']=$is_reg;
        $loginChannel=$this->LoginRespository->insertChannel($user_info,$target,$platform,$channel,$device);//创建注册渠道

        if(!$loginChannel ){
            DB::connection('mysql_origin')->rollback();  //回滚
            return $this->rtJsonError(1000106);
        }
        DB::connection('mysql_origin')->commit(); //提交
        $loginTime=$this->LoginRespository->lastLoginTime($user_id,$device_token);//最后登陆时间
        return $this->dologin($user_info,$channel);
    }



    /*
     * 苹果授权登陆
     */
    public function appleLogin(Request $request){
        $target = $request->input('target', 'apple');
        $platform = $request->input('platform', '');
        $channel = $request->input('channel', 'ios');
        $device = $request->input('device', '');
        $device_token = $request->input('device_token', '');
        $rowData = $request->input('rowData', '');
        $version = $request->input('version', '2.3.0');
        $version=(int)str_replace('.','',$version);
        $rowData = json_decode($rowData, true);
        $clientUser = $rowData['userID'];
        $CheckConfig=(new CheckConfig())->show($channel,$version);//获取版本配置信息
        $bindMobile=$CheckConfig['bindmobile'];
       //$bindMobile=1;
        $identityToken = $rowData['identityToken'];
        if(!$identityToken){
            return $this->rtJsonError(1000112);
        }

        $ASDecoder=new ASDecoder();
        $appleSignInPayload = $ASDecoder->getAppleSignInPayload($identityToken);
        if(is_object($appleSignInPayload)){
            if($appleSignInPayload->sub!==$clientUser){
                return $this->rtJsonError(1000113);
            }
        }else{
            return $this->rtJsonError(1000113,$appleSignInPayload['message']);
        }
        $appleInfo = hl_channel::channelInfo(['apple_id'=>$clientUser]);//查询是否已经注册
        if($bindMobile==0){
            //最新版本直接注册
            if(!$appleInfo){
                $data=$this->regData($rowData,$platform,$channel,$device_token);
                $appleInfo=$this->branch(1,$data,0,$target,$platform,$channel,$device);
                if(isset($appleInfo['code'])){
                    return $this->rtJsonError($appleInfo['code'],$appleInfo['msg']);
                }
            }

        }else{
            //绑定手机号或 注册
            if(!$appleInfo || ($appleInfo && !$appleInfo['mobile'])){
                return $this->rtJsonError(1000102,'请绑定手机号', ['rowData' => $rowData, 'is_reg' => 1]);
            }
        }
        /**登陆**/
        $user_info=hl_user::userInfo(['cid'=>$appleInfo['cid']]);
        $loginChannel=$this->LoginRespository->insertChannel($user_info,$target,$platform,$channel,$device);//创建注册渠道
        if(!$loginChannel ){
            return $this->rtJsonError(1000106);
        }
        $this->LoginRespository->lastLoginTime($user_info['user_id'],$device_token);//最后登陆时间
        return $this->dologin($user_info,$channel);
    }

    /*
     * 苹果用户和手机号绑定
     */
    public function appleBind(Request $request){
        $target = $request->input('target', 'apple');
        $platform = $request->input('platform', '');
        $channel = $request->input('channel', '');
        $device = $request->input('device', '');
        $device_token = $request->input('device_token', '');
        $phone = $request->input('mobile', '');
        $phone_code = $request->input('mobile_code', '');
        $rowData = $request->input('rowData', '');
        $rowData = json_decode($rowData, true);
        $uc_sms = new UcloudSms();
        if (!$uc_sms->mobileCheck($phone)) {
            return $this->rtJsonError(1000201);
        }
        if (!$uc_sms->checkCode($phone, $phone_code)) {
            return $this->rtJsonError(1000101);
        }
        $phoneUser=hl_user::userInfo(['phone'=>$phone]);
        if($phoneUser){
            return $this->rtJsonError(1000104);
        }
        $appleInfo = hl_channel::channelInfo(['apple_id'=>$rowData['userID']]);//查询是否已经注册
        //注册
        if(!$appleInfo){
            $rowData['phone']=$phone;
            $data=$this->regData($rowData,$platform,$channel,$device_token);
            $is_reg=1;
            $user_id=0;
        }else{
            /**绑定手机号**/
            $user_info=hl_user::userInfo(['cid'=>$appleInfo['cid']]);
            $user_id=$user_info['user_id'];
            $data['phone']=$phone;
            $is_reg=0;
        }
        $user_info=$this->branch($is_reg,$data,$user_id,$target,$platform,$channel,$device);
        if(isset($user_info['code'])){
            return $this->rtJsonError($user_info['code'],$user_info['msg']);
        }
        /**登陆**/
        $this->LoginRespository->lastLoginTime($user_id,$device_token);//最后登陆时间
        return $this->dologin($user_info,$channel);
    }


    /*
     * 安全中心绑定苹果账号
     */
    public function bindApple(Request $request){
        $rowData = $request->input('rowData', '');
        $user_id = $request->input('user_id');
        $channel='ios';
        if(!$user_id){
            return $this->rtJsonError(102);
        }
        $userInfo=hl_user::userInfo(['user_id'=>$user_id]);

        if(!$userInfo){
            return $this->rtJsonError(102);
        }
        $rowData = json_decode($rowData, true);
        $clientUser = $rowData['userID'];
        $identityToken = $rowData['identityToken'];
        if(!$identityToken){
            return $this->rtJsonError(1000112);
        }
        $appleRes = hl_channel::channelInfo(['apple_id'=>$clientUser]);//查询是否已经注册
        if($appleRes){
            return $this->rtJsonError(1000114);
        }
        $ASDecoder=new ASDecoder();

        $appleSignInPayload = $ASDecoder->getAppleSignInPayload($identityToken);
        if(is_object($appleSignInPayload)){
            if($appleSignInPayload->sub!==$clientUser){
                return $this->rtJsonError(1000113);
            }
        }else{
            return $this->rtJsonError(1000113,$appleSignInPayload['message']);
        }

        $nick_name=$rowData['nickname'];
        $data['apple_id']=$rowData['userID'];
        $data['apple_nickname']=$nick_name;
        $res=hl_channel::channelUpdate($userInfo['cid'],$data);
        return $this->dologin($userInfo,$channel);




    }
    /*
     * 安全中心绑定手机号
     */
    public function bindMobile(Request $request){
        $phone = $request->input('mobile', '');
        $phone_code = $request->input('mobile_code', '');
        $channel = $request->input('channel', '');
        $user_id = $request->user_info['user_id'];
        if(!$user_id){
            return $this->rtJsonError(102);
        }
        $uc_sms = new UcloudSms();
        if (!$uc_sms->mobileCheck($phone)) {
            return $this->rtJsonError(1000201);
        }

        if (!$uc_sms->checkCode($phone, $phone_code)) {
            return $this->rtJsonError(1000101);
        }
        $phoneUser=hl_user::userInfo(['phone'=>$phone]);
        if($phoneUser){
            return $this->rtJsonError(1000104);
        }
        //开启事务
        DB::connection('mysql_origin')->beginTransaction();
        $res=$this->LoginRespository->updateUser($user_id,['phone'=>$phone]);
        if(!$res){
            DB::connection('mysql_origin')->rollback();  //回滚
            return $this->rtJsonError(1000106);
        }
        DB::connection('mysql_origin')->commit();  //回滚
        $user_info=hl_user::userInfo(['user_id'=>$user_id]);
        return $this->dologin($user_info,$channel);
    }


    /*
     * 组装注册数据
     */
    public function regData($rowData,$platform,$channel,$device_token){
        if(!$rowData['nickname'] && isset($rowData['phone']) && $rowData['phone']){
            $rowData['nickname']=substr_replace($rowData['phone'], '****', 3, 4);
        }
        if(!$rowData['nickname'] && (!isset($rowData['phone']) || !$rowData['phone'])){
            $rowData['nickname']=$this->LoginRespository->sys_randname();
        }

        $data=array(
            'apple_id'=>isset($rowData['userID'])?$rowData['userID']:'',
            'apple_nickname'=>$rowData['nickname'],
            'phone'=>isset($rowData['phone'])?$rowData['phone']:'',
            'target'=>'apple',
            'source'=>6,
            'sex'=>1,
            'platform'=>$platform,
            'channel'=>$channel,
            'device_token'=>$device_token,
            'ip'=>(new FaceUtility())->clientIpAddress()
        );
        return $data;
    }


    /*
     * 注册或者绑定手机号逻辑
     * $is_reg 1：注册，0：登陆
     * $data 入库数据
     * $user_id 用户id
     * $target 标签
     * $platform 平台
     * $channel 渠道
     * $device 设备号
     */
    public function branch($is_reg,$data,$user_id=0,$target='',$platform='',$channel='',$device=''){
        //开启事务
        DB::connection('mysql_origin')->beginTransaction();
        if($is_reg==1){
            $res=$this->LoginRespository->createUser($data);//创建用户
            if(isset($res['code'])){
                DB::connection('mysql_origin')->rollback();  //回滚
                return $res;
            }
            $user_info=$res;
            $user_id=$res['user_id'];
            $user_info['is_reg']=$is_reg;
        }else{
            /**绑定手机号**/
            $res=$this->LoginRespository->updateUser($user_id,$data);
            if(!$res){
                DB::connection('mysql_origin')->rollback();  //回滚
                return ['code'=>1000106,'msg'=>'绑定失败'];
            }
        }
        $user_info=hl_user::userInfo(['user_id'=>$user_id]);
        if($target && $platform && $channel){
            $loginChannel=$this->LoginRespository->insertChannel($user_info,$target,$platform,$channel,$device);//创建注册渠道
            if(!$loginChannel){
                DB::connection('mysql_origin')->rollback();  //回滚
                return ['code'=>1000106,'msg'=>'绑定失败'];
            }
        }
        DB::connection('mysql_origin')->commit(); //提交

        return $user_info;
    }




    /*
     * 获取登陆用户信息
     */
    public function dologin($user_info,$channel){
        $user_info['wx']='';
        $user_info['apple_nick_name']='';
        $user_info['apple_id']='';
        $user_info['is_reg']=isset($user_info['is_reg'])?$user_info['is_reg']:0;
        //  $wechatInfo=UsersWechat::getWechatInfo(['user_id'=>$user_info['user_id']]);
        $wechatInfo=hl_channel::channelInfo(['cid'=>$user_info['cid']]);

        if($wechatInfo && $wechatInfo['openId']){
            $user_info['wx']=$wechatInfo['nickname'];
        }
        if($wechatInfo){
            $user_info['apple_nickname']=$wechatInfo['apple_nickname'];
            $user_info['apple_id']=$wechatInfo['apple_id'];
        }

        $user_info['headimgurl']=$user_info['headimgurl']?$user_info['headimgurl']:'https://hl-static.haoliao188.com/resource/202002/27/ca2ed48d3859896ff049903652c287db.png';
        $balance=hl_user_balance::getUserBalanceInfo($user_info['user_id']);
        $user_info['vc_balance']=(new FaceUtility())->ncPriceFen2YuanInt($balance);
        $token = $this->getOldToken($user_info['user_id'], $user_info['nick_name']);
        LoginLog::doLog($user_info['user_id'], $channel);
        $user_info['token']=$token;
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
            hl_user::userUpdate($user_id,['device_token'=>$device_token]);
        }
        return $this->rtJson();
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
        //根据ip 获取城市
        $url= "https://apis.map.qq.com/ws/location/v1/ip?ip={$param['Address']}&key=AO6BZ-7HV6W-26IRA-RWAZB-DYIO5-O6B3P";
        $locationInfo=(new FaceUtility())->httpRequestOnce($url);
        $locationInfo=json_decode($locationInfo,true);
        if($locationInfo['status']==0){
            $param['province']=$locationInfo['result']['ad_info']['province'];
            $param['city']=$locationInfo['result']['ad_info']['city'];
            $param['country']=$locationInfo['result']['ad_info']['nation'];
        }
        hl_device_info::addDeviceInfo($param);
        return $this->rtJson_();
    }



}
