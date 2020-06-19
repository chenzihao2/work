<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\hl_comment_reply;
use Illuminate\Http\Request;
use App\Requests\CommentValidator;
use App\Respository\CommentRespository;
use App\Models\hl_comment;
use App\Respository\FaceUtility;
use GatewayWorker\Lib\Gateway;
use App\Models\hl_user;
use App\Models\hl_sensitives;
use App\Models\hl_config;
use App\Models\CheckConfig;

class CommentController extends Controller
{

    protected $CommentRespository;
    protected $address;
    protected $channel ='';
    protected $version ='';
    protected $show_comment_model =1;//评论模块
    /*
     * 依赖注入
     */
    public function __construct(CommentRespository $CommentRespository,CheckConfig $CheckConfig,Request $request)
    {
        $this->CommentRespository = $CommentRespository;
        $this->hl_user=new hl_user();
        $this->hl_sensitives=new hl_sensitives();
        $this->CheckConfig = $CheckConfig;
        $this->channel=$request->input('channel', '');
        $version = $request->input('version', '');
        $this->version =  (int)str_replace('.', '', $version);
        if($version && $this->channel){
            $congInfo=$this->CheckConfig->show($this->channel,$this->version);
            $this->show_comment_model=$congInfo['show_comment_model'];
        }
    }

   /*
    * 发布评论接口
    * $comment 内容
    * $user_id
    * $topic_id 主题id（文章/视频）
    * $topic_type 主题类型 1：文章；2：视频
    * $nick_name 昵称
    * $headimgurl 头像
    * $content_type 内容类型1：文字；2：图片；3：emoji
    * $image 图片 数组
    * $type 1评论，2回复
    */
        public function releaseComent(Request $request){
            $param=$request->all();

            $user_info = $request->user_info;
            $param['user_id'] = $user_info['user_id'];

            if(!$param['user_id']){
                 $this->rtJsonError(102);
            }

            //是否禁言
            $forbidden_msg=$this->hl_user->getForbiddenDay($param['user_id']);
            if($forbidden_msg['not_say']==true){
                return $this->rtJsonError(1000302,$forbidden_msg['msg'],$forbidden_msg);
            }

            $validator = new CommentValidator();
            $vol = $validator->ruleCom($param);
            if($vol['code']!=200){
                return $this->rtJsonError($vol['code'], $vol['msg'][0]);
            }
            $param['org_content']=$param['content'];
            $resourcesInfo=$this->CommentRespository->resourcesInfo($param['topic_id'],$param['topic_type']);
            $show_comment_model=hl_config::configInfo($param['topic_type']);//查询资讯/视频模块控制
            if($resourcesInfo['comment']!=1 || $show_comment_model!=1){
                return $this->rtJsonError(10003034);
            }
            //验证是否敏感词
            $param['content']= $this->hl_sensitives->matchSensitiveWords($param['content']);
            //等级为1
            if($param['content']===1){
                return $this->rtJsonError(1000303);
            }
            $res=$this->CommentRespository->addComment($param);

            if($res){
                return $this->rtJson_(['id'=>$res,'content'=>$param['content']]);
            }else{
                return $this->rtJsonError(1000302);
            }

        }

    /*
    * 评论回复接口
    * $comment_id 评论id
    * $reply_id 回复目标id
    * $comment 内容
    * $user_id
    * $topic_id 主题id（文章/视频）
    * $topic_type 主题id（文章/视频）
    * $nick_name 昵称
    * $headimgurl 头像
    * $content_type 内容类型1：文字；2：图片；3：emoji
    * $image 图片 数组
    * $from_uid 回复者id
    * $to_uid 被回复者id
    */
    public function commentReply(Request $request){
        $param=$request->all();
        $user_info = $request->user_info;
        $param['user_id'] = $user_info['user_id'];
        if(!$param['user_id']){
            return $this->rtJsonError(102);
        }

        //是否禁言
        $forbidden_msg=$this->hl_user->getForbiddenDay($param['user_id']);
        if($forbidden_msg['not_say']==true){
            return $this->rtJsonError(1000302,$forbidden_msg['msg'],$forbidden_msg);
        }
        $validator = new CommentValidator();
        $vol = $validator->ruleRep($param);
        if($vol['code']!=200){
            return $this->rtJsonError($vol['code'], $vol['msg'][0]);
        }
        $param['org_content']=$param['content'];
        //验证是否敏感词
        $param['content']= $this->hl_sensitives->matchSensitiveWords($param['content']);
        //等级为1
        if($param['content']===1){
            return $this->rtJsonError(1000303);
        }

        $res=$this->CommentRespository->addCommentReply($param);
        if($res){
           // return $this->rtJson();
            return $this->rtJson_(['id'=>$res,'content'=>$param['content']]);
        }else{
            return $this->rtJsonError(1000302);
        }
    }

    /*
     * 评论列表接口
     * $topic_id 主题id
     * $topic_type 主题类型 1：文章；2：视频
     * $page 页数
     * $pageSize 条数
     */
    public function commentList(Request $request){
        $topic_id=$request->input('topic_id');
        $topic_type=$request->input('topic_type');
        $page=$request->input('page',1);
        $pageSize=$request->input('pagesize',15);
        $user_info = $request->user_info;
        $user_id = $user_info['user_id'];
        $res=$this->CommentRespository->commentList($topic_id,$topic_type,$user_id,$page,$pageSize,$this->show_comment_model);
        return $this->rtJson_($res);
        //return $this->rtJson($res);
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
        $comment_id=$request->input('comment_id');
//        $user_id=$request->input('user_id',0);
        $page=$request->input('page',1);
        $pageSize=$request->input('pagesize',15);
        $user_info = $request->user_info;
        $user_id = $user_info['user_id'];
        $commentInfo=$this->CommentRespository->commentInfo($comment_id);
        $res=$this->CommentRespository->replyList($comment_id,$user_id,$commentInfo['topic_id'],$commentInfo['topic_type'],$page,$pageSize);
        return $this->rtJson_($res);
       // return $this->rtJson($res);
    }

   /*
    * 评论详情接口
    * $comment_id 评论id
    */

   public function commentInfo(Request $request){
       $comment_id=$request->input('comment_id');
//       $user_id=$request->input('user_id',0);
       $user_info = $request->user_info;
       $user_id = $user_info['user_id'];
       if(!$comment_id){
           return $this->rtJsonError(1000301);
       }
       $res=$this->CommentRespository->commentInfo($comment_id,$user_id);
       return $this->rtJson_($res);
//       return $this->rtJson($res);
   }

   /*
    * 删除评论
    * $comment_id 评论id
    * $type 1评论 2 回复
    */
    public function delComment(Request $request){
        $comment_id=$request->input('comment_id');
        $type=$request->input('type',0);
        $user_info = $request->user_info;
        $user_id = $user_info['user_id'];
        if(!$user_id){
            return $this->rtJsonError(102);
        }
        if(!$comment_id || !$type ){
            return $this->rtJsonError(1000301);
        }
        $res=$this->CommentRespository->delComent($comment_id,$type);
        return $this->rtJson_($res);

    }

}
