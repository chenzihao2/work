<?php

namespace App\Respository;


use App\Http\Requests\requiredValidator;
use Illuminate\Support\Facades\Redis;
use App\Models\hl_user;
use App\Models\hl_comment;
use App\Models\hl_comment_reply;
use App\Models\hl_fabulous;
use App\Models\hl_news;
use App\Models\hl_videos;
use App\Models\hl_sensitives;
use App\Models\hl_config;
use App\Respository\FaceUtility;
use Illuminate\Support\Facades\DB;

class CommentRespository
{

    protected $model;
    protected $userFollowExpert;
    protected $user;
    protected $hl_videos;
    protected $hl_news;
    protected $hl_sensitives;

    /*
     * 依赖注入
     */
    public function __construct()
    {
        $this->model = new hl_comment();
        $this->reply_user = new hl_comment_reply();
        $this->hl_videos = new hl_videos();
        $this->hl_news = new hl_news();
        $this->hl_user = new hl_user();
        $this->hl_sensitives = new hl_sensitives();
    }

    /*
   * 发布评论
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
    public function addComment($param){
        $times=date("Y-m-d H:i:s");
        $images='';
        if(isset($param['image']) && $param['image']){
            $images=implode(',',$param['image']);
        }
        $resources=$this->resourcesInfo($param['topic_id'],$param['topic_type']);//获取资源信息
        $userInfo=$this->hl_user->userInfo(['user_id'=>$param['user_id']]);
        $data=array(
            'user_id'=>$param['user_id'],
            'topic_id'=>$param['topic_id'],
            'topic_type'=>$param['topic_type'],
            'topic_title'=>$resources['title'],
            'author_id'=>0,
            'author_name'=>$resources['target'],
            'nick_name'=>$userInfo['nick_name'],
            'headimgurl'=>(isset($userInfo['headimgurl'])&&$userInfo['headimgurl'])?$userInfo['headimgurl']:'https://hl-static.haoliao188.com/resource/202002/27/ca2ed48d3859896ff049903652c287db.png',
            'content_type'=>$param['content_type'],
            'content'=>$param['content'],
            'org_content'=>$param['org_content'],
            'image'=>$images,
            'status'=>1,
            'create_time'=>$times,
            'update_time'=>$times,
        );

        return $this->model->insertGetId($data);

    }


    /*
     * 评论回复逻辑
     */
    public function addCommentReply($param){
        $times=date("Y-m-d H:i:s");
        $images='';
        if(isset($param['image']) && $param['image']){
            $images=implode(',',$param['image']);
        }

        $userInfo=$this->hl_user->userInfo(['user_id'=>$param['user_id']]);
        $toUserInfo=$this->hl_user->userInfo(['user_id'=>$param['to_uid']]);
        $commentInfo=$this->commentInfo($param['comment_id']);//获取主评论信息

        $data=array(
            'comment_id'=>$param['comment_id'],
            'reply_id'=>$param['reply_id'],
            'from_uid'=>$param['from_uid'],
            'to_uid'=>$param['to_uid'],
            'topic_id'=>$commentInfo['topic_id'],
            'topic_type'=>$commentInfo['topic_type'],
            'topic_title'=>$commentInfo['topic_title'],
            'nick_name'=>$userInfo['nick_name'],
            'to_nick_name'=>isset($toUserInfo['nick_name'])?$toUserInfo['nick_name']:'',
            'headimgurl'=>(isset($userInfo['headimgurl'])&&$userInfo['headimgurl'])?$userInfo['headimgurl']:'https://hl-static.haoliao188.com/resource/202002/27/ca2ed48d3859896ff049903652c287db.png',
            'content_type'=>$param['content_type'],
            'content'=>$param['content'],
            'org_content'=>$param['org_content'],
            'image'=>$images,
            'status'=>1,
            'create_time'=>$times,
            'update_time'=>$times,
        );
        return $this->reply_user->insertGetId($data);
    }

