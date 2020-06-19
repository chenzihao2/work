<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\UsersWechat;
use App\Models\UsersExpert;
use App\Models\UsersChannel;

use Illuminate\Support\Facades\DB;
use toolbox\net\FileDownload;

class hl_user extends Model
{
    //
    protected $connection = 'mysql_origin';
    protected $table = 'hl_user';
    protected $dateFormat = 'U';
    public $timestamps = false;
    public static function getData() {
        //$data = self::first()
        $data = self::LeftJoin('hl_user_expert as e', 'hl_user.user_id', 'e.expert_id')
            ->LeftJoin('hl_user_subsidiary_wechat as w', 'hl_user.user_id', 'w.user_id')
            ->select('e.create_time as expert_create_time', 'e.modify_time as expert_modify_time','w.create_time as wechat_create_time','w.modify_time as wechat_modify_time', 'hl_user.*', 'e.*', 'w.*', 'hl_user.phone as phone', 'e.idcard_number as idcard_number', 'hl_user.user_id as user_id', 'hl_user.last_login_time as last_login_time')
            ->get()
            ->toArray();
        foreach ($data as $item) {
            self::dealData($item);
        }
        //$data_users = self::get();
        //foreach ($data_users as $users) {
        //    self::dealData($users, 1);
        //}
        return $data;
    }

    public static function modifyData($data) {
        $user_data['user_id'] = $data['user_id'];
        $user_data['identity'] = $data['identity'] == 3 ? 2 : 1; //
        User::updateUser($data['user_id'], $user_data);

    }


    public static function dealData($data, $user = 0) {
        $user_data = $wechat_data = $expert_data = [];
        $user_data['user_id'] = $data['user_id'];
        $user_data['cid'] = $data['cid'] ?: 0;
        $user_data['phone'] = $data['phone'] ?: '';
        $user_data['nick_name'] = $data['nick_name'] ?: '';
        $user_data['headimgurl'] = $data['headimgurl'] ?: '';
        $user_data['source'] = 1; //
        $user_data['identity'] = $data['identity'] == 3 ? 2 : 1; //
        $user_data['device_token'] = $data['device_token'] ?: '';
        $user_data['user_status'] = $data['user_status']  ?: 0;
        $user_data['forbidden_say'] = $data['forbidden_say']  ?: 0;
        $user_data['last_login_time'] = date('Y-m-d H:i:s', $data['last_login_time'] ?: 1580580122);//
        $user_data['created_at'] = date('Y-m-d H:i:s',$data['create_time'] ?: 1580580122);//
        $user_data['updated_at'] = date('Y-m-d H:i:s',$data['modify_time'] ?: 1580580122);//
        if (!$user_data['phone']) {
            $user_data['source'] = 2;
        }
        User::addUser($user_data);
        if ($user) {
            return;
        }

        $wechat_data['user_id'] = $data['user_id'];
        $wechat_data['unionid'] = $data['unionid'] ?: '';
        $wechat_data['openid'] = $data['openid'] ?: '';
        $wechat_data['nick_name'] = $data['nick_name'] ?: '';
        $wechat_data['headimgurl'] = $data['headimgurl'] ?: '';
        $wechat_data['sex'] = $data['sex'];
        $wechat_data['country'] = $data['country'] ?: '';
        $wechat_data['province'] = $data['province'] ?: '';
        $wechat_data['city'] = $data['city'] ?: '';
        $wechat_data['created_at'] = date('Y-m-d H:i:s',$data['wechat_create_time'] ?: 1580580122);
        $wechat_data['updated_at'] = date('Y-m-d H:i:s',$data['wechat_modify_time'] ?: 1580580122);
        UsersWechat::addWechat($wechat_data);

        $expert_data['user_id'] = $data['user_id'];
        $expert_data['expert_id'] = $data['expert_id'] ?: 0;
        $expert_data['real_name'] = $data['real_name'] ?: '';
        $expert_data['idcard_number'] = $data['idcard_number'] ?: '';
        $expert_data['desc'] = $data['desc'] ?: '';
        $expert_data['identity_desc'] = $data['identity_desc'] ?: '';
        $expert_data['tag'] = $data['tag'] ?: '';
        $expert_data['sort'] = $data['sort'] ?: 0;
        $expert_data['expert_status'] = $data['expert_status'] ?: 1;
        $expert_data['platform'] = $data['platform'] ?: 0;
        $expert_data['is_recommend'] = $data['is_recommend'] ?: 0;
        $expert_data['is_placement'] = $data['is_placement'] ?: 0;
        $expert_data['is_wx_recommend'] = $data['is_wx_recommend'] ?: 0;
        $expert_data['is_wx_placement'] = $data['is_wx_placement'] ?: 0;
        $expert_data['expert_type'] = $data['expert_type'] ?: 0;
        $expert_data['push_resource_time'] = date('Y-m-d H:i:s', $data['push_resource_time'] ?: 1580580122);
        $expert_data['created_at'] = date('Y-m-d H:i:s',$data['expert_create_time'] ?: 1580580122);
        $expert_data['updated_at'] = date('Y-m-d H:i:s',$data['expert_modify_time'] ?: 1580580122);
        UsersExpert::addExpert($expert_data);
        var_dump($data['user_id'] . 'done');
    }


