<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Models\hl_match_schedule;
use App\Models\hl_match_team;
use App\Models\hl_soccer_match;
use App\Models\hl_basketball_match;

class hl_resource_record extends Model
{
    //
    protected $connection = 'mysql_origin';
    protected $table = 'hl_resource_record';
    protected $resource_table = 'hl_resource';
    public $timestamps = false;
    private $play_method = [1 => '主队', 2 => '主队', 3 => '大小分'];
    public function __construct() {
        $this->hl_match_schedule=new hl_match_schedule();//比赛日程
        $this->hl_soccer_match=new hl_soccer_match();//足球赛程
        $this->hl_basketball_match=new hl_basketball_match();//篮球赛程
        $this->hl_match_team=new hl_match_team();//队伍
    }
    /*
     * 修改历史纪录
     */
    public function getRecord($resource_id){
        $query_builder =self::where('eu.resource_id',$resource_id)->select(['eu.*','r.title']);
        $query_builder->from($this->table . ' as eu');
        $query_builder->leftJoin($this->resource_table . ' as r', 'r.resource_id', '=', 'eu.resource_id');
        $list=$query_builder->orderBy('eu.id','desc')->get()->toArray();
        foreach($list as &$v){
            $v['date'] ='';
            $v['time'] ='';
            $v['content']='';
            $v['host_team']=[];
            $v['guest_team']=[];
            if($v['schedule_id']){

               // $scheduleInfo=$this->hl_match_schedule->getMatchScheduleInfo($v['schedule_id'],$v['type']);
                if($v['type']==1){
                    $matchInfo=$this->hl_soccer_match->getMathInfo($v['schedule_id']);
                }
                if($v['type']==2){
                    $matchInfo=$this->hl_basketball_match->getMathInfo($v['schedule_id']);
                    $v['play_method_text'] = $this->play_method[$v['d']];
                    $v['play_method'] = $v['d'];
                    unset($v['d']);
                }
                $teamNum=[$matchInfo['host_team'],$matchInfo['guest_team']];
                $teamList=$this->hl_match_team->getMatchTeamList($teamNum,$v['type']);
                $teamList = array_column($teamList, null, 'team_num');
                $time = strtotime($matchInfo['date']);
                $v['date'] = date('Y-m-d', $time);
                $v['time'] = date('H:i', $time);
                $v['host_team']=$teamList[$matchInfo['host_team']];
                $v['guest_team']=$teamList[$matchInfo['guest_team']];
            }
        }
        return $list;
    }

    /*
     * 是否已记录
     */
    public function existsRecord($where){
        $info=self::where($where)->first();
        if($info){
            $info=$info->toArray();
        }
        return $info;
    }

    /*
     * 修改记录数据入库
     */
    public function addRecord($param){


        $data=array(
            'resource_id'=>$param['resource_id'],
            'content'=>$param['content'],
            'league_id'=>isset($param['league_id'])?$param['league_id']:0,
            'schedule_id'=>isset($param['schedule_id'])?$param['schedule_id']:0,
            'type'=>isset($param['type'])?$param['type']:0,
            'lottery_type'=>isset($param['lottery_type'])?$param['lottery_type']:0,
            'lottery_id'=>isset($param['lottery_id'])?$param['lottery_id']:0,
            'h'=>isset($param['h'])?$param['h']:'',
            'w'=>isset($param['w'])?$param['w']:'',
            'd'=>isset($param['d'])?$param['d']:'',
            'l'=>isset($param['l'])?$param['l']:'',
            'recommend'=>isset($param['recommend'])?$param['recommend']:'',
            'status'=>$param['status'],
            'utime'=>date("Y-m-d H:i:s",time()),
        );
        $where=$data;
        unset($where['utime']);
        unset($where['status']);
        $exists=$this->existsRecord($data);
        if(!$exists){
            self::insert($data);
        }
    }

}
