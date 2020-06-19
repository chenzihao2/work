<?php

namespace App\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CommentValidator extends FormRequest
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



    #回复参数验证
    public function ruleRep($arr)
    {

        $validator = Validator::make($arr, [

            'comment_id' => 'required',
            'reply_id' => 'required',
            'from_uid' => 'required',
            'to_uid' => 'required',
            'topic_id' => 'required',
            'topic_type' => 'required',
           // 'nick_name' => 'required',
            // 'headimgurl' => 'required',
            'content_type' => 'required',
            //'type' => 'required',
        ], [
            'comment_id.required' => '缺少评论id',
            'reply_id.required' => '缺少回复目标id',
            'from_id.required' => '缺少用户id',
            'to_uid.required' => '缺少目标用户id',
            'topic_id.required' => '缺少主题id',
            'topic_type.required' => '缺少主题类型',
            //'nick_name.required' => '缺少用户昵称',
            'content_type.required' => '缺少文本类型',

        ], []
        );
        if ($validator->fails()) {
            return ['code' => 1000301, 'msg' => $validator->errors()->all()];
        } else {
            return ['code' => 200];
        }
    }


    // 必要参数验证
    public function ruleCom($arr)
    {

        $validator = Validator::make($arr, [
            'user_id' => 'required',
            'topic_id' => 'required',
            'topic_type' => 'required',
           // 'topic_title' => 'required',
           // 'author_id' => 'required',
            //'nick_name' => 'required',
           // 'headimgurl' => 'required',
            'content_type' => 'required',
        ], [
            'user_id.required' => '缺少用户id',
            //'type.required' => '缺少发布类型',
            'topic_id.required' => '缺少主题id',
            'topic_type.required' => '缺少主题类型',
           // 'topic_title.required' => '缺少主题标题',
            //'author_id.required' => '缺少作者',
            //'nick_name.required' => '缺少用户昵称',
            'content_type.required' => '缺少文本类型',

        ], []
        );
        if ($validator->fails()) {
            return ['code' => 1000301, 'msg' => $validator->errors()->all()];
        } else {
            return ['code' => 200];
        }
    }
    //举报参数校验
    public function ruleReport($arr)
    {
        if(!$arr['user_id']){
            return ['code' => 102,''];
        }
        $validator = Validator::make($arr, [

            'user_id' => 'required',
            'to_user_id' => 'required',
            'author_id' => 'required',
            'report_type' => 'required',
            'topic_id' => 'required',
            'topic_type' => 'required',
        ], [
            'user_id.required' => '缺少举报者id',
            'to_user_id.required' => '缺少被举报者id',
            'author_id.required' => '缺少作者id',
            'report_type.required' => '缺少举报类型',
            'topic_id.required' => '缺少被举报资源id',
            'topic_type.required' => '缺少被举报资源类型',
        ], []
        );
        if ($validator->fails()) {
            return ['code' => 1000301, 'msg' => $validator->errors()->all()];
        } else {
            return ['code' => 200];
        }
    }

 
}
