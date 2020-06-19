<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;


class hl_basketball_match extends Model
{
    //
    protected $connection = 'mysql_origin';
    protected $table = 'hl_basketball_match';
    protected $basketball_match_detail = 'hl_basketball_match_detail';
    public $timestamps = false;

    public function __construct() {

    }
    /*
     * 修改足球赛事信息
     */
    public function updateBasketballMatch($math_num,$data){
        $data['utime']=date("Y-m-d H:i:s");
        return self::where('match_num',$math_num)->update($data);
    }


    /*
    * 获取赛事信息
    */
    public function getMathInfo($math_num){
        $info=self::where('match_num',$math_num)->first();
        if($info){
            $info=$info->toArray();
        }
        return $info;
    }

    /*
        * 获取赛事详情
        */
    public function getMathDetail($match_num){
        $info=self::from($this->basketball_match_detail)->where('match_num',$match_num)->first();
        if($info){
            $info=$info->toArray();
        }
        return $info;
    }
}
