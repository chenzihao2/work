<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Respository\ChatRespository;
use App\Models\MessageModel;
use App\Http\Requests\ChatValidator;
use App\Respository\FaceUtility;
use GatewayWorker\Lib\Gateway;
use App\Models\User;
use App\Models\hl_user;

class ChatController extends Controller
{

    protected $ChatRespository;
    protected $address;
    /*
     * 依赖注入
     */
    public function __construct(ChatRespository $ChatRespository)
    {
        $this->ChatRespository = $ChatRespository;
    }

    /*
     * 用户绑定
     * formid 发送者id
     * clint_id 连接id
     */
    public function bind(Request $request){

        $param=$request->all();
        $data=['content'=>'success','time'=>time(),'type'=>'login'];
        $res=Gateway::bindUid($param['client_id'],$param['from_uid']);//客户端id与fromid绑定
        Gateway::sendToUid($param['from_uid'], json_encode($data));

        return $data;
    }

    /**
     * 发送消息
     * @return mixed
     */
    public function onMessage(Request $request){
        $param=$request->all();
		//date_default_timezone_set('PRC');
        //file_put_contents('logs.txt',json_encode($param));

       $say='{"from_uid":2402,"identity":1,"to_uid":152,"type":"say","text":"77777","user":{"_id":2402,"name":"13522072031","avatar":null},"createdAt":"2020-01-16T07:58:52.884Z","_id":"FC269865-0445-490E-BE42-91A6527219BB","contentType":1}';

      //$param=json_decode($say,true);
      //$param['_id']=(new FaceUtility())->create_guid();

        $validator = new ChatValidator();
        if(isset($param['type'])&&$param['type']=='login'){
            $param['to_uid']='fail';
        }
        $vol = $validator->ruleMsg($param);
        if($vol['code']!=200){
                return ['msg'=>$vol['msg'][0],'code'=>$vol['code']];
        }
        $from_uid=intval($param['from_uid']);
        $to_uid=intval($param['to_uid']);
        $identity=intval($param['identity']);

        //Gateway::getClientIdByUid判断uid是否有在线的client_id
        $data=[];
        switch ($param['type']){
            case 'login':
             $res=Gateway::bindUid($param['client_id'],$from_uid);//客户端id与fromid绑定

                $data=['content'=>'success','time'=>date('Y-m-d H:i:s',time()),'type'=>'login'];
                //获取未读消息
                $count=$this->ChatRespository->noReadTotal($from_uid);
                $this->pushUnreadCount($from_uid,$count);

                //推送好友列表$identity
                $friendsList=$this->ChatRespository->pushFriendsList($from_uid,$identity);
                $res=Gateway::sendToUid($from_uid,json_encode(['type'=>'friendsList','data'=>$friendsList]));
                break;


            case 'say':
                $vol = $validator->ruleMsgTwo($param);
                if($vol['code']!=200){
                    return ['msg'=>$vol['msg'][0],'code'=>$vol['code']];
                }




				if(!Gateway::isUidOnline($from_uid)){

				 return ['msg'=>'您已断开连接','code'=>0];
				}
				//是否禁言
				$userInfo=(new hl_user())->userInfo(['user_id'=>$from_uid]);
				if($userInfo['forbidden_say']){
					return ['msg'=>'您已被禁言','code'=>0];
				}



                //$text = nl2br(htmlspecialchars($param['text']));
                $text = $param['text'];

                //推送聊天内容
                $images='';
                if($param['contentType']==2){
                    $images=$param['image'];
                }

                $data=$this->pushContent($from_uid,$to_uid,$param['user']['name'],$param['user']['avatar'],$text,$param['_id'],$param['contentType'],$images);
                //聊天记录入库

                $user_type=isset($param['identity'])?$param['identity']:1;
                $messageData=['from_to'=>$param['from_uid'].'-'.$param['to_uid'],'type'=>$param['contentType'],'gmid'=>$param['_id'],'is_read'=>$data['is_read'],'create_time'=>date('Y-m-d H:i:s',time()),'content'=>$param['contentType']==1?$text:$images,'from_uid'=>$param['from_uid'],'to_uid'=>$param['to_uid'],'user_type'=>$user_type];

                $res=$this->ChatRespository->addMessage($messageData);
        }


        return $data;
    }



    /*
     * 推送当前用户 未读消息总数
     */

    public function pushUnreadCount($user_id,$count){
        Gateway::sendToUid($user_id, json_encode(['type'=>'unreadCount','msgCount'=>$count]));
    }

