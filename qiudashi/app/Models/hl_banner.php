<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Models\hl_news;

class hl_banner extends Model
{
    //
    protected $connection = 'mysql_origin';
    protected $table = 'hl_banner';
    public $timestamps = false;

    public function __construct() {
        $this->hl_news=new hl_news();
    }

    //查询
    public function getBannerList($where=[],$show_comment_model=1){

        $where['deleted']=0;
        if($where['platform']>0){
            $model=self::where($where)->whereIn('platform',[0,$where['platform']]);
            unset($where['platform']);
        }else{
            $model=self::where($where);
        }
        $data=$model->orderBy('sort','asc')->get()->toArray();
        foreach($data as &$v){
            if($v['type']==3){
                $v['show_comment_model']=$show_comment_model;//查询资讯模块控制
               // $info=$this->hl_news->getNewsInfo($v['oid']);
                //$v['comment']=$info['comment'];
            }
        }
        return $data;
    }


}
