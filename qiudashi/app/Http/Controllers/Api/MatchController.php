<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\hl_soccer_match;
use App\Models\hl_basketball_match;
use App\Models\hl_match_information;
use App\Models\hl_match_recommend;
use App\Respository\FFMpegPhp;


class MatchController extends Controller
{

    public function __construct(hl_match_information $hl_match_information,hl_soccer_match $hl_soccer_match,hl_basketball_match $hl_basketball_match,hl_match_recommend $hl_match_recommend) {
        $this->hl_soccer_match = $hl_soccer_match;
        $this->hl_basketball_match = $hl_basketball_match;
        $this->hl_match_information = $hl_match_information;
        $this->hl_match_recommend = $hl_match_recommend;
    }

    //添加情报
    public function addInformation(Request $request) {
            $param = $request->all();
            $math_num=$param['math_num'];
            $math_type=$param['math_type'];

            //开启事务
            DB::beginTransaction();
            $res=$this->hl_match_information->addInformation($param);//情报
            $data['has_information']=$param['status'];
            if($math_type==1){
                $updateMathRes=$this->hl_soccer_match->updateSoccerMatch($math_num,$data);
            }else{
                $updateMathRes=$this->hl_soccer_match->updateSoccerMatch($math_num,$data);
            }
            if(!$updateMathRes || !$res){
                DB::rollback();  //回滚
                return $this->rtJsonError();
            }
            DB::commit(); //提交
            return $this->rtJson($data);
    }

    //查询情报
    public function getInformation(Request $request) {
        $match_num = $request->input('match_num', 0);
        $match_type = $request->input('match_type', 1);
        $team_num = $request->input('team_num', 0);
        if (!$match_num || !$team_num) {
            return $this->rtJsonError();
        }
        $data = $this->hl_match_information->getInformation($match_num, $team_num, $match_type);
        return $this->rtJson_($data);
    }


    /*
     *
     */

    public function getMatch(Request $request){
        $this->hl_match_recommend->getMatch();
    }




}
