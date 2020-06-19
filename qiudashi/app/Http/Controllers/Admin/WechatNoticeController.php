<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Respository\CommentRespository;
use App\Models\hl_wechat_notice;
use App\Models\User;

class WechatNoticeController extends Controller
{

    protected $CommentRespository;
    protected $address;
    /*
     * 依赖注入
     */
    public function __construct(CommentRespository $CommentRespository)
    {
        //$this->CommentRespository = $CommentRespository;
    }


    /*
     * 通知列表
     * $title 标题
     * $content 内容
     * $id
     * $page 页数
     * $pagesize 条数
     */

    public function wordsList(Request $request){
        $title=$request->input('title','');
        $content=$request->input('content','');
        $id=$request->input('id','');
        $page=$request->input('page',1);
        $pagesize=$request->input('pagesize',15);
        $where=[];
        if($title){
           $where[]=['title','like',"'%".$title."%'"];
        }
        if($content){
            $where[]=['content','like',"'%".$content."%'"];
        }
        if($id){
            $where[]=['id','=',$id];
        }
        $res=hl_wechat_notice::noticeLimit($where,$page,$pagesize);
        return $this->rtJson($res);
    }

    /*
     * 添加通知
     * $title 标题
     * $content 内容
     * $remarks 备注
     * $complete_time 完成时间
     * $status 0:待发送，1：已发送
     *
     */
    public function addNotice(Request $request){
            $title=$request->input('title','');
            $content=$request->input('content','');
            $remarks=$request->input('remarks','');
            $complete_time=$request->input('complete_time','');
            $status=$request->input('status',0);
            if(!$title || !$content || !$remarks || !$complete_time){
                return $this->rtJsonError(1000301,'缺少必要参数');
            }
            $times=date("Y-m-d H:i:s");
            $data['title']=$title;
            $data['content']=$content;
            $data['remarks']=$remarks;
            $data['complete_time']=$complete_time;
            $data['status']=$status;
            $data['ctime']=$times;
            $res=hl_wechat_notice::addNotice($data);
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
        $title=$request->input('title','');
        $content=$request->input('content','');
        $remarks=$request->input('remarks','');
        $complete_time=$request->input('complete_time','');
        $status=$request->input('status',0);
        $id=$request->input('id','');
        if(!$id){
            return $this->rtJsonError(1000301,'缺少id');
        }
        $times=date("Y-m-d H:i:s");
        $data['title']=$title;
        $data['content']=$content;
        $data['remarks']=$remarks;
        $data['complete_time']=$complete_time;
        $data['status']=$status;
        $data['utime']=$times;
        $res=hl_wechat_notice::updateNotice($id,$data);
        if($res){
            return $this->rtJson();
        }else{
            return $this->rtJsonError();
        }
    }



    /*
     * 发送
     */

    public function send(){

    }



}
