<?php

namespace App\Http\Controllers\Api;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\hl_comment_reply;
use App\Respository\CommentRespository;
use App\Models\hl_comment;
use App\Respository\FaceUtility;
use GatewayWorker\Lib\Gateway;
use App\Models\hl_news;
use App\Models\hl_fabulous;
use App\Models\hl_videos;

class FabulousController extends Controller
{

    protected $CommentRespository;

    /*
     * 依赖注入
     */
    public function __construct(CommentRespository $CommentRespository)
    {
        //$this->CommentRespository = $CommentRespository;
    }
    /*
      * 点赞/取消点赞
      * $user_id 用户id
      * $topic_id 主题id（文章id,视频id，评论id，被回复id）
      * $type类型 1：文章；2：视频；3：评论,4:回复
      * $fabulous_id 点赞id 取消点赞时传入
      * $is_reply 是否是回复：1：回复 作废
      * $comment_type 评论类型 1：文章；2：视频；(类型为评论时有效) 作废
      *返回 当前记录id
      */
    public function fabulous(Request $request){
       // $user_id=$request->input('user_id');
        $topic_id=$request->input('topic_id');//
        $type=$request->input('type');
        // $comment_type=$request->input('comment_type',0);
        // $is_reply=$request->input('is_reply',0);
        $fabulous_id=$request->input('fabulous_id',0);//点赞id
        $user_id = $request->user_info['user_id'];
        if(!$user_id){
            return $this->rtJsonError(102);
        }
        if(!$topic_id || !$type){
            return $this->rtJsonError(1000301);
        }
        DB::beginTransaction();
       // $type == 2 && $fabulous_id = 0;
        $fabulousInfo=hl_fabulous::fabulousInfo($user_id,$type,$topic_id);
        if($fabulousInfo){
            $fabulous_id=$fabulousInfo['id'];
            //取消点赞
            $res=$this->canche($fabulous_id,$type,$topic_id);
        }else{
            //点赞
            $res=$this->add($user_id,$type,$topic_id);
        }

        if($res){
            DB::commit();  //提交
            return $this->rtJson_();
        }else{
            DB::rollback();  //回滚
            return $this->rtJsonError(1000303,'fail');
        }

    }

    //取消处理
    public function canche($fabulous_id,$type,$topic_id){

        switch ($type) {
            case 1:
                //文章点赞数量-1
                $res = hl_news::modifyFabulous($topic_id, 'sub');
                break;
            case 2:
                //视频点赞数量-1
                $res=true;
                break;
            case 3:
                //评论点赞数量-1
                $res=hl_comment::commentDecrement($topic_id);
                break;
            case 4:
                //回复点赞数量-1
                $res=hl_comment_reply::replyDecrement($topic_id);
                break;

        }
        $fabulousRes=hl_fabulous::delFabulous($fabulous_id);
        if($res && $fabulousRes){
            return true;
        }
        return false;
    }

    //添加操作
    public function add($user_id,$type,$topic_id){

        switch ($type) {
            case 1:
                //文章点赞数量+1
                $res = hl_news::modifyFabulous($topic_id, 'add');
                break;
            case 2:
                //视频点赞数量+1
                $hl_videos = new hl_videos();
                $res = $hl_videos->fabulous($user_id, $topic_id);
                return $res;
                break;
            case 3:
                //评论点赞数量+1
                $res=hl_comment::commentIncrement($topic_id);
                break;
            case 4:
                //回复点赞数量+1
                $res=hl_comment_reply::replyIncrement($topic_id);
                break;

        }
        $fabulousRes=hl_fabulous::addFabulous($user_id,$topic_id,$type);

        if($res && $fabulousRes){
            return true;
        }
        return false;
    }
   
}
