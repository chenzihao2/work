<?php

namespace App\Respository;


use App\Http\Requests\requiredValidator;
use Illuminate\Support\Facades\Redis;
use App\Models\MessageModel;
use App\Models\User;
use App\Models\hl_user;
use App\Models\hl_userFollowExpert;
use App\Respository\FaceUtility;
use Illuminate\Support\Facades\DB;
class ChatRespository
{

    protected $model;
    protected $userFollowExpert;
    protected $user;

    /*
     * 依赖注入
     */
    public function __construct(MessageModel $message, hl_userFollowExpert $userFollowExpert,hl_user $user)
    {
        $this->model = $message;
        $this->userFollowExpert = $userFollowExpert;
        $this->user = $user;
    }
    //聊天记录入库
    public function addMessage($messageData){

        return $this->model->insert($messageData);
    }

    /*
     * 获取聊天记录
     */
    public function messageList($from_uid,$to_uid,$page=1){
        $pageSize=20;
        // DB::connection()->enableQueryLog();  // 开启QueryLog
        // return DB::getQueryLog();
        $thisModel=$this->model
            ->where(function($query) use ($from_uid,$to_uid){
                $query->where(['from_uid'=>$from_uid,'to_uid'=>$to_uid,'is_del'=>0]);
            })
            ->orWhere(function($query) use ($from_uid,$to_uid){
                $query->where(['from_uid'=>$to_uid,'to_uid'=>$from_uid,'is_del'=>0]);
            });

        $count= $thisModel->count();
        $totalPage = ceil($count/$pageSize); //总页数
        $startPage=($page-1)*$pageSize;//开始记录

        $list=$thisModel->offset($startPage)->orderBy('create_time','desc')->limit($pageSize)->get()->toArray();


        $data['type']='message';
        $data['to_uid']=intval($from_uid);
        $data['data']=[];
        if($list){
            foreach($list as $v){
                //需要获取发送者头像 昵称
                $userInfo=$this->user->userInfo(['user_id'=>$v['from_uid']]);
                if(!$userInfo['headimgurl']){
                    $userInfo['headimgurl']='https://hl-static.haoliao188.com/resource/202002/27/ca2ed48d3859896ff049903652c287db.png';
                }
                $msg_data=array(
                    'text'=>$v['type']==1?$v['content']:null,
                    'user'=>['_id'=>intval($v['from_uid']),'name'=>$userInfo['nick_name'],'avatar'=>$userInfo['headimgurl']],
                    'createdAt'=>strtotime($v['create_time']),
                    '_id'=>$v['gmid'],
                    'contentType'=>$v['type']
                );
                if($v['type']==2){
                    $msg_data['image']=$v['content'];
                }
                $data['data'][]=$msg_data;

            }
        }
        return $data;
    }

    /*
     * 未读消息总数  后续增加到缓存
     */
    public function noReadTotal($to_uid,$from_uid=0){
        $where[]=['to_uid','=',$to_uid];
        $where[]=['is_read','=',0];
        $where[]=['is_del','=',0];
        if($from_uid){
            $where[]=['from_uid','=',$from_uid];
        }
        $count=$this->model->where($where)->count();
        return $count;
    }

    /*
     * 打开聊天 所有的未读改为已读
     * to_uid 接收者id
     * from_uid 发送者id
     *
     */
    public function changeRead($from_uid,$to_uid){
        $where[]=['from_uid',$from_uid];
        $where[]=['to_uid',$to_uid];
        $where[]=['is_read',0];

        return $this->model->where($where)->update(['is_read'=>1]);

    }


    /*
     * 获取新一条的 聊天记录
     */
    public function getNewRecord($from_uid,$to_uid){
        ///  DB::connection()->enableQueryLog();  // 开启QueryLog
        return $this->model->where(function($query) use ($from_uid,$to_uid){
            $query->where(['from_uid'=>$from_uid,'to_uid'=>$to_uid]);
        })->orWhere(function($query) use ($from_uid,$to_uid){
            $query->where(['from_uid'=>$to_uid,'to_uid'=>$from_uid]);

        })->where(['is_del'=>0])->select('create_time as createdAt', 'content as text','type as contentType')->orderBy('msg_id','desc')->first();
        //return  DB::getQueryLog();
    }