    public static function userChannelData() {
        $data = self::LeftJoin('hl_channel as c', 'hl_user.cid', 'c.cid')
            ->where('hl_user.cid', '<>', 0)
            ->get()
            ->toArray();
        foreach ($data as $item) {
            $channel_data = [];
            $channel_data['cid'] = $item['cid'];
            $channel_data['user_id'] = $item['user_id'];
            $channel_data['target'] = strtolower($item['target']);
            $channel_data['platform'] = strtolower($item['platform']);
            $channel_data['channel'] = strtolower($item['channel']);
            $channel_data['created_at'] = date('Y-m-d H:i:s', $item['ctime'] ?: 1580580122);
            UsersChannel::addChannel($channel_data);
        }
    }


    //向老库写入注册信息
    public static function regUser($targetUser,$uuid=0){
        //$FaceUtility= new FaceUtility();

        $nowtime = time();
        $userEntity = array(
            'user_id'=>$targetUser['user_id'],
            'uuid'          => $uuid,
            'nick_name'     => $targetUser['nick_name'],
            'sex'           => $targetUser['sex'],
            'headimgurl'    => $targetUser['headimgurl'],
            'phone'         => $targetUser['phone'],
            'city'          => $targetUser['city'],
            'province'      => $targetUser['province'],
            'country'       => $targetUser['country'],
            'device_token' => $targetUser['device_token'],
            'source'        => 2,
            'identity'      => 1,
            'create_time'   => $nowtime,
            'modify_time'   => $nowtime,
            'last_login_time' => $nowtime,
            'last_login_ip' =>$targetUser['ip']
        );



        //老库微信用户表
        $channelEntity = array(
            'target'      => $targetUser['target'],
            'platform'    => $targetUser['platform'],
            'channel'     => $targetUser['channel'],
            'pname'       => 'hl',
            'openId'   => $targetUser['openid']?$targetUser['openid']:'',
            'unionId'  => $targetUser['unionid']?$targetUser['unionid']:'',
            'nickname' => $targetUser['nick_name'],
            'sex'      => $targetUser['sex']?$targetUser['sex']:0,
            'avatar'   => $targetUser['headimgurl']?$targetUser['headimgurl']:'',
            'mobile'   => $targetUser['phone'],
            'birthday' => $targetUser['birthday'],
            'city'     => $targetUser['city']?$targetUser['city']:'',
            'province' => $targetUser['province']?$targetUser['province']:'',
            'country'  => $targetUser['country']?$targetUser['country']:'',
            'ctime'    => $nowtime,
            'utime'    => $nowtime,
            'ip'       => $targetUser['ip']
        );


        if(isset($targetUser['cid'])){
            $channelEntity['cid']=$targetUser['cid'];
        }

        $resWechat=true;
        if($targetUser['target']=='wx'){
            $resWechat=self::weChatInfoCheck($targetUser['user_id'], $targetUser['openid'], $targetUser['unionid']);
            $existsChannel=DB::table('haoliao.hl_channel')->where('openid',$targetUser['openid'])->exists();

            if($existsChannel){
                return 2;
            }
        }

        $channelRes=DB::table('haoliao.hl_channel')->insertGetId($channelEntity);
        $userEntity['cid']=$channelRes;
        $existsUser=self::where('user_id',$targetUser['user_id'])->exists();
        $existsUserPhone=self::where('phone',$targetUser['phone'])->exists();

        if($existsUser || $existsUserPhone){
            return 2;
        }
        $userRes=self::insert($userEntity);

        if(!$userRes || !$channelRes || !$resWechat){
            return false;
        }
        return true;
    }

    //修改用户信息
    public static function updateHlUser($user_id,$data){
        $data['modify_time']=time();
        //return self::where('user_id',$user_id)->update($data);
        return DB::table('haoliao.hl_user')->where('user_id',$user_id)->update($data);
    }

    //修改channel 表
    public static function updateChannel($user_id,$data){

        $cid=self::where('user_id',$user_id)->value('cid');
        return DB::table('haoliao.hl_channel')->where('cid',$cid)->update($data);
    }