    /*
     * 推送好友列表
     * $from_uid 接收人
     */
    public function pushFriendsList($to_uid,$identity=1){
        $friendsList=$this->ChatRespository->pushFriendsList($to_uid,$identity);
        $res=Gateway::sendToUid($to_uid,json_encode(['type'=>'friendsList','data'=>$friendsList]));
    }
    /*
    * 推送内容
    * $from_uid 发送者id
    * $to_uid 接收者id
    * $name 发送者昵称
    * $avatar 发送者头像
    * $gmid 全局id
    * $contentType 类型：1文本 2图片
    * $images 图片
    * $user['_id'=>发送者id,'name'=>发送者昵称,'avatar'=>发送者头像]
    */
    public function pushContent($from_uid,$to_uid,$name='',$avatar='',$text,$gmid,$contentType=1,$images=''){
        $avatar=$avatar?$avatar:'https://hl-static.haoliao188.com/resource/202002/27/ca2ed48d3859896ff049903652c287db.png';
        $times=time();
        $from_uid=(int)$from_uid;
        $to_uid=(int)$to_uid;
        $data['type']='message';
        $data['to_uid']=$from_uid;
        $data['take_id']=$to_uid;
        $msg_data=array(
            'text'=>$text,
            'user'=>['_id'=>$from_uid,'name'=>$name,'avatar'=>$avatar],
            '_id'=> $gmid,
            'contentType'=> $contentType,
            'createdAt'=>$times,
	
        );
        //图片
        if($images){
            $msg_data['image']=$images;
        }
        $data['data'][]=$msg_data;
	
        Gateway::sendToUid($from_uid, json_encode($data));
        if(Gateway::isUidOnline($to_uid)){
            Gateway::sendToUid($to_uid, json_encode($data));
            //获取未读总数
            $count=$this->ChatRespository->noReadTotal($to_uid);
			
            $this->pushUnreadCount($to_uid,$count+1);
            $data['is_read']= 0;
        }else{
            $data['is_read']= 0;
        }
		
        return $data;
    }


    /*
     * 关注专家更新好友列表和自动回复
     *from_uid 用户id
     * to_uid 专家id
     */
    public function follow(Request $request){
        $from_uid=(int) $request->input('from_uid',0);//用户id
        $to_uid=(int) $request->input('to_uid',0);//专家id
        $identity=$request->input('identity',1);//用户类型
        $expert=$request->input('expert',[]);//批量专家更新列表
        $nickname=$request->input('nickname','');
        $avatar=$request->input('avatar','');
        //file_put_contents('logs.txt',json_encode($request->all()));

        if(empty($expert) && $to_uid){
            $expert[]=['to_uid'=>$to_uid,'nickname'=>$nickname,'avatar'=>$avatar];
        }
        if(!$from_uid || !$expert || !$identity){
            return ['msg'=>"缺少必要参数",'code'=>0];
        }

        $text='您好，欢迎关注我！获取更多优惠福利，请添加客服微信：haoliao365';
        foreach($expert as $k=>$v){

            $gmid=(new FaceUtility())->create_guid();
            $messageData=['from_to'=>$v['to_uid'].'-'.$from_uid,'type'=>1,'gmid'=>$gmid,'is_read'=>1,'create_time'=>date('Y-m-d H:i:s',time()),'content'=>$text,'from_uid'=>$v['to_uid'],'to_uid'=>$from_uid,'user_type'=>2];

            $res=$this->ChatRespository->addMessage($messageData);
            //向专家推送好友列表
            if(Gateway::isUidOnline($v['to_uid'])){
                //推好友列表
                $this->pushFriendsList($v['to_uid'],2);
            }

        }
        //向用户 推送好友列表
        $this->pushFriendsList($from_uid,$identity);
        return ['msg'=>"SUCCESS",'code'=>1];

        /*if($res){
            return ['msg'=>"SUCCESS",'code'=>1];
        }else{
            return ['msg'=>"FAIL",'code'=>0];
        }*/
    }



    public function follow_bak(Request $request){
        $from_uid=(int) $request->from_uid;
        $to_uid=(int) $request->to_uid;


        $identity=$request->identity;
        $nickname=$request->nickname;
        $avatar=$request->avatar;
        // ,FILE_APPEND
        //file_put_contents('logs.txt',json_encode($request->all()));

        if(!$from_uid || !$to_uid || !$identity){
            return ['msg'=>"缺少必要参数",'code'=>0];
        }
        $gmid=(new FaceUtility())->create_guid();


        $text='您好，欢迎关注我！获取更多优惠福利，请添加客服微信：haoliao365';
        //推送内容
        // $data=$this->pushContent($to_uid,$from_uid,$nickname,$avatar,$text,$gmid,1);

        $messageData=['from_to'=>$to_uid.'-'.$from_uid,'type'=>1,'gmid'=>$gmid,'is_read'=>1,'create_time'=>date('Y-m-d H:i:s',time()),'content'=>$text,'from_uid'=>$to_uid,'to_uid'=>$from_uid,'user_type'=>2];
        $res=$this->ChatRespository->addMessage($messageData);

        //向用户 推送好友列表
        $this->pushFriendsList($from_uid,$identity);
        //向专家推送好友列表
        if(Gateway::isUidOnline($to_uid)){
            //推好友列表
            $this->pushFriendsList($to_uid,2);
        }


        if($res){
            return ['msg'=>"SUCCESS",'code'=>1];
        }else{
            return ['msg'=>"FAIL",'code'=>0];
        }
    }




