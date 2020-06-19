<?php

namespace App\Http\Controllers\Api;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Requests\CommentValidator;
use App\Models\hl_user;
use App\Models\hl_report;
use GatewayWorker\Lib\Gateway;

class ReportController extends Controller
{
    /*
    * 依赖注入
    */
    protected $reportModel;
    public function __construct()
    {
       $this->reportModel=new hl_report();
    }

    //举报类型列表
    public function getReportCate(Request $request){
        $list=$this->cate;
        return $this->rtJson($list);
    }

    /*
     * 提交举报
     * topic_id 主题id(文章id/视频id/评论id/回复id)
     * report_cate 主题类型 1：文章；2：视频，3：评论，4回复
     * reason 原因
     * author_id 作者
     * to_user_id 被举报人id
     * user_id 举报人id
     * report_type 类型
     */
    public function submitReport(Request $request){
        $user_info = $request->user_info;
        $param['user_id']= $user_info['user_id'];
        $param['to_user_id']=$request->input('to_user_id','');//被举报人
        $param['author_id']=$request->input('author_id','');//作者
        $param['report_type']=$request->input('report_type','');//举报类型
        $param['topic_id']=$request->input('topic_id','');//举报资源id
        $param['topic_type']=$request->input('report_cate','');//举报资源类型
        $reason=$request->input('reason','');//举报理由
        $param['author_id']=$param['to_user_id'];
        if(!$param['user_id']){
            return $this->rtJsonError(102);
        }
        $validator = new CommentValidator();
        $vol = $validator->ruleReport($param);
        if($vol['code']!=200){
            return $this->rtJsonError($vol['code'], $vol['msg'][0]);
        }

        $userInfo=hl_user::userInfo(['user_id'=>$param['user_id']]);
        $touserInfo=hl_user::userInfo(['user_id'=>$param['to_user_id']]);
        $param['nick_name'] = $userInfo['nick_name'];
        $param['to_nick_name'] = isset($touserInfo['nick_name'])?$touserInfo['nick_name']:'';
        $param['reason'] = $reason?$reason:'';

        $this->reportModel->addReport($param);
        return $this->rtJson_();
    }


}
