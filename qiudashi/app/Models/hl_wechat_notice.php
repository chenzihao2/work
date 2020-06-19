<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
class hl_wechat_notice extends Model
{
    /*
     * 微信通知
     */
    protected $connection = 'mysql_origin';
    protected $table = 'hl_wechat_notice';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $guarded = [];
    /*
     * 添加 通知
     */
    public static function addNotice($data){
       return  self::insertGetId($data);
    }

    /*
     * 通知详情
     */
    public static function noticeInfo($id){
        return  self::where('id',$id)->first();
    }

    /*
     * 修改通知
     */

    public static function updateNotice($id,$data){
        return  self::where('id',$id)->update($data);
    }


    /*
     * 分页获取
     */
    public static function noticeLimit($where=[],$page=1,$pageSize=15){

        $count=self::where($where)->count();
        $totalPage = ceil($count/$pageSize); //总页数
        $startPage=($page-1)*$pageSize;//开始记录
        $list=self::where($where)->offset($startPage)->orderBy('id','desc')->limit($pageSize)->get()->toArray();
       return ['list'=>$list,'totalCount'=>$count];
    }



}
