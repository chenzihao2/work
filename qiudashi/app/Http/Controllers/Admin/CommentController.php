<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\hl_comment_reply;
use Illuminate\Http\Request;
use App\Requests\CommentValidator;
use App\Respository\CommentRespository;
use App\Models\hl_comment;
use App\Respository\FaceUtility;
use GatewayWorker\Lib\Gateway;
use App\Models\User;

class CommentController extends Controller
{

    protected $CommentRespository;
    protected $address;
    protected $arr=['不当言论','政治敏感','侮辱谩骂','淫秽色情','暴恐反动','不适内容'];
    /*
     * 依赖注入
     */
    public function __construct(CommentRespository $CommentRespository)
    {
        $this->CommentRespository = $CommentRespository;
    }

    /*
     * 评论列表接口
     * $topic_id 主题id
     * $topic_type 主题类型 1：文章；2：视频
     * $page 页数
     * $pageSize 条数
     */
    public function commentList(Request $request){
        $id=$request->input('id',0);
        $content=$request->input('content','');
        $status=$request->input('status','all');
        $title=$request->input('title','');
        $times=$request->input('times',[]);
        $topic_type=$request->input('topic_type',0);
        $page=$request->input('page',1);
        $pageSize=$request->input('pageSize',15);
        $where=[];
        if($id){
            $where[]=['hl_comment.id','=',$id];
        }
        if($content){
            $where[]=['hl_comment.content','like',"%".$content."%"];
        }
        if($title){
            $where[]=['hl_comment.topic_title','like',"%".$title."%"];
        }
        if($status!='all'){
            $where[]=['hl_comment.status','=',$status];
        }

        if(count($times) && $times[0]){
            $where[]=['hl_comment.create_time','>=',$times[0]];
        }
        if(count($times) && $times[1]){
            $where[]=['hl_comment.create_time','<=',$times[1]];
        }

        if($topic_type){
            $where[]=['hl_comment.topic_type','=',$topic_type];
        }

        $res=$this->CommentRespository->consoleCommentList($where,$page,$pageSize);
        return $this->rtJson($res);
    }

    /*
     * 回复列表接口
     * $comment_id 平论id
     * $user_id 平论id
     * $topic_type 主题类型 1：文章；2：视频
     * $page 页数
     * $pageSize 条数
     */
    public function replyList(Request $request){
        $id=$request->input('id',0);
        $content=$request->input('content','');
        $status=$request->input('status','all');
        $title=$request->input('title','');
        $times=$request->input('times',[]);
        $page=$request->input('page',1);
        $pageSize=$request->input('pageSize',15);
        $topic_type=$request->input('topic_type',0);
        $where=[];
        if($id){
            $where[]=['id','=',$id];
        }
        if($content){
            $where[]=['content','like',"%".$content."%"];
        }
        if($title){
            //$where[]=['topic_title','like',"%".$title."%"];
        }
        if($status!='all'){
            $where[]=['status','=',$status];
        }

        if(count($times) && $times[0]){
            $where[]=['create_time','>=',$times[0]];
        }
        if(count($times) && $times[1]){
            $where[]=['create_time','<=',$times[1]];
        }
        if($topic_type){
            $where[]=['topic_type','=',$topic_type];
        }
        $res=$this->CommentRespository->consoleReplyList($where,$page,$pageSize);
        return $this->rtJson($res);
    }

    /*
     * 评论回复列表接口
     */
    public function commentReplyList(Request $request){
        $comment_id=$request->input('comment_id',0);

        $page=$request->input('page',1);
       $pageSize=$request->input('pageSize',15);
        if(!$comment_id){
            return $this->rtJsonError(1000301);
        }
        $where[]=['comment_id','=',$comment_id];
        $res=$this->CommentRespository->consoleReplyList($where,$page,$pageSize);
        return $this->rtJson($res);
    }



    /*
     * 评论详情接口
     * $comment_id 评论id
     */

    public function commentInfo(Request $request){
        $comment_id=$request->input('comment_id');
        $user_id=$request->input('user_id',0);
        if(!$comment_id){
            return $this->rtJsonError(1000301);
        }
        $res=$this->CommentRespository->commentInfo($comment_id,$user_id);
        return $this->rtJson($res);
    }

    /*
     * 审核
     * $comment_id 评论id
     * $type 1评论，2回复
     * $status 0：未审核；1：通过，2：拒绝
     */
    public function changeStatus(Request $request){
        $comment_id=$request->input('comment_id');
        $status=$request->input('status',0);
        $type=$request->input('type',0);
        $res=$this->CommentRespository->chaneStatus($comment_id,$type,$status);
        return $this->rtJson();

    }

    /*
     * 举报类型接口
     */
    public function jubao(){
        return $this->rtJson($this->arr);
    }



    /*
     * 删除评论
     * $comment_id 评论id
     * $type 1评论 2 回复
     */
    public function delComment(Request $request){
        $comment_id=$request->input('comment_id');
        $type=$request->input('type',0);
        if(!$comment_id || !$type){
            return $this->rtJsonError(1000301);
        }
        $res=$this->CommentRespository->delComent($comment_id,$type);
        return $this->rtJson($res);
    }



}
