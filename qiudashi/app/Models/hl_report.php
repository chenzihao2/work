<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
class hl_report extends Model
{
    /*
     * 评论表
     */
    protected $connection = 'mysql_origin';
    protected $table = 'hl_report';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $guarded = [];
    /*
     * 新增举报
     * $user_id 举报人id
     * $nick_name 举报人昵称
     * $to_user_id 被举报人id
     * $to_nick_name 被举报人昵称
     * $author_id 作者id
     * $topic_id 资源id
     * $topic_type 资源类型
     * $topic_type 资源类型
     * $report_type 举报类型
     * $reason 原因
     */
    public static function addReport($data){
        $times=date("Y-m-d H:i:s");
        $data['create_time']=$times;
        $data['update_time']=$times;
        return self::insertGetId($data);
    }

    /*
     * 举报列表
     */
    public static function reportList($where=[],$page=1,$pagesize=15){
        $model=self::where($where)->where('deleted',0)->whereIn('topic_type',[3,4]);
        $count=$model->count();
        $totalPage = ceil($count/$pagesize); //总页数
        $startPage=($page-1)*$pagesize;//开始记录
        $list=$model->offset($startPage)->orderBy('id','desc')->limit($pagesize)->get()->toArray();
        return ['list'=>$list,'totalCount'=>$count];
    }

    /*
     * 举报详情
     */
    public static function reportInfo($id){
        return self::where('id',$id)->first();
    }
    /*
     * 修改
     */
    public static function reportUpdate($id,$data){
        $data['update_time']=date('Y-m-d H:i:s');
        return self::where('id',$id)->update($data);
    }


}
