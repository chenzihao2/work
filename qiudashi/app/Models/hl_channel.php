<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\UsersWechat;
use App\Models\UsersExpert;
use App\Models\UsersChannel;

use Illuminate\Support\Facades\DB;
use toolbox\net\FileDownload;

class hl_channel extends Model
{
    //
    protected $connection = 'mysql_origin';
    protected $table = 'hl_channel';
    protected $primaryKey = 'cid';
    public $timestamps = false;
    protected $guarded = [];


    /*
     * 写入渠道
     */

    public function addChannel($targetUser){
        $nowtime=time();
        $channelEntity = array(
            'target'      => isset($targetUser['target'])?$targetUser['target']:'',
            'platform'    => isset($targetUser['platform'])?$targetUser['platform']:'',
            'channel'     => isset($targetUser['channel'])?$targetUser['channel']:'',
            'pname'       => 'hl',
            'openId'   => isset($targetUser['openid'])?$targetUser['openid']:'',
            'unionId'  => isset($targetUser['unionid'])?$targetUser['unionid']:'',
            'nickname' => isset($targetUser['nick_name'])?$targetUser['nick_name']:'',
            'apple_nickname' => isset($targetUser['apple_nickname'])?$targetUser['apple_nickname']:'',
            'apple_id' => isset($targetUser['apple_id'])?$targetUser['apple_id']:'',
            'sex'      => isset($targetUser['sex'])?$targetUser['sex']:0,
            'avatar'   => isset($targetUser['headimgurl'])?$targetUser['headimgurl']:'',
            'mobile'   => isset($targetUser['phone'])?$targetUser['phone']:'',
            'birthday' => isset($targetUser['birthday'])?$targetUser['birthday']:'',
            'city'     => isset($targetUser['city'])?$targetUser['city']:'',
            'province' => isset($targetUser['province'])?$targetUser['province']:'',
            'country'  => isset($targetUser['country'])?$targetUser['country']:'',
            'ctime'    => $nowtime,
            'utime'    => $nowtime,
            'ip'       => isset($targetUser['ip'])?$targetUser['ip']:''
        );

        return self::insertGetId($channelEntity);
    }

    /*
     * 修改
     */
    public static function channelUpdate($cid,$data){
        return self::where('cid',$cid)->update($data);
    }

    /*
     * 当前渠道数据是否存在
     * $where=[]
     */

    public static function channelInfo($where){
        $info=self::where($where)->first();
        if($info){
            $info=$info->toArray();
        }
       return $info;
    }

}
