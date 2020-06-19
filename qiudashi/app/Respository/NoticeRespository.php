<?php

namespace App\Respository;


use App\Http\Requests\requiredValidator;
use Illuminate\Support\Facades\Redis;
use App\Models\User;
use App\Models\hl_comment;
use App\Models\hl_comment_reply;
use App\Models\hl_fabulous;
use App\Respository\FaceUtility;
use App\Respository\CommentRespository;
use GatewayWorker\Lib\Gateway;
use Illuminate\Support\Facades\DB;
class NoticeRespository
{

    protected $model;
    protected $userFollowExpert;
    protected $user;

    /*
     * 依赖注入
     */
    public function __construct(hl_interact_notice $notice)
    {
        $this->model = $notice;
    }

    /*
   * 发布评论
   * $comment 内容
   * $user_id 互动人id
   * $author_id 作者id
   * $topic_id 主题id(文章id/视频id/评论id/回复id)
   * $topic_type 主题类型 1：文章；2：视频，3：评论，4回复
   * $nick_name 互动人昵称
   * $headimgurl 互动人头像
   * $type 1：点赞；2：评论，3回复
   */
    public function addComment($param){
        $times=date("Y-m-d H:i:s");
        $data=array(
            'user_id'=>$param['user_id'],
            'topic_id'=>$param['topic_id'],
            'topic_type'=>$param['topic_type'],
            'author_id'=>$param['author_id'],
            'nick_name'=>$param['nick_name'],
            'headimgurl'=>isset($param['headimgurl'])?$param['headimgurl']:'https://hl-static.haoliao188.com/resource/202002/27/ca2ed48d3859896ff049903652c287db.png',
            'type'=>$param['type'],
            'status'=>1,
            'create_time'=>$times,
            'update_time'=>$times,
        );

        return $this->model->insertGetId($data);

    }

    /*
     * 通知列表
     */
    public function noticeList($where=[],$page=1,$pageSize=15){

        $commnet=new CommentRespository();
        $this->model->where($where);

        $count=$this->model->count();
        $totalPage = ceil($count/$pageSize); //总页数
        $startPage=($page-1)*$pageSize;//开始记录
        $list=$this->model->orderBy('id','desc')
            ->offset($startPage)->limit($pageSize)->get()->toArray();
        foreach($list as &$v){
            $v['commentInfo']=[];
            $v['replyInfo']=[];
            $v['original_article']='';

            if($v['topic_type']==1){

            }
            if($v['topic_type']==2){

            }
            //点赞
            if($v['type']==1){

                //获取文章或视频详情

            }


            //点赞的评论
            if($v['topic_type']==3){
                $commenfInfo=$commnet->commentInfo($v['topic_id']);
                $v['commentInfo'][]=$commenfInfo;
            }
            //点赞的回复
            if($v['topic_type']==4){
                $replyInfo=$commnet->replyInfo($v['topic_id']);
                $v['commentInfo'][]=$replyInfo;
                if(!$v['reply_id']){
                    $v['commentInfo'][]=$commnet->commentInfo($v['comment_id']);
                }else{
                    $v['commentInfo'][]=$commnet->replyInfo($replyInfo['reply_id']);
                }
            }

        }


    }

    /*
     * socket通知
     * $user_id 接收人
     * $type 类型
     * $data 提示内容 格式暂定
     */
    public function socketNotice($user_id,$type,$data){
        //在线就推送
        if(Gateway::isUidOnline($user_id)){
            Gateway::sendToUid($user_id, json_encode($data));
        }


    }

}
