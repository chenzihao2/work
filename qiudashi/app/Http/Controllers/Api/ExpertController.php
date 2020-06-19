<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ExpertModel;
use App\Models\NewsAttentionModel;
use App\Models\hl_userFollowExpert;

class ExpertController extends Controller
{
    //
    public function __construct() {
        $this->expert_model = new ExpertModel();
    }

    //足篮球专家推荐
    public function soccerBasketRecommend(Request $request) {
        $expert_type = $request->input('expert_type', 1);
        $data = $this->expert_model->soccerBasketRecommend($expert_type);
        $user_id = $request->user_info['user_id'];
        $data = $this->expert_model->assembleDataFollow($data, $user_id);
        return $this->rtJson_($data);
    }

    //专家干货列表
    public function dryStuffList(Request $request) {
        $data = $this->expert_model->dryStuffList();
        return $this->rtJson_($data);
    }
    
    //收藏专家干货
    public function collectDryStuff(Request $request) {
        $nid = $request->input('nid', 0);
        $user_id = $request->user_info['user_id'];
        if (!$nid) {
            return $this->rtJsonError(1000401);
        }
        if (!$user_id) {
            return $this->rtJsonError(102);
        }
        $data = NewsAttentionModel::collectNews($nid, $user_id);
        return $this->rtJson_();
    }

    //专家干货收藏列表
    public function collectedDryStuffList(Request $request) {
        $user_id = $request->user_info['user_id'];
        $newsAttentionModel = new NewsAttentionModel();
        $data = $newsAttentionModel->collectedNewsList($user_id);
        return $this->rtJson_($data);
    }

    //相关干货列表
    public function dryStuffListRelated(Request $request) {
        $cid = $request->input('cid', 0);
        $nid = $request->input('nid', 0);
        $data = $this->expert_model->dryStuffListRelated($cid, $nid);
        return $this->rtJson_($data);
    }

    //专家详情
    public function expertInfo(Request $request) {
        $expert_id = $request->input('expert_id', '');
        $data = $this->expert_model->expertInfo($expert_id);
        $user_id = $request->user_info['user_id'];
        $data = $this->expert_model->assembleDataFollow($data, $user_id);
        return $this->rtJson_($data);
    }

    //红人榜
    public function redMan(Request $request) {
        $data = $this->expert_model->redManApp(); 
        $user_id = $request->user_info['user_id'];
        $data = $this->expert_model->assembleDataFollow($data, $user_id);
        $refresh_time = $this->expert_model->refreshRedMan(1);
        $refresh_time = date('m/d H:i:s', strtotime($refresh_time));
        return $this->rtJson_($data);
    }

    //红人方案
    public function redManResource(Request $request) {
        $user_id = $request->user_info['user_id'];
        $data = $this->expert_model->redManResource($user_id);
        return $this->rtJson_($data);
    }

    //连红榜
    public function redRecord(Request $request) {
        $key_fields = 'recent_red';
        $data = $this->expert_model->expertRank($key_fields);
        $user_id = $request->user_info['user_id'];
        $data = $this->expert_model->assembleDataFollow($data, $user_id);
        return $this->rtJson_($data);
    }

    //高回报
    public function highProfit(Request $request) {
        $key_fields = 'profit_all';
        $data = $this->expert_model->expertRank($key_fields);
        $user_id = $request->user_info['user_id'];
        $data = $this->expert_model->assembleDataFollow($data, $user_id);
        return $this->rtJson_($data);
    }

    //命中率
    public function hitRate(Request $request) {
        $tab_type = $request->input('tab_type', 5);
        $key_fields = 'bet_rate';
        $data = $this->expert_model->expertRank($key_fields, $tab_type);
        $user_id = $request->user_info['user_id'];
        $data = $this->expert_model->assembleDataFollow($data, $user_id);
        return $this->rtJson_($data);
    }

    //全部专家
    public function allExpert(Request $request) {
        $user_id = $request->user_info['user_id'];
        $data = $this->expert_model->allExpert($user_id);
        return $this->rtJson_($data);
    }

    //查看专家命中率
    public function lookExpertHitRate() {
        $data = $this->expert_model->lookExpertHitRate();
        $this->rtJson($data);
    }

    //更新专家相关信息
    public function updateExpertExtra(Request $request) {
        $expert_id = $request->input('expert_id', '');
        $this->expert_model->updateExpertExtra($expert_id);
        $this->rtJson();
    }

    /*
     * 推荐关注专家列表
     */
    public function recommendExpert(Request $request){
        $data = $this->expert_model->redManApp(4);
        $this->rtJson($data);
    }

    /*
    * 批量关注专家
    * $user_id 用户id
    * $expert_ids 专家id
    */
    public function batchFollowExpert(Request $request){
        $user_info = $request->user_info;
        $user_id = $user_info['user_id'];
        $expert_ids=$request->input('expert_ids',[]);
        if(!$user_id || !$expert_ids){
            return $this->rtJsonError(1000107);
        }
        $res=hl_userFollowExpert::batchFollowExpert($user_id,$expert_ids);
        return $this->rtJson_($res);
    }

    /*
     * 单个关注专家
     */
    public function folowExpert(Request $request){
        $user_info = $request->user_info;
        $user_id = $user_info['user_id'];
        $expert_ids=$request->input('expert_id','');
        if(!$user_id || !$expert_ids){
            return $this->rtJsonError(1000107);
        }
        $res=hl_userFollowExpert::folowExpert($user_id,$expert_ids);
        return $this->rtJson_($res);
    }
}
