<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Models\hl_basketball_match;
use App\Models\hl_soccer_match;
use App\Models\hl_match_team;
use App\Respository\FaceUtility;
class hl_match_information extends Model
{
    //
    protected $connection = 'mysql_origin';
    protected $table = 'hl_match_information';
    public $timestamps = false;

    public function __construct() {
        $this->hl_basketball_match=new hl_basketball_match();
        $this->hl_soccer_match=new hl_soccer_match();
        $this->hl_match_team=new hl_match_team();
        $this->Utility = new FaceUtility();
    }

    /*
     *获取情报列表
     */
    public function getInformationList($match_num,$match_type){
        return self::where(['match_num'=>$match_num,'match_type'=>$match_type])->get()->toArray();
    }



    /*
     * 添加情报
     * assemble true需要组装数据 false不需要
     */
    public function addInformation($data,$is_assemble=true){
        if($is_assemble){
            $result=$this->information($data);
        }else{
            $result=$data;
        }

        return self::insert($result);
    }
    /*
     * 修改情报
     */
    public function updateInformation($data,$is_assemble=true){
        if($is_assemble){
            $result=$this->information($data);
        }else{
            $result=$data;
        }
        foreach($result as $v){
            $id=0;
            if(isset($v['id'])){
                $id=$v['id'];
                unset($v['id']);
            }
            if(isset($v['ctime'])){
                unset($v['ctime']);
            }
            $v['utime']=time();
            if($id){
                self::where('id',$id)->update($v);
            }

        }
        return true;
    }

    //组装可以用情报入库数据
    public function information($data){
        $result=[];
        $time=time();
        foreach($data['data'] as $val){
            foreach($val['data'] as $k=>$v){
                $rows=['content'=>$v['content'],'type'=>$k,'price'=>$data['price'],'status'=>$data['status'],'team_num'=>$val['team_num'],'match_num'=>$data['match_num'],'match_type'=>$data['match_type'],'ctime'=>$time,'utime'=>$time];
                if(isset($v['id']) && $v['id']){
                    $rows['id']=$v['id'];
                }
                $result[]=$rows;
            }
        }
        return $result;
    }

    public function getInformation($match_num, $team_num, $match_type) {
        $data = self::where('match_num', $match_num)->where('team_num', $team_num)->where('status', 1)->where('match_type', $match_type)->get()->toArray();
        $datas = [];
        for($i = 0; $i < 3; $i++) {
            $info = '';
            foreach ($data as $item) {
                if ($i == $item['type']) {
                    $info = $item['content'];
                    break;
                }
            }
            $datas[] = $info;
        }
        return $datas;
    }

    //后台获取情报详情
    public function getMatchInfomation($math_num,$math_type){
        if($math_type==1){
            $mathInfo=$this->hl_soccer_match->getMathInfo($math_num);
            $MathDetail=$this->hl_soccer_match->getMathDetail($math_num);
        }
        if($math_type==2){
            $mathInfo=$this->hl_basketball_match->getMathInfo($math_num);
            $MathDetail=$this->hl_basketball_match->getMathDetail($math_num);
        }

        $host_team=$this->hl_match_team->getMatchTeam($mathInfo['host_team'],$math_type);
        $guest_team=$this->hl_match_team->getMatchTeam($mathInfo['guest_team'],$math_type);

        $informationList=$this->getInformationList($math_num,$math_type);
        //已有情报
        $host_team['information']=[];
        $guest_team['information']=[];
        $price=0;
        if($informationList){
            foreach ($informationList as $k => $val) {
                if($val['team_num']==$mathInfo['host_team']){
                    $host_team['information'][]=$val;
                }
                if($val['team_num']==$mathInfo['guest_team']){
                    $guest_team['information'][]=$val;
                }
            }
            $price=$informationList[0]['price'];//方案金额
        }

        $price=$this->Utility->ncPriceFen2Yuan($price);
        $data['host_team']=$host_team;
        $data['guest_team']=$guest_team;
        $data['price']=$price;
        $data['is_free']=$price>0?0:1;
        $data['match_num']=$math_num;
        $data['match_type']=$math_type;
        return $data;
    }


    //定时更新情报
    public function matchInformation($param){


        $host_team=$this->hl_match_team->getMatchTeam($param['host_team'],$param['match_type']);
        $guest_team=$this->hl_match_team->getMatchTeam($param['guest_team'],$param['match_type']);
        if(!$host_team['short_name']){
            $host_team['short_name']=$host_team['name'];
        }
        if(!$guest_team['short_name']){
            $guest_team['short_name']=$guest_team['name'];
        }
        $host_team_informationList=[];
        $guest_teaminformationList=[];
        $informationList=$this->getInformationList($param['match_num'],$param['match_type']);
        if($informationList){
            foreach ($informationList as $k => &$val) {
                if($val['team_num']==$param['host_team']){
                    $host_team_informationList[]=$val;
                }
                if($val['team_num']==$param['guest_team']){
                    $guest_teaminformationList[]=$val;
                }
            }

        }else{
            foreach([$param['host_team'],$param['guest_team']] as $k=>$v){
                for($i=0;$i<3;$i++){
                    $information=array(
                        'type'=>$i,
                        'match_num'=>$param['match_num'],
                        'match_type'=>$param['match_type'],
                        'team_num'=>$v,
                        'content'=>'',
                        'price'=>0,
                    );
                    if($k==0){
                        $host_team_informationList[]=$information;
                    }else{
                        $guest_teaminformationList[]=$information;
                    }
                }
            }

        }

        //向对应情报栏目写入初始情报
        $confidence=explode(' ',$param['confidence']);
        if($confidence[0]=="和局"){
            $host_team_informationList[2]['content']=$param['content'];
            $guest_teaminformationList[2]['content']=$param['content'];
        }
        if($confidence[0]==$host_team['short_name']){
            $host_team_informationList[0]['content']=$param['content'];
        }
        if($confidence[0]==$guest_team['short_name']){
            $guest_teaminformationList[0]['content']=$param['content'];
        }
        $data=array_merge($host_team_informationList,$guest_teaminformationList);
        if(count($informationList)){
            $this->updateInformation($data,false);
        }else{
            $this->addInformation($data,false);
        }
    }




}
