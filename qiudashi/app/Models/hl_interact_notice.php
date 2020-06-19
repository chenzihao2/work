<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
class hl_interact_notice extends Model
{
    /*
     * 通知表
     */
    protected $connection = 'mysql_origin';
    protected $table = 'hl_interact_notice';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $guarded = [];
    /*
       * 通知列表
     * to_uid 接收人id
   */
    public  function noticeList($to_uid,$page=1,$pagesize=20){
        $startPage=($page-1)*$pagesize;//开始记录

        $list= self::where('to_uid',$to_uid)->offset($startPage)->limit($pagesize)->get()->toArray();
        foreach($list as $v){

        }
    }




}
