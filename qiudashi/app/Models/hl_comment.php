<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
class hl_comment extends Model
{
    /*
     * 评论表
     */
    protected $connection = 'mysql_origin';
    protected $table = 'hl_comment';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $guarded = [];
    /*
   * 回复点赞数量+1
   */
    public static function commentIncrement($id){
        return self::where('id',$id)->increment('prase_count', 1);
    }

    /*
    * 回复点赞数量-1
    */
    public static function commentDecrement($id){
        return self::where('id',$id)->decrement('prase_count', 1);
    }

    /*
     * 修改操作
     * $id 评论id
     * $data[] 修改的内容
     */
    public static function updateComment($id,$data){
        return self::where('id',$id)->update($data);
    }


}
