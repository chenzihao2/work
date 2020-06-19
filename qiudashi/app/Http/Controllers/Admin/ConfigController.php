<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CheckConfig;
use App\Models\hl_config;
use App\Respository\FaceUtility;

class ConfigController extends Controller
{
    //
    public function __construct() {
        $this->CheckConfig = new CheckConfig();
        $this->utility = new FaceUtility();
    }

    //渠道展示控制
    public function show(Request $request) {
        $channel = $request->input('channel', 'dev');
        $version = $request->input('version', '2.2.6');
        $version =  (int)str_replace('.', '', $version);
        $data = $this->CheckConfig->show($channel, $version);
        $this->rtJson($data);
    }

    //修改配置
    public function editShow(Request $request) {
        $data = [];
        $data['id'] = $request->input('id', '');
        if (!$data['id']) {
            return $this->rtJsonError(2000201);
        }
        $data['bottom'] = $request->input('bottom', []);
        $data['toptab'] = $request->input('toptab', []);
        $data['pay'] = $request->input('pay', 1);
        $data['match'] = $request->input('match', 1);
        $data['show_comment_model'] = $request->input('show_comment_model', 1);
        $data['bindmobile'] = $request->input('bindmobile', 1);
        $res = $this->CheckConfig->editShow($data);
        $this->rtJson();
    }

    //查看配置信息
    public function showInfo(Request $request) {
        $id = $request->input('id', '');
        if (!$id) {
            return $this->rtJsonError(2000201);
        }
        $data = $this->CheckConfig->showInfo($id);
        $this->rtJson($data);
    }

    //配置列表
    public function showList(Request $request) {
        $channel = $request->input('channel', '');
        $version = $request->input('version', '');
        $pagesize = $request->input('pagesize', 20);
        $condition = ['channel' => $channel, 'version' => $version, 'pagesize' => $pagesize];
        $data = $this->CheckConfig->showList($condition);
        return $this->rtJson_($data);
    }

    //处理base64
    //
    public function dealBase64(Request $request) {
        $resource = $request->input('resource', '');
        if (!$resource) {
            return $this->rtJsonError(2000202);
        }
        try {
            $image_url = $this->utility->dealBase64($resource);
        } catch (\Exception $e) {
            return $this->rtJsonError($e->getcode(), $e->getMessage());
        }
        $this->rtJson($image_url);
    }


    /*
      * 隐藏文章评论模块
      *$status 1开启，0关闭
      */
    public function setNewsConfig(Request $request){

        $status = $request->input('status', 1);
        $id=1;
        $data['value']=$status;
        hl_config::updateConfig([1,2],$data);
        $this->CheckConfig->where('show_comment_model','!=',$status)->update(['show_comment_model'=>$status]);
        $this->rtJson();
    }
    /*
      * 隐藏视频评论模块
      *$status 1开启，0关闭
      */
    public function setVideoConfig(Request $request){

        $status = $request->input('status', 1);
        $id=2;
        $data['value']=$status;
        hl_config::updateConfig([1,2],$data);
        $this->CheckConfig->where('show_comment_model','!=',$status)->update(['show_comment_model'=>$status]);
        $this->rtJson();
    }
    /*
     * 获取文章评论模块配置
     */
    public function getNewsConfig(Request $request){
        $id=1;
        $res=hl_config::configInfo($id);
        $this->rtJson($res);
    }
    /*
    * 获取视频评论模块配置
    */
    public function getVideoConfig(Request $request){
        $id=2;
        $res=hl_config::configInfo($id);
        $this->rtJson($res);
    }





}
