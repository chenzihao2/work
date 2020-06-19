<?php

namespace App\Http\Controllers\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\hl_comment_reply;
use App\Models\hl_comment;
use App\Models\hl_user;
use App\Models\hl_report;
use App\Models\hl_news;
use App\Models\hl_videos;
use App\Respository\CommentRespository;

use GatewayWorker\Lib\Gateway;

class ReportController extends Controller
{
    protected $topicType=['文章','视频','评论','回复'];
    protected $hl_user;
    /*
    * 依赖注入
    */
    public function __construct()
    {
        $this->CommentRespository = new CommentRespository();
        $this->hl_user=new hl_user();
    }
    /*
     * 投诉列表
     */
    public function reportList(Request $request){
        $nick_name=$request->input('nick_name','');
        $to_nick_name=$request->input('to_nick_name','');
        $title=$request->input('title','');
        $times=$request->input('times',[]);
        $report_type=$request->input('report_type',0);
        $page=$request->input('page',1);
        $pagesize=$request->input('pagesize',15);
        $where=[];
        if($nick_name){
            $where[]=['nick_name','like',"%".$nick_name."%"];
        }
        if($to_nick_name){
            $where[]=['to_nick_name','like',"%".$to_nick_name."%"];
        }
        if($title){
            $where[]=['title','like',"'%".$title."%'"];
        }
        if($report_type){
            $where[]=['report_type','=',$report_type];
        }
        if(count($times)){
            $where[]=['create_time','>=',$times[0]];
            $where[]=['create_time','<=',$times[1]];
        }

        $result=hl_report::reportList($where,$page,$pagesize);

        foreach($result['list'] as &$v){
            $conmentInfo=[];
            // $author_info=User::userInfo($v['author_id']);
            //$v['author_name']=$author_info['nick_name'];
            $v['content']='';
            $v['image']='';
            $v['title']='';
            foreach($this->cate as $vv){
                if($vv['id']==$v['report_type']){
                    $v['report_type_name']=$vv['title'];
                }

            }

            $v['topic_type_name']=$this->topicType[$v['topic_type']-1];
            //1：文章；2：视频，3：评论，4回复
            if($v['topic_type']==1){

            }
            if($v['topic_type']==2){

            }
            if($v['topic_type']==3){
                $conmentInfo=$this->CommentRespository->commentInfo($v['topic_id']);
                $v['content']=$conmentInfo['content'];
                $v['image']=$conmentInfo['image'];
                $v['title']=$conmentInfo['topic_title'];
            }
            if($v['topic_type']==4){
                $conmentInfo=$this->CommentRespository->replyInfo($v['topic_id']);
                $v['content']=$conmentInfo['content'];
                $v['image']=$conmentInfo['image'];
                $v['title']=$conmentInfo['topic_title'];
            }


        }
        $this->rtJson($result);
    }

    /*
     * 查看举报
     */

    public function seeInfo(Request $request){
        $id=$request->input('id','');
        if(!$id){
            return $this->rtJsonError(1000301,'缺少参数');
        }

        $info=hl_report::reportInfo($id);



        //$info=hl_report::reportInfo($topic_id);
        //文章
        if($info['topic_type']==1){
            $info['relevant']=(new hl_news())->getNewsInfo($info['topic_id']);
        }
        //视频
        if($info['topic_type']==2){
            $info['relevant']=(new hl_videos())->getVideoInfo($info['topic_id']);
        }
        //评论或者回复
        if($info['topic_type']==3 || $info['topic_type']==4){
            $info['relevant']= $this->CommentRespository->thisComment($info['topic_id'],$info['topic_type']);

        }

        $this->rtJson($info);

    }


    /*
     * 未违规
     * $type:1 删除评论，2未违规，3 禁言三日，4 禁言7日 -1 永久禁言
     */
    public function changeStatus(Request $request){
            $id=$request->input('id','');
            $type=$request->input('type','');

            if(!$id){
                return $this->rtJsonError(1000301,'缺少参数');
            }
            $info=hl_report::reportInfo($id);
            switch ($type){
                case 1:
                    $topic_type=$info['topic_type']==3?1:2;
                    $res=$this->CommentRespository->delComent($info['topic_id'],$topic_type);
                    break;
                case 2:
                    $res=$this->hl_user->userUpdate($info['to_user_id'],['forbidden_day'=>0,'forbidden_time'=>0]);
                   // $res=hl_report::reportUpdate($id,['status'=>1]);
                    break;
                case 3:
                    $day=3;
                    break;
                case 4:
                    $day=7;
                    break;
                case -1:
                    $day=-1;
                    break;

            }
            if(in_array($type,[3,4,-1]) && $info['to_user_id']){
                $data['forbidden_day']=$day;
                $data['forbidden_time']=time();
                $res=$this->hl_user->userUpdate($info['to_user_id'],$data);
            }
            hl_report::reportUpdate($id,['status'=>1,'results'=>$type]);

            $this->rtJson();
    }


    /*
     * 回复
     */

    public function reply(Request $request){
        $id=$request->input('id','');
        $user_id=$request->input('user_id','');
        $reply_content=$request->input('reply_content','');
        if(!$id || !$user_id || !$reply_content){
            return $this->rtJsonError(1000301,'缺少参数');
        }
        /*举报修改参数*/
        $data['reply_content']=$reply_content;
        $data['is_reply']=1;

        hl_report::reportUpdate($id,$data);
        /*向消息中心表写入数据*/


        $this->rtJson();

    }


    

}
