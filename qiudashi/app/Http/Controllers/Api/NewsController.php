<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\hl_news;
use App\Models\hl_videos;
use App\Models\CheckConfig;

class NewsController extends Controller
{
    //protected $pagesize = 20;
    protected $channel ='';
    protected $version ='';
    protected $show_comment_model =1;//评论模块

    public function __construct(hl_news $hl_news,CheckConfig $CheckConfig,Request $request) {
        $this->hl_news = $hl_news;
        $this->CheckConfig = $CheckConfig;
        $this->channel=$request->input('channel', '');
        $version = $request->input('version', '');
        $this->version =  (int)str_replace('.', '', $version);
        if($version && $this->channel){
            $congInfo=$this->CheckConfig->show($this->channel,$this->version);
            $this->show_comment_model=$congInfo['show_comment_model'];
        }
    }

    //资讯列表
    public function newsList(Request $request) {
        $cid = $request->input('cid', '');
        $user_info = $request->user_info;
        $user_id = $user_info['user_id'];
        $condition = [];
        $condition['cid'] = $cid;
        $data = $this->hl_news->newsList($condition, $user_id,$this->show_comment_model);
        return $this->rtJson_($data);
    }

    //相关资讯列表
    public function newsListRelated(Request $request) {
        $cid = $request->input('cid', 0);
        $nid = $request->input('nid', 0);
        $data = $this->hl_news->newsListRelated($cid, $nid,$this->show_comment_model);
        return $this->rtJson_($data);
    }

    //相关视频列表
    public function relatedVideos(Request $request) {
        $id = $request->input('id', 0);
        $data = $this->hl_news->relatedVideos($id,$this->show_comment_model);
        return $this->rtJson_($data);
    }

    //视频列表
    public function getVideoList(Request $request){

        $cid = $request->input('cid', 0);
        $page = $request->input('page', 1);
        $pagesize = $request->input('pagesize', 15);
        $user_info = $request->user_info;
        $user_id = $user_info['user_id'];
        $condition = [];
        $condition['cid'] = $cid;

        $data=(new hl_videos())->getVideoList($condition,$user_id,$page,$pagesize,$this->show_comment_model);
        return $this->rtJson_($data);
    }
}