    /*
     * 查询相关资源数据
     */
    public function resourcesInfo($topic_id,$topic_type){
            //文章
            if($topic_type==1){
                $info=$this->hl_news->getNewsInfo($topic_id);

            }
            //视频
            if($topic_type==2){
                $info=$this->hl_videos->getVideoInfo($topic_id);
                if($info){
                    $info['target']='';
                }

            }

            return $info;
    }


    /*
     * 评论列表
     * $topic_id 主题id
     * $topic_type 主题类型
     * $user_id 用户id
     */
    public function commentList($topic_id,$topic_type,$user_id=0,$page=1,$pageSize=15,$show_comment_model=1){
        $resources=$this->resourcesInfo($topic_id,$topic_type);//获取资源信息
        $list=[];
        $count=0;
        $commentCount=0;
        $fabulous_id=null;
        $is_fabulous=0;
        if($show_comment_model==1 && $resources['comment']==1){
                $where=['hl_comment.topic_id'=>$topic_id,'hl_comment.topic_type'=>$topic_type,'hl_comment.status'=>1,'hl_comment.is_del'=>0];
                $model=$this->model
                    ->leftJoin(DB::raw('(select comment_id,count(*) as totalReply from hl_comment_reply) c '),'c.comment_id','=','hl_comment.id')
                    ->where($where);


                $count=$model->count();
                $totalPage = ceil($count/$pageSize); //总页数
                $startPage=($page-1)*$pageSize;//开始记录
                $list=$model
                    ->orderBy('hl_comment.prase_count','desc')
                    ->orderBy('c.totalReply','desc')
                    ->orderBy('hl_comment.id','desc')
                    ->offset($startPage)->limit($pageSize)->get()->toArray();

                foreach($list as &$v){
                    $v['fabulous_id']=0;
                    $v['is_fabulous']=0;
                    //是否点赞

                    if($user_id){
                        $fabulous=hl_fabulous::isFabulous($user_id,$v['id'],3);
                        $v['fabulous_id']=$fabulous['fabulous_id'];
                        $v['is_fabulous']=$fabulous['is_fabulous'];
                    }


                    $v['count']=$this->replyCount($v['id']);
                    $v['image']=$this->imagesArr($v['image']);
                    $replyList=$this->replyList($v['id'],$user_id,$v['topic_id'],$v['topic_type'],1,2);
                    foreach ($replyList['replyList'] as $kk=>$vv){
                        unset($replyList['replyList'][$kk]['relpyToInfo']);
                    }
                    $v['replyList']=$replyList['replyList'];
                }
                $commentCount=$this->topicCount($topic_id,$topic_type,$show_comment_model);//主题下所有评论数

                if($user_id){
                    $fabulousRes=hl_fabulous::isFabulous($user_id,$topic_id,$topic_type);//该资源是否点赞
                    $fabulous_id=$fabulousRes['fabulous_id'];
                    $is_fabulous=$fabulousRes['is_fabulous'];
                }

        }

        return ['commentList'=>$list,'totalCount'=>$count,'commentCount'=>$commentCount,'fabulous_id'=>$fabulous_id,'is_fabulous'=>$is_fabulous,'show_comment_model'=>$show_comment_model];

    }