    /*
    * 获取推送的好友列表
    * $user_id 用户id
    * $identity 用户类型
    */
    public function pushFriendsList($user_id,$identity=1){
        if($identity==1){
            $result=$this->friendsList($user_id);
        }else{
            $userList=$this->expertFriendsList($user_id);//关注我的用户

            $expertList=$this->friendsList($user_id);//我关注的专家

            $result=array_merge($userList,$expertList);
        }
        //一对一未读消息
        foreach($result as &$v){
            if(!$v['headimgurl']){
                $v['headimgurl']='https://hl-static.haoliao188.com/resource/202002/27/ca2ed48d3859896ff049903652c287db.png';
            }
            $count=$this->noReadTotal($user_id,$v['user_id']);
            $v['msgCount']=$count;
            $v['sort']=0;//排序
            $v['msgInfo']=['contentType'=>null,'createdAt'=>null,'text'=>null];

            $msgInfo=$this->getNewRecord($user_id,$v['user_id']);
            if($msgInfo){
                $msgInfo['createdAt']=strtotime($msgInfo['createdAt']);
                $v['sort']=$msgInfo['createdAt'];
                $v['msgInfo']=$msgInfo;
            }
        }
        file_put_contents('find.txt',json_encode($result));
        $last_names = array_column($result,'sort');
        array_multisort($last_names,SORT_DESC,$result);
        return $result;
    }










    //获取用户好友列表
    public function friendsList($user_id,$page=1){
        $pageSize=20;
        $where=['hl_user_follow_expert.follow_status'=>1,'hl_user_follow_expert.user_id'=>$user_id];

        $result=$this->userFollowExpert
            ->where($where)
            ->join('hl_user', function ($join) {
                $join->on('hl_user.user_id', '=','hl_user_follow_expert.expert_id');
            })
            ->select('hl_user.nick_name','hl_user.headimgurl','hl_user.user_id')
            ->get()->toArray();
        return  $result;

    }



    //获取专家好友列表
    public function expertFriendsList($user_id,$page=1){
        $where=['hl_user_follow_expert.follow_status'=>1,'hl_user_follow_expert.expert_id'=>$user_id];

        $result=$this->userFollowExpert->where($where)
            ->join('hl_user', function ($join) {
                $join->on('hl_user.user_id', '=','hl_user_follow_expert.user_id');
            })
            ->select('hl_user.user_id','hl_user.nick_name','hl_user.headimgurl')
            ->get()->toArray();

        return $result;
    }





    /*
     * 清空当前用户关系所有聊天记录
     * $from_uid 发送者id
     * $to_uid 接收者id
     */

    public function delRecord($from_uid,$to_uid){
        return $this->model->where(function($query) use ($from_uid,$to_uid){
            $query->where(['from_uid'=>$from_uid,'to_uid'=>$to_uid]);
        })->orWhere(function($query) use ($from_uid,$to_uid){
            $query->where(['from_uid'=>$to_uid,'to_uid'=>$from_uid]);

        })->update(['is_del'=>1]);
    }



    /*
     * 后台消息列表管理
     */

    public function recordList($where,$page=1,$pageSize=20){


        $count=$this->model->where($where)->count();

        //$pageSize=20;
        $totalPage = ceil($count/$pageSize); //总页数
        $startPage=($page-1)*$pageSize;//开始记录

        $result=$this->model->where($where)->offset($startPage)->limit($pageSize)->orderBy('create_time','desc')->get()->toArray();
        foreach($result as &$v){
            $userInfo_from=$this->user->userInfo(['user_id'=>$v['from_uid']]);
            $userInfo_to=$this->user->userInfo(['user_id'=>$v['to_uid']]);
            $v['from_nickname']=$userInfo_from['nick_name'];
            $v['to_nickname']= $userInfo_to['nick_name'];
        }
        $res['count']=$count;
        $res['list']=$result;
        return $res;
    }


    //临时使用
    public function autoMsg(){
        $followExpert=$this->userFollowExpert->where(['follow_status'=>1])->get();
        foreach($followExpert as $v){
            $exists=$this->model->where('from_uid',$v['expert_id'])->exists();
            if(!$exists){
                $dates=date('Y-m-d H:i:s',$v['create_time']);
                $gmid=(new FaceUtility())->create_guid();
                $messageData=['from_to'=>$v['expert_id'].'-'.$v['user_id'],'type'=>1,'gmid'=>$gmid,'is_read'=>1,'create_time'=>$dates,'content'=>'您好，欢迎关注我！','from_uid'=>$v['expert_id'],'to_uid'=>$v['user_id'],'user_type'=>2];
                //$res=$this->model->insert($messageData);
                //dump($res);
            }
        }
    }


}
