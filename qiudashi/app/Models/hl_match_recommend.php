<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Respository\FaceUtility;
use App\Models\hl_soccer_match;
use App\Models\hl_match_team;
class hl_match_recommend extends Model
{
    //
    protected $connection = 'mysql_origin';
    protected $table = 'hl_match_recommend';
    public $timestamps = false;

    public function __construct() {
        $this->Utility=new FaceUtility();
        $this->hl_soccer_match=new hl_soccer_match();
        $this->hl_match_team=new hl_match_team();
    }
    /*
         * 获取距离开赛 还有15分钟得数据
         */
    public function getMatch(){
        $startTime=date('Y-m-d H:i:s');
        $endTime=date('Y-m-d H:i:s',strtotime("+15 minute"));
        $where[]=['date','>=',$startTime];
        $where[]=['date','>=',$endTime];
        $list=$this->hl_soccer_match->getMatch($where);
        $data=[];
        foreach($list as $val){
                $matchInfo=$this->getMatchInfo($val['match_num']);
                if($matchInfo){
                    continue;
                }
                $url= 'https://d-api.haoliao188.com/index.php?platform=1&v=2&p=user&c=soccer&do=matchIndexs&indexs_type=2&match_num='.$val['match_num'];
                $indexs=$this->Utility->httpRequestOnce($url);
                //初始让球数 $initBalls   最后让球数 $endBalls  初始赔率 $initOdds  终盘赔率 $endOdds  博彩公司编号
                $initBalls= $indexs[0]['first'][1];
                $endBalls=$indexs[0]['now'][1];
                $initOdds=$indexs[0]['first'][0];
                $endOdds=$indexs[0]['now'][0];
                $res=$this->Utility->soccerAsiaIndexSvmClassifier($initBalls,$endBalls,$initOdds,$endOdds);
                $recommend_team='';
                $recommend_team_name='';
                $team_num=0;
                $forecast=0;//预判结果
                //上盘
                if($res[0]==1){
                    $team_num=$val['host_team'];
                    $forecast=1;
                }
                //下盘
                if($res[0]==-1){
                    $team_num=$val['guest_team'];
                    $forecast=3;
                }
                //查询队伍信息
                if($team_num){
                    $teamInfo=$this->hl_match_team->getMatchTeam($team_num,1);
                    $recommend_team=$teamInfo['team_num'];
                    $recommend_team_name=$teamInfo['short_name'];
                }
                $data[]=array(
                    'match_num'=>$val['match_num'],
                    'league_num'=>$val['league_num'],
                    'comp_name'=>$indexs[0]['comp_name'],
                    'comp_num'=>$indexs[0]['comp_num'],
                    'init_pankou'=>$indexs[0]['first'][1],
                    'now_pankou'=>$indexs[0]['now'][1],
                    'recommend_team'=>$recommend_team,
                    'recommend_team_name'=>$recommend_team_name,
                    'forecast'=>$forecast,
                    'confidence'=>$res[1],
                    'utime'=>date('Y-m-d H:i:s'),
                );

        }


        dd($data);
    }


    /*
     * 是否已存在
     */
    public function getMatchInfo($match_num){
        $info=self::where('match_num',$match_num)->first();
        if($info){
            $info=$info->toArray();
        }
        return $info;
    }

}
