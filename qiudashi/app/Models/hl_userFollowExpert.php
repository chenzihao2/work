<?php
/**
 * IDE Name: PhpStorm
 * Author  : zyj
 * DateTime: 2020-01-12 11:47:00
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class hl_userFollowExpert extends Model
{
    protected $connection = 'mysql_origin';
    protected $table = 'hl_user_follow_expert';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $guarded = [];

    /*
     * 批量关注专家
     * $user_id 用户id
     * $expert_ids 专家id
     */
    public static function batchFollowExpert($user_id,$expert_ids=[]){
        $times=time();
        $addArr=[];
        $updateArr=[];
        $expert=[];//未关注的专家

        foreach($expert_ids as $v){
            $res=self::where(['user_id'=>$user_id,'expert_id'=>$v])->first();
            if(!$res || $res['follow_status']==0){
                $expert[]=['to_uid'=>$res['expert_id'],'nick_name'=>'','headimgurl'=>''];
            }
            if($res && !$res['follow_status']){
                $updateArr[]=$v;
            }
            if(!$res){
                $addArr[]=['user_id'=>$user_id,'expert_id'=>$v,'follow_status'=>1,'create_time'=>$times];

            }
        }
        if($addArr){
            self::insert($addArr);
        }

        if($updateArr){
            self::where('user_id',$user_id)->whereIn('expert_id',$updateArr)->update(['follow_status'=>1,'create_time'=>$times]);
        }
        return $expert;
    }

    /*
     * 单个关注/取消关注
     */
    public static  function folowExpert($user_id,$expert_id){
        $info=self::where(['user_id'=>$user_id,'expert_id'=>$expert_id])->first();
        $times=time();
        $follow_status=1;
        if($info){
            if($info['follow_status']==1){
                $follow_status=0;
            }
            $res=self::where('id',$info['id'])->update(['follow_status'=>$follow_status,'create_time'=>$times]);
        }else{
            $res=self::insert(['user_id'=>$user_id,'expert_id'=>$expert_id,'follow_status'=>$follow_status,'create_time'=>$times]);
        }
        if(!$res){
            return false;
        }
        return true;
    }

}
