<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;


class hl_match_schedule extends Model
{
    //
    protected $connection = 'mysql_origin';
    protected $table = 'hl_match_schedule';
    public $timestamps = false;

    public function __construct() {

    }

    /*
     * 队伍信息
     */
    public function getMatchScheduleInfo($schedule_id,$match_type){

        $info= self::where(['schedule_id'=>$schedule_id,'match_type'=>$match_type])->first();
        if($info){
            $info=$info->toArray();
        }
        return $info;
    }


}