    //验证是否存在openid
    public static function weChatInfoCheck($uid, $openid, $unionId) {
        $model= DB::table('haoliao.hl_user_subsidiary_wechat');
        $weChatId=$model->where(['openid'=>$openid,'unionid'=>$unionId])->first();
        if ($weChatId) {
            $update['last_login_time'] = time();
            $res=$model->where('id',$weChatId->id)->update($update);

        } else {
            $weChatInfo['user_id'] = $uid;
            //$weChatInfo['wechat_id'] = $GLOBALS['weChatId'];
            $weChatInfo['wechat_id'] = 4;
            $weChatInfo['unionid'] = $unionId;
            $weChatInfo['openid'] = $openid;
            $weChatInfo['create_time'] = time();
            $weChatInfo['modify_time'] = time();
            $weChatInfo['last_login_time'] = time();
            $res=$model->insert($weChatInfo);
        }
        return $res;
    }


    /*
     * 根据查询条件获取用户信息
     */
    public static function userInfo($where){

       $info= self::where($where)->first();

       if($info){
           $info=$info->toArray();
           $info['headimgurl']=$info['headimgurl']?$info['headimgurl']:'https://hl-static.haoliao188.com/resource/202002/27/ca2ed48d3859896ff049903652c287db.png';
       }
       return $info;
    }


    /*
     * 写入用户
     */
    public static function addUser($targetUser,$uuid=0){
        $nowtime=time();
        $userEntity = array(
            'uuid'          => $uuid,
            'cid'          => isset($targetUser['cid'])?$targetUser['cid']:'',
            'nick_name'     => isset($targetUser['apple_nickname'])?$targetUser['apple_nickname']:$targetUser['nick_name'],
            'sex'           => isset($targetUser['sex'])?$targetUser['sex']:'',
            'headimgurl'    => isset($targetUser['headimgurl'])?$targetUser['headimgurl']:'',
            'phone'         => isset($targetUser['phone'])?$targetUser['phone']:'',
            'city'          => isset($targetUser['city'])?$targetUser['city']:'',
            'province'      => isset($targetUser['province'])?$targetUser['province']:'',
            'country'       => isset($targetUser['country'])?$targetUser['country']:'',
            'device_token' => isset($targetUser['device_token'])?$targetUser['device_token']:'',
            'source'        => 2,
            'identity'      => 1,
            'create_time'   => $nowtime,
            'modify_time'   => $nowtime,
            'last_login_time' => $nowtime,
            'last_login_ip' =>isset($targetUser['ip'])?$targetUser['ip']:''
        );
        return self::insertGetId($userEntity);
    }

    /*
     * 修改用户信息
     */
    public function userUpdate($user_id,$data){
        $data['modify_time']=time();
        return self::where('user_id',$user_id)->update($data);
    }



    /*
     * 查看用户禁言时间
     */
    public function getForbiddenDay($user_id){
        $userInfo=self::where('user_id',$user_id)->select('forbidden_day','forbidden_time')->first();
        if($userInfo){
            $userInfo=$userInfo->toArray();
        }
        $arr=['not_say'=>false,'msg'=>'','start_time'=>'','end_time'=>'','day'=>0,'hour'=>0,'minute'=>0,'second'=>0];
        if($userInfo['forbidden_day']==-1){
            $arr['not_say']=true;
            $arr['msg']='您被永久禁言';
            $arr['day']=-1;
            return $arr;
        }
        //$endTime=date("Y-m-d H:i:s",strtotime(date('Y-m-d H:i:s',$userInfo['forbidden_time']).' +'.$userInfo['forbidden_day'].' day'));//结束时间
        $endTime=strtotime(date('Y-m-d H:i:s',$userInfo['forbidden_time']).' +'.$userInfo['forbidden_day'].' day');//结束时间

        if($userInfo['forbidden_day']>0 && $endTime>time()){

            //结束时间-当前时间=时间差
            $diff = abs($endTime - time());
            //转换时间差的格式

            $years = floor($diff / (365*60*60*24));//年
            $months = floor(($diff - $years * 365*60*60*24)  / (30*60*60*24));//月
            $days = floor(($diff - $years * 365*60*60*24 -  $months*30*60*60*24)/ (60*60*24));//日
            $hours = floor(($diff - $years * 365*60*60*24   - $months*30*60*60*24 - $days*60*60*24)  / (60*60));//时
            $minutes = floor(($diff - $years * 365*60*60*24  - $months*30*60*60*24 - $days*60*60*24  - $hours*60*60)/ 60);//分
            $seconds = floor(($diff - $years * 365*60*60*24  - $months*30*60*60*24 - $days*60*60*24  - $hours*60*60 - $minutes*60));//秒
            //printf("相差：%d 年, %d 月, %d 日, %d 小时, %d 分, %d 秒", $years, $months, $days, $hours, $minutes, $seconds);

            $arr['start_time']=date('Y-m-d H:i:s',$userInfo['forbidden_time']);
            $arr['end_time']=date('Y-m-d H:i:s',$endTime);
            $arr['day']=$days;
            $arr['hour']=$hours;
            $arr['minute']=$minutes;
            $arr['second']=$seconds;
            $arr['not_say']=true;
            $arr['msg']='您被禁言到'.$arr['end_time'];
        }

        return $arr;

    }

}
