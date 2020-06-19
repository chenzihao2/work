<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;


class hl_match_team extends Model
{
    //
    protected $connection = 'mysql_origin';
    protected $table = 'hl_match_team';
    public $timestamps = false;

    public function __construct() {

    }

    /*
     * 单个队伍信息
     */
    public function getMatchTeam($team_num,$type){

        $info= self::where(['team_num'=>$team_num,'type'=>$type])->select('team_num','name','short_name','logo')->first();
        if($info){
            $info=$info->toArray();
        }
        return $info;
    }

    /*
     * 队伍列表
     */
    public function getMatchTeamList($where,$type){
        $info= self::where(['type'=>$type]);
        if($where){
            $info->whereIn('team_num',$where);
        }
        return $info->select('team_num','name','short_name','logo')->get()->toArray();
    }


}
