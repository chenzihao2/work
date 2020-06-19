<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ExpertModel;
use App\Models\hl_news;

class ExpertController extends Controller
{
    //

    //protected $pagesize = 20;
    public function __construct() {
        $this->expert_model = new ExpertModel();
    }

    //后台专家列表
    public function expertList(Request $request) {
        $query = $request->input('query', '');
        $query && $query = json_decode($query, 1);
        $order = $request->input('order', '');
        $order && $order = json_decode($order, 1);
        $pagesize = $request->input('pagesize', '');
        $data = $this->expert_model->expertList($query, $pagesize,$order);
        $this->rtJson($data);
    }

    //足篮球推荐
    public function recommend(Request $request) {
        $expert_id = $request->input('expert_id', '');
        $bs_recommend = $request->input('bs_recommend', 0);
        if (empty($expert_id)) {
            return $this->rtJsonError(2000301);
        }
        try {
            $this->expert_model->recommend($expert_id, $bs_recommend);
            $this->rtJson();
        } catch (\Exception $e) {
            return $this->rtJsonError($e->getcode(), $e->getmessage());
        }
    }

    //后台红人榜
    public function redMan(Request $request) {
        $pagesize = $request->input('pagesize', '');
        $redMan = $this->expert_model->redMan($pagesize);
        $this->rtJson($redMan);
    }

    //红人榜置顶
    public function setTopRedMan(Request $request) {
        $expert_id = $request->input('expert_id', '');
        $top_level = $request->input('red_top_level', 21);
        if (empty($expert_id)) {
            return $this->rtJsonError(2000301);
        }
        if ($top_level > 21) {
            return $this->rtJsonError(2000304);
        }
        if ($top_level == 0) {
            $top_level = 21;
        }
        $this->expert_model->setTopRedMan($expert_id, $top_level);
        $this->rtJson();
    }

    //红人榜展示标签选择
    public function setShowRedMan(Request $request) {
        $expert_id = $request->input('expert_id', '');
        $red_man_show = $request->input('red_man_show', 0);
        if (empty($expert_id)) {
            return $this->rtJsonError(2000301);
        }
        if (!in_array($red_man_show, [0, 1, 2, 3])) {
            return $this->rtJsonError(2000305);
        }
        $this->expert_model->setShowRedMan($expert_id, $red_man_show);
        $this->rtJson();
    }

    //专家名字列表
    public function expertNameList(Request $request) {
        $data = $this->expert_model->expertNameList();
        $this->rtJson($data);
    }

    //所有专家列表
    public function expertAllList(){
        $data = $this->expert_model->expertAllList();
        $this->rtJson($data);
    }

    //专家干货
    public function expertDryStuff(Request $request) {
        $query = $request->input('query', '');
        $pagesize = $request->input('pagesize', 20);
        $query = json_decode($query, 1);
        $query['article_source'] = 'expert';
        $data = hl_news::getNews($query, $pagesize, 1);
        return $this->rtJson($data);
    }

    //置顶专家干货
    public function setTopDryStuff(Request $request) {
        $nid = $request->input('nid', '');
        $top_level = $request->input('dry_top_level', 10);
        if (empty($nid)) {
            return $this->rtJsonError(2000306);
        }
        if ($top_level > 3 && $top_level != 10) {
            return $this->rtJsonError(2000307);
        }
        if ($top_level == 0) {
            $top_level = 10;
        }
        hl_news::setTopDryStuff($nid, $top_level);
        $this->rtJson();
    }

    //刷新红人榜数据
    public function refreshRedMan(Request $request) {
        $time = $request->input('time', 0);
        $data = $this->expert_model->refreshRedMan($time);
        $this->rtJson($data);
    }
}
