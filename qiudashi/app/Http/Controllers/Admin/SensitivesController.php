<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Respository\CommentRespository;
//use App\Respository\SensitivesRespository;
use App\Models\hl_sensitives;
use App\Respository\FaceUtility;
use GatewayWorker\Lib\Gateway;
use App\Models\User;

class SensitivesController extends Controller
{

    protected $CommentRespository;
    protected $address;
    protected $level=['禁止','危险', '敏感'];
    /*
     * 依赖注入
     */
    public function __construct(CommentRespository $CommentRespository)
    {
        //$this->CommentRespository = $CommentRespository;
    }


    /*
     * 敏感词列表
     * $words  关键词
     * $page 页数
     * $pagesize 条数
     */

    public function wordsList(Request $request){
        $words=$request->input('words','');
        $page=$request->input('page',1);
        $pagesize=$request->input('pagesize',15);
        $where=[];
        if($words){
            $where[]=['words','like',"%".$words."%"];
        }
        $res=hl_sensitives::wordsLimit($where,$page,$pagesize);
        foreach($res['list'] as &$v){

            $v['level_name']=$this->level[($v['level']-1)];
        }
        return $this->rtJson($res);

    }

    /*
     * 添加敏感词
     * $words
     * $level 1:禁止 2:危险 3:敏感
     *
     */
    public function addWords(Request $request){
        $words=$request->input('words','');
        $level=$request->input('level',1);
        if(!$words){
            return $this->rtJsonError(1000301,'缺少敏感词');
        }
        $times=date("Y-m-d H:i:s");
        $data['words']=$words;
        $data['level']=$level;
        $data['utime']=$times;
        $data['ctime']=$times;
        $res=hl_sensitives::addWords($data);
        if($res){
            return $this->rtJson();
        }else{
            return $this->rtJsonError();
        }
    }

    /*
     * 修改敏感词
     */
    public function updateStatus(Request $request){
        $words=$request->input('words','');
        $level=$request->input('level',1);
        $id=$request->input('id','');
        if(!$id){
            return $this->rtJsonError(1000301,'缺少id');
        }
        $times=date("Y-m-d H:i:s");
        $data['words']=$words;
        $data['level']=$level;
        $data['utime']=$times;

        $res=hl_sensitives::updateWords($id,$data);
        if($res){
            return $this->rtJson();
        }else{
            return $this->rtJsonError();
        }
    }

    /*
     * 删除敏感词
     */

    public function delWords(Request $request){
        $id=$request->input('id','');
        if(!$id){
            return $this->rtJsonError(1000301,'缺少id');
        }
        $times=date("Y-m-d H:i:s");
        $data['deleted']=1;
        $data['utime']=$times;

        $res=hl_sensitives::updateWords($id,$data);
        if($res){
            return $this->rtJson();
        }else{
            return $this->rtJsonError();
        }
    }





}
