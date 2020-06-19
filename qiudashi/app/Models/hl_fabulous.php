<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
class hl_fabulous extends Model
{
    /*
     * 点赞表
     */
    protected $connection = 'mysql_origin';
    protected $table = 'hl_fabulous';
    /*
     * 是否已点赞
     * $user_id 用户id
     * $topic 主题id（文章id,视频id，评论id，被回复id）
     * $type类型 1：文章；2：视频；3：评论 4：回复
     * $is_reply 是否是回复：1：回复 作废
     * $comment_type 评论类型 1：文章；2：视频；(类型为评论时有效) 作废
     *返回 当前记录id
     */
    public static function isFabulous($user_id,$topic=0,$type=0,$comment_type=0,$is_reply=0){
            if(!$user_id || !$topic || !$type){
                return ['msg'=>'fail','fabulous_id'=>0,'is_fabulous'=>0];
            }
            $where[]=['user_id','=',$user_id];
            $where[]=['topic','=',$topic];
            $where[]=['type','=',$type];
            //if($type==3 && !$comment_type){
               // return ['msg'=>'fail','fabulous_id'=>0,'is_fabulous'=>0];
           // }
            //if($comment_type){
               // $where[]=['comment_type','=',$comment_type];
           // }
//            if($is_reply){
//                $where[]=['is_reply','=',$is_reply];
//            }
            $res=self::where($where)->value('id');
            return ['msg'=>"success",'fabulous_id'=>$res,'is_fabulous'=>$res?1:0];

    }

    /*
     * 写入点赞信息
     * $user_id 用户id
     * $topic 主题id（文章id,视频id，评论id，被回复id）
     * $type类型 1：文章；2：视频；3：评论 4：回复
     * $comment_type 评论类型 1：文章；2：视频；(类型为评论时有效) 作废
     */
    public static function addFabulous($user_id,$topic=0,$type=0,$comment_type=0,$is_reply=0){

            if(!$user_id || !$topic || !$type){
                return false;
            }
           /* if($type==3 && !$comment_type){
                return false;
            }*/
            $create_time=date('Y-m-d H:i:s');
            $where = [
                'user_id'=>$user_id,
                'topic'=>$topic,
                'type'=>$type,
            ];
            $exists = self::where($where)->first();
            if ($exists) {
                return true;
            }
            $data=array(
                'user_id'=>$user_id,
                'topic'=>$topic,
                'type'=>$type,
                'comment_type'=>$comment_type,
                //'is_reply'=>$is_reply,
                'create_time'=>$create_time,
                'update_time'=>$create_time,
            );
            return self::insertGetId($data);
    }

    /*
     * 取消点赞删除记录
     * $id 点赞id
     */
    public static function delFabulous($id){
        if(!$id){
            return false;
        }
        return self::where('id',$id)->delete();
    }

    /*
     * 根据资源查询是否点赞
     */
    public static function fabulousInfo($user_id,$type,$topic_id){
            $data=array(
                'user_id'=>$user_id,
                'topic'=>$topic_id,
                'type'=>$type,
            );
            $info=self::where($data)->first();
            if($info){
                $info=$info->toArray();
            }
            return $info;
    }





}
