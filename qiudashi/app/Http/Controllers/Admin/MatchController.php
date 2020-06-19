<?php

namespace App\Http\Controllers\Admin;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\hl_soccer_match;
use App\Models\hl_basketball_match;
use App\Models\hl_match_information;
use App\Respository\FFMpegPhp;
use App\Respository\FaceUtility;

class MatchController extends Controller
{

    public function __construct(hl_match_information $hl_match_information,hl_soccer_match $hl_soccer_match,hl_basketball_match $hl_basketball_match) {
        $this->hl_soccer_match = $hl_soccer_match;
        $this->hl_basketball_match = $hl_basketball_match;
        $this->hl_match_information = $hl_match_information;
        $this->Utility = new FaceUtility();
    }

    /*
     * 获取情报详情
     */
    public function getMatchInfomation(Request $request){
        $match_num=$request->input('match_num','');
        $match_type=$request->input('match_type','');
        if(!$match_num || !$match_type){
            return $this->rtJsonError(2000501);
        }
        $data=$this->hl_match_information->getMatchInfomation($match_num,$match_type);

        return $this->rtJson($data);
    }

    //添加情报
    public function addInformation(Request $request) {
        $param = $request->all();
        $math_num=$param['match_num'];
        $math_type=$param['match_type'];
        $param['data']=json_decode($param['data'],true);
        if(!$math_num || !$math_type){
            return $this->rtJsonError(2000501);
        }
        $param['price'] =  $this->Utility->ncPriceYuan2Fen($param['price']);
        $informationList=$this->hl_match_information->getInformationList($math_num,$math_type);
        //开启事务
        DB::beginTransaction();
        if(count($informationList)){
            $res=$this->hl_match_information->updateInformation($param);//修改情报
        }else{
            $res=$this->hl_match_information->addInformation($param);//添加情报
        }

        $data['has_information']=$param['status'];

        if($math_type==1){
            $updateMathRes=$this->hl_soccer_match->updateSoccerMatch($math_num,$data);
        }else{
            $updateMathRes=$this->hl_soccer_match->updateSoccerMatch($math_num,$data);
        }
        if(!$updateMathRes || !$res){
            DB::rollback();  //回滚
            return $this->rtJsonError(2000502);
        }
        DB::commit(); //提交
        return $this->rtJson();
    }

    //查询情报
    public function getInformation(Request $request) {
        $match_num = $request->input('match_num', 0);
        $team_num = $request->input('team_num', 0);
        if (!$match_num || !$team_num) {
            return $this->rtJsonError();
        }
        $data = $this->hl_match_information->getInformation($match_num, $team_num);
        return $this->rtJson($data);
    }


    //定时更新情报
    public function timingGetMatchInformation(Request $request){
        $param=$request->all();
        $this->hl_match_information->matchInformation($param);
    }



}
