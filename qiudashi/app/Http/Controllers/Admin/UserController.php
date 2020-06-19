<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\hl_user;
use App\Models\hl_channel;

class UserController extends Controller
{
    //

    public function __construct() {
        $this->hl_user = new hl_user();
    }

    /*
     * 禁言
     * user_id 用户id
     * forbidden_day 禁言天数:0 正常，3：三天，7：七天，-1：永久禁言
     */
    public function isForbidden(Request $request){
        $user_id=$request->input('user_id','');
        $forbidden_day=$request->input('forbidden_day',0);
        if(!$user_id){
            return $this->rtJsonError(2000401);
        }
        $data['forbidden_day']=$forbidden_day;
        $data['forbidden_time']=$forbidden_day==0?0:time();
        $res=$this->hl_user->userUpdate($user_id,$data);
        return $this->rtJson();
    }

}
