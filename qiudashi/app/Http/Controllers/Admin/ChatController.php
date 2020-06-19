<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Respository\ChatRespository;
use App\Models\MessageModel;
use App\Models\User;
class ChatController extends Controller
{

    protected $ChatRespository;

    /*
     * 依赖注入
     */
    public function __construct(ChatRespository $ChatRespository)
    {
        $this->ChatRespository = $ChatRespository;
    }


    /*
     * 获取消息 列表
     */
    public function recordList(Request $request)
    {

        $page = $request->page;
        $pagesize = $request->pagesize;
        $from_uid = $request->from_uid;
        $to_uid = $request->to_uid;
        $times = $request->times;

        $where[]=['is_del',0];
        if($from_uid){
            $where[]=['from_uid',$from_uid];
        }
        if($to_uid){
            $where[]=['to_uid',$to_uid];
        }
        if($times){
           // date('Y-m-d H:i:s', bcdiv($times[0], 1000));
            $where[]=['create_time','>=',date('Y-m-d H:i:s', bcdiv($times[0], 1000))];
            $where[]=['create_time','<=',date('Y-m-d H:i:s', bcdiv($times[1], 1000))];
        }


        $result = $this->ChatRespository->recordList($where, $page,$pagesize);

        return ['code' => 1, 'msg' => 'SUCCESS', 'data' => $result['list'],'count'=>$result['count']];

    }
	
	/*
	*
	*禁言
	*
	*user_id 用户id，
	*forbidden_say：0：正常，1禁言
	*/

	public function forbiddenSay(Request $request){
		$user_id=$request->user_id;
		$forbidden_say=$request->forbidden_say;
		if(!$user_id){
			
		}
		$res=(new User())->updateUser($user_id,['forbidden_say'=>$forbidden_say]);
		if($res){
			return ['code' => 1, 'msg' => 'SUCCESS'];
		}else{
			return ['code' => 0, 'msg' => 'FAIL'];
		}
	}



}
