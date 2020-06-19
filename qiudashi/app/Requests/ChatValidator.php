<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ChatValidator extends FormRequest
{

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return false;
    }



    #参数验证
    public function ruleMag($arr)
    {

        $validator = Validator::make($arr, [
             'type' => 'required',

        ], [
            'type.required' => '缺少参数',

        ], []
        );
        if ($validator->fails()) {
            return ['code' => 200111, 'msg' => $validator->errors()->all()];
        } else {
            return ['code' => 200];
        }
    }


    //聊天 必要参数验证
    public function ruleMsg($arr)
    {

        $validator = Validator::make($arr, [
            'type' => 'required',
            'from_uid' => 'required',
            'to_uid' => 'required',
            'identity' => 'required',
        ], [
            'type.required' => '缺少参数',
            'from_uid.required' => '缺少发送方id',
            'to_uid.required' => '缺少接收者id',
            'identity.required' => '缺少用户身份',

        ], []
        );
        if ($validator->fails()) {
            return ['code' => 200111, 'msg' => $validator->errors()->all()];
        } else {
            return ['code' => 200];
        }
    }

    //聊天类型为say时 校验的参数
    public function ruleMsgTwo($arr)
    {

        $validator = Validator::make($arr, [

            'contentType' => 'required',
            '_id' => 'required',
            'user' => 'required',

           // 'text' => 'required',

        ], [

            'contentType.required' => '缺少消息类型',
            '_id.required' => '消息参数有误',
            'user.required' => '用户参数有误',
           // 'user.array' => '用户数据格式有误',
           // 'identity.required' => '缺少用户身份',
           // 'text.required' => '缺少内容参数',

        ], []
        );
        if ($validator->fails()) {
            return ['code' => 200111, 'msg' => $validator->errors()->all()];
        } else {
            return ['code' => 200];
        }
    }


    //好友列表验证
    public function ruleFriends($arr)
    {

        $validator = Validator::make($arr, [
            'identity' => 'required',
            'user_id' => 'required',
        ], [

            'identity.required' => '缺少用户类型',
            'user_id.required' => '缺少用户id',
        ], []
        );
        if ($validator->fails()) {
            return ['code' => 200111, 'msg' => $validator->errors()->all()];
        } else {
            return ['code' => 200];
        }
    }


 
}
