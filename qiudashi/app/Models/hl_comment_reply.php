<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
class hl_comment_reply extends Model
{
    /*
     * 评论回复表
     */
    protected $connection = 'mysql_origin';
    protected $table = 'hl_comment_reply';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $guarded = [];
    /*
     * 回复点赞数量+1
     */
    public static function replyIncrement($id){
        return self::where('id',$id)->increment('prase_count', 1);
    }

    /*
    * 回复点赞数量-1
    */
    public static function replyDecrement($id){
        return self::where('id',$id)->decrement('prase_count', 1);
    }

  /*
  * 修改操作
  * $id 评论id
  * $data[] 修改的内容
  */
    public static function updateReply($id,$data){
        return self::where('id',$id)->update($data);
    }

    /*
     * 根据评论id 修改
     * comment_id
     * data[] 修改内容
     */
    public static function updateCommentId($comment_id,$data){
        return self::where('comment_id',$comment_id)->update($data);
    }
}