    /*
     * 回复列表
     * $user_id 用户id
     */
    public function replyList($comment_id,$user_id=0,$topic_id,$topic_type,$page=1,$pageSize=15){

        $where=['comment_id'=>$comment_id,'topic_id'=>$topic_id,'topic_type'=>$topic_type,'status'=>1,'is_del'=>0];
        $model=$this->reply_user->where($where);
        $count=$model->count();
        $totalPage = ceil($count/$pageSize); //总页数
        $startPage=($page-1)*$pageSize;//开始记录
        $list=$model->offset($startPage)->orderBy('id','desc')->limit($pageSize)->get()->toArray();
        foreach($list as &$v){
            $v['user_id']=$v['from_uid'];
            $v['fabulous_id']=0;
            $v['is_fabulous']=0;
            //是否点赞
            if($user_id){
                $fabulous=hl_fabulous::isFabulous($user_id,$v['id'],4);
                $v['fabulous_id']=$fabulous['fabulous_id'];
                $v['is_fabulous']=$fabulous['is_fabulous'];
            }

            $v['image']=$this->imagesArr($v['image']);
            //$v['relpyToInfo']=$this->replyInfo($v['reply_id']);
        }

        return ['replyList'=>$list,'totalCount'=>$count];
    }

    /*
     * 评论详情
     *
     */

    public function commentInfo($id,$user_id=0){
        $info=$this->model->where(['id'=>$id])->first();
        if($info){
            $info['fabulous_id']=0;
            $info['is_fabulous']=0;
            //是否点赞
            if($user_id){
                $fabulous=hl_fabulous::isFabulous($user_id,$info['id'],3);
                $info['fabulous_id']=$fabulous['fabulous_id'];
                $info['is_fabulous']=$fabulous['is_fabulous'];
            }
            $info['image']=$this->imagesArr($info['image']);
            $info['count']=$this->replyCount($id);
        }
        return $info;
    }

    /*
     * 回复详情
     */
    public function replyInfo($id){
        $info=$this->reply_user->where(['id'=>$id])->first();


        if($info){
            $info['user_id']=$info['from_uid'];
            $info['image']=$this->imagesArr($info['image']);
        }


        return $info;
    }

    /*
     * 图片处理成数组
     */
    public function imagesArr($path){
        $arr=explode(',',$path);
        foreach($arr as $k=>$v){
            if(!$v){
                unset($arr[$k]);
            }
        }
        return $arr;
    }

    /*
     * 主题下评论总数
     * $topic_id 主题id
     * $topic_type 主题类型 1：文章；2：视频
     */
    public function topicCount($topic_id,$topic_type,$show_comment_model=1){
        $resourcesInfo=$this->resourcesInfo($topic_id,$topic_type);
        //$show_comment_model=hl_config::configInfo($topic_type);//查询资讯/视频模块控制
        $commentCount=0;
        $replyCount=0;
        if($resourcesInfo['comment']==1 && $show_comment_model==1){
            $where=['topic_id'=>$topic_id,'topic_type'=>$topic_type,'status'=>1,'is_del'=>0];
            $commentCount=$this->model->where($where)->count();
            $replyCount=$this->reply_user->where($where)->count();
        }

        return intval($commentCount+$replyCount);
    }


    /*
     * 评论下回复总数
     * $comment_id 主题id
     */
    public function replyCount($comment_id){
        $where=['comment_id'=>$comment_id,'status'=>1,'is_del'=>0];
        return $this->reply_user->where($where)->count();
    }


    /**后台评论管理逻辑**/
    /*
      * 后台评论列表
      * $topic_id 主题id
      * $topic_type 主题类型
      * $user_id 用户id
      */
    public function consoleCommentList($where=[],$page=1,$pageSize=15){
        $model=$this->model
            ->leftJoin(DB::raw('(select comment_id,count(*) as totalReply from hl_comment_reply) c '),'c.comment_id','=','hl_comment.id')
            //->leftJoin("users",'hl_comment.author_id','users.user_id')
            ->where($where);
        $count=$model->count();
        $totalPage = ceil($count/$pageSize); //总页数
        $startPage=($page-1)*$pageSize;//开始记录
        $list=$model
            //->orderBy('hl_comment.prase_count','desc')
            //->orderBy('c.totalReply','desc')
            ->orderBy('hl_comment.id','desc')
            ->offset($startPage)->limit($pageSize)->get()->toArray();

        foreach($list as &$v){
            if($v['topic_type']==1){
                $v['topic_title']='[文章]-'.$v['topic_title'];
            }
            if($v['topic_type']==2){
                $v['topic_title']='[视频]-'.$v['topic_title'];
            }
            $v['count']=$this->replyCount($v['id']);
            $v['image']=$this->imagesArr($v['image']);

        }

        return ['commentList'=>$list,'totalCount'=>$count];
    }