    /*
     * 取消关注更新数据
     */

    public function cancel(Request $request){
        $from_uid=$request->from_uid;
        $to_uid=$request->to_uid;
        $identity=$request->identity;
	//file_put_contents('testlog.txt',json_encode($request->all()));
        if(!$from_uid || !$to_uid || !$identity){
            return ['msg'=>"缺少必要参数",'code'=>0];
        }
        //向用户 推送好友列表
        $this->pushFriendsList($from_uid,$identity);
        //向专家推送好友列表
        if(Gateway::isUidOnline($to_uid)){
            //推好友列表
            $this->pushFriendsList($to_uid,2);
        }
        //清空聊天记录
        $res=$this->ChatRespository->delRecord($from_uid,$to_uid);
        if($res){
            return ['msg'=>"SUCCESS",'code'=>1];
        }else{
            return ['msg'=>"FAIL",'code'=>0];
        }
    }








    /*
     * 获取历史消息列表
     */
    public function messageList(Request $request){
        $page=$request->page;
        $from_uid=$request->from_uid;
        $to_uid=$request->to_uid;
        $list=$this->ChatRespository->messageList($from_uid,$to_uid,$page);
        return ['code' => 1, 'msg' => 'SUCCESS','data' => $list];
    }

    /*
     * 获取未读消息数量
     * to_uid 接收者id
     * from_uid 发送者id（可选）
     */
    public function unreadCount(Request $request){
        $to_uid=$request->to_uid;
        $from_uid=$request->from_uid;
        $count=$this->ChatRespository->noReadTotal($to_uid,$from_uid);
        return ['code' => 1, 'msg' => 'SUCCESS','data' => $count];
    }


    /*
     * 打开聊天 所有的未读改为已读
     * to_uid 接收者id
     * from_uid 发送者id
     *
     */
    public function openChat(Request $request){

        $to_uid=$request->to_uid;
        $from_uid=$request->from_uid;
        $res=$this->ChatRespository->changeRead($from_uid,$to_uid);
        if($res){
            return ['code' => 1, 'msg' => 'SUCCESS'];
        }else{
            return ['code' => 0, 'msg' => 'fail'];
        }
    }

    /*
     * 关闭聊天接口
     *to_uid 接收者id
     */

    public function closeChat(Request $request){
        $to_uid=$request->to_uid;
        $from_uid=$request->from_uid;
		
        if(!$to_uid || !$from_uid){
            return ['msg'=>'缺少接收者id','code'=>0];
        }
		
		$res=$this->ChatRespository->changeRead($from_uid,$to_uid);
        //获取未读消息
        $count=$this->ChatRespository->noReadTotal($to_uid);
		
        $this->pushUnreadCount($to_uid,$count);
        return ['code' => 1, 'msg' => 'SUCCESS'];

    }



    /*
     * 好友列表接口
     * 暂时不用
     */

    public function friendsList(Request $request){
        $param=$request->all();
        $validator = new ChatValidator();
        $vol = $validator->ruleFriends($param);
        if($vol['code']!=200){
            return ['msg'=>$vol['msg'][0],'code'=>$vol['code']];
        }
        $page=isset($param['page'])?$param['page']:1;
        $identity=$param['identity'];//用户类型
        $user_id=$param['user_id'];//用户id
        if($identity==1){
            $result=$this->ChatRespository->friendsList($user_id,$page);
        }else{
            $userList=$this->ChatRespository->expertFriendsList($user_id,$page);//关注我的用户

            $expertList=$this->ChatRespository->friendsList($user_id,$page);//我关注的专家

            $result=array_merge($userList,$expertList);
        }
        //一对一未读消息
        foreach($result as &$v){
            $count=$this->ChatRespository->noReadTotal($user_id,$v['user_id']);
            $v['msgCount']=$count;
            $v['msgInfo']=[];

            $msgInfo=$this->ChatRespository->getNewRecord($user_id,$v['user_id']);
            if($msgInfo){
                $msgInfo['createdAt']=strtotime($msgInfo['createdAt']);
                $v['msgInfo']=$msgInfo;
            }

        }
        return ['code' => 1, 'msg' => 'SUCCESS','data'=>$result];
    }



    /**
     * 客户端断开连接
     * @param integer $client_id 客户端id
     * @param integer $from_uid 用户id
     */
    public function onClose(Request $request){
       return Gateway::isUidOnline(10);
       // file_put_contents('test.txt',json_encode($request->all()));
    }

    //跑数据脚本
    public function expertMsg(Request $request){
        $userList=$this->ChatRespository->autoMsg();
    }

}