    /*
     * 回复管理
     */
    public function consoleReplyList($where,$page=1,$pageSize=15){
        $model=$this->reply_user->where($where);
        $count=$model->count();
        $totalPage = ceil($count/$pageSize); //总页数
        $startPage=($page-1)*$pageSize;//开始记录
        $list=$model->offset($startPage)->orderBy('id','desc')->limit($pageSize)->get()->toArray();
        foreach($list as &$v){
            $v['image']=$this->imagesArr($v['image']);
            if($v['reply_id']){
                $v['relpyToInfo']=$this->replyInfo($v['reply_id']);
            }else{
                $v['relpyToInfo']=$this->commentInfo($v['comment_id']);
            }

        }

        return ['replyList'=>$list,'totalCount'=>$count];
    }

    /*
     * 评论审核
     * $id 评论/回复id
     * $type 1评论，2回复
     * $status 0：未审核；1：通过，2：拒绝
     */

    public function chaneStatus($id,$type,$status){
        $data['update_time']=date("Y-m-d H:i:s");
        $data['status']=$status;
        if($type==1){
            $res=hl_comment::updateComment($id,$data);
        }else{
            $res=hl_comment_reply::updateReply($id,$data);
        }
        return $res;
    }

    /*
     * 删除评论
     * $type 1评论，2回复
     */
    public function delComent($id,$type){
        $data['update_time']=date("Y-m-d H:i:s");
        $data['is_del']=1;
        if($type==1){
            $res=hl_comment::updateComment($id,$data);
            hl_comment_reply::updateCommentId($id,$data);
        }else{
            $res=hl_comment_reply::updateReply($id,$data);
        }
        return $res;
    }

    /*
     * 获取当前评论得前后各五条数据
     * $type 1：评论，2回复
     */

    public function thisComment($id,$type){
        $list=[];
        if($type==3){
            $replyInfo=$this->commentInfo($id);//当前评论
            $replyInfo['current']=1;
            $list[]=$replyInfo;
        }
        //回复
        if($type==4){
            $replyInfo=  $this->replyInfo($id);
            $replyInfo['current']=1;

            $topList=$this->getTop($replyInfo['reply_id']);//向上找5条

            $topList[]=$replyInfo;
            $downList=$this->getdown($id);//向下找5条
            $list=array_merge($topList,$downList);
            $newList=[];
            foreach($list as $v){

                $newList[]=json_decode($v,true);
            }

            $newList=$this->orderReply($newList,0);
        }

        return $list;

    }

    /*
     * 向上找
     */

    public function getTop($pid=0,$level=0)
    {
        static $list = [];
        $replyInfo=$this->replyInfo($pid);
        if($replyInfo){
            $list[]=$replyInfo;

            if($replyInfo['reply_id'] && $level<5){
                $this->getTop($replyInfo['reply_id'],$level+1);
            }
        }

        return $list;
    }

    /*
     * 向下找
     */

    public function getdown($pid=0,$level=0)
    {
        static $list = [];
        $replyInfo=$this->reply_user->where('reply_id',$pid)->first();
        if($replyInfo){

            $list[]=$replyInfo;
            if($level<5){
                $this->getdown($replyInfo['id'],$level+1);
            }
        }

        return $list;
    }



    //已经找到的数据 排序

    public function orderReply($array,$pid=0){
        $arr = array();
        foreach($array as $v){
            if($v['reply_id']==$pid){
                $arr[] = $v;
                $arr = array_merge($arr,$this->orderReply($array,$v['id']));
            }
        }
        return $arr;
    }



}
