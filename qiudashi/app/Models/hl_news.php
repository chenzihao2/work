<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Respository\FaceUtility;
use App\Models\hl_videos;
use App\Models\hl_order;
use App\Models\hl_fabulous;
use App\Models\hl_config;
use App\Models\CheckConfig;

use App\Respository\CommentRespository;
class hl_news extends Model
{
    //
    protected $connection = 'mysql_origin';
    protected $table = 'hl_news';
    protected $pagesize = 20;
    protected $divisor = 5;
    const DEFAULT_DRY_TOP_LEVEL = 10;
    public $timestamps = false;

    public function __construct() {
        $this->utility = new FaceUtility();
        $this->hl_videos = new hl_videos();
        $this->hl_order = new hl_order();
        $this->hl_fabulous = new hl_fabulous();


    }

    //查询资讯
    public static function getNews($query, $pagesize, $is_admin = 0) {
        $fields = ['n.*', 'c.name as cname'];
        if (isset($query['article_source']) && $query['article_source'] == 'expert') {
            $fields = ['n.*', 'e.expert_name'];
        }
        $query_builder =  self::select($fields);
        $query_builder->from('hl_news as n');
        if (isset($query['article_source']) && $query['article_source'] == 'expert') {
            $query_builder->leftJoin('hl_user_expert as e', 'n.cid', '=', 'e.expert_id');
        } else {
            $query_builder->leftJoin('hl_category as c', 'n.cid', '=', 'c.id');
        }
        $news_related = 0;
        if (isset($query['news_related']) && $query['news_related']) {
            unset($query['news_related']);
            $news_related = 1;
        }
        if ($query) {
            foreach ($query as $k => $v) {
                if ($v == '') {
                    continue;
                }
                if (in_array($k, ['title', 'target'])) {
                    $v = '%' . $v . '%';
                    $query_builder->where('n.' . $k, 'like', $v);
                    continue;
                }
                if (in_array($k, ['start_time', 'end_time'])) {
                    if ($k == 'start_time') {
                        $query_builder->where('n.create_time', '>', $v);
                    }
                    if ($k == 'end_time') {
                        $query_builder->where('n.create_time', '<=', $v);
                    }
                    continue;
                }
                if (isset($query['article_source']) && $query['article_source'] == 'expert') {
                    if(in_array($k, ['nid']) && !$is_admin) {
                        $query_builder->where('n.' . $k, '!=', $v);
                        continue;
                    }
                    if (in_array($k, ['nids']) && !$is_admin) {
                        $query_builder->whereIn('n.nid', $v);
                        continue;
                    }
                }
                if ($news_related) {
                    if(in_array($k, ['nid']) && !$is_admin) {
                        $query_builder->where('n.' . $k, '!=', $v);
                        continue;
                    }
                }
                $query_builder->where('n.' . $k, $v);
            }
        }
        if (!$is_admin) {
            $query_builder->where('n.status', 1);
        }
        if (!isset($query['article_source'])) {
            $query_builder->where('c.deleted', 0);
            $query_builder->orderBy('n.is_recommend', 'DESC');
            //$query_builder->orderBy('n.create_time', 'DESC');
            $query_builder->orderBy('n.nid', 'DESC');
        } else {
            $query_builder->orderBy('n.dry_top_level', 'ASC');
            //$query_builder->orderBy('n.create_time', 'DESC');
            $query_builder->orderBy('n.nid', 'DESC');
        }
        $data = $query_builder->paginate($pagesize)->toArray();
        if($data){
            foreach($data['data'] as &$v){
                $v['commentCount']=(new CommentRespository())->topicCount($v['nid'],1);
            }
        }
        //var_dump($query_builder->toSql());
        return $data;
    }

    public function newsListRelated($cid, $nid,$show_comment_model=1) {
        $query = ['news_related' => 1];
        if ($cid) {
            $query['cid'] = $cid;
        }
        if ($nid) {
            $query['nid'] = $nid;
        }
        $data = self::getNews($query, 2);
        foreach ($data['data'] as $k => $v) {
            $data['data'][$k]['show_comment_model']=$show_comment_model;
            $data['data'][$k]['create_time'] = $this->utility->friendlyDate(strtotime($v['create_time']));
        }
        return $data;

    }

    //推荐
    public static function recommend($nid, $r_level) {
        if ($r_level == 2) {
            self::where('is_recommend', 2)->update(['is_recommend' => 1]);
        }
        return self::where('nid', $nid)->update(['is_recommend' => $r_level]);
    }

    //查询资讯-app端
    public function newsList($condition, $user_id = 0,$show_comment_model=1) {
        $query = [];
        $query['status'] = 1;
        if ($condition['cid']) {
            $query['cid'] = $condition['cid'];
        }
        //$query['article_source'] = 0;
        $query_builder =  self::select();
        foreach ($query as $k => $v) {
            $query_builder->where($k, $v);
        }
        $query_builder->where('article_source', '<>', 'expert');
        $query_builder->where('source', '<>', 3);
        $query_builder->orderBy('is_recommend', 'DESC');
        $query_builder->latest('create_time');
        $news = $query_builder->paginate($this->pagesize)->toArray();
        //var_dump($query_builder->toSql());die;
        $except = [];
        foreach($news['data'] as &$v){
            $v['commentCount']=(new CommentRespository())->topicCount($v['nid'],1);//评论数量
            $v['show_comment_model'] = $show_comment_model;
        }
        foreach ($news['data'] as $k => $item) {
            $news['data'][$k]['is_buy'] = $this->hl_order->checkBuy($user_id, $item['nid'], 3);
            $tmp_new_fabulous = [];
            $tmp_new_fabulous = $this->hl_fabulous->isFabulous($user_id, $item['nid'], 1);
            $news['data'][$k]['is_fabulous'] = $tmp_new_fabulous['is_fabulous'];
            $news['data'][$k]['fabulous_id'] = $tmp_new_fabulous['fabulous_id'];


            $video = [];
            $kk = $k + 1;
            if (!$condition['cid']) {
                if ($kk > 0 && ($kk%$this->divisor) == 0) {
                    $video = $this->hl_videos->getRandomVideo($except,$show_comment_model);
                    $video['is_buy'] = $this->hl_order->checkBuy($user_id, $video['id'], 4);
                    $video['commentCount']=(new CommentRespository())->topicCount($video['id'],2);//评论数量
                    $tmp_video_fabulous = [];
                    $tmp_video_fabulous = $this->hl_videos->isFabulous($user_id, $video['id']);
                    $video['is_fabulous'] = $tmp_video_fabulous['is_fabulous'];
                    $video['fabulous_id'] = $tmp_video_fabulous['fabulous_id'];
                    array_splice($news['data'], $kk + count($except), 0, [$video]);
                    $except[] = $video['id'];
                }
            }
        }
        $total = $news['total'];
        $total = bcdiv($total, $this->divisor);
        $news['total'] += $total;
        return $news;
    }

    //修改点赞数
    public static function modifyFabulous($id, $action = 'add') {
        if ($action == 'add') {
            return self::where('nid', $id)->increment('fabulous');
        }
        if ($action == 'sub') {
            $info = self::select('fabulous')->where('nid', $id)->first();
            if ($info['fabulous'] > 0) {
                return self::where('nid', $id)->decrement('fabulous');
            }
        }
        return true;

    }

    //随机三个视频
    public function relatedVideos($id,$show_comment_model=1) {
        $data = $this->hl_videos->where('id', '<>', $id)->inRandomOrder()->take(3)->get()->toArray();
        foreach($data as &$v){
            $v['show_comment_model']=$show_comment_model;
        }
        return $data;
    }

    public static function setTopDryStuff($nid, $top_level) {
        if ($top_level != self::DEFAULT_DRY_TOP_LEVEL) {
            self::where('dry_top_level', $top_level)->update(['dry_top_level' => self::DEFAULT_DRY_TOP_LEVEL]);
        }
        self::where('nid', $nid)->update(['dry_top_level' => $top_level]);
    }

    //修改文章
    public static function updateNews($nid,$data){
        $data['modify_time']=time();
        return self::where('nid', $nid)->update($data);
    }

    public function getCreateTimeAttribute($value) {
        if (is_int($value)) {
            return date('Y-m-d H:i:s', $value);
        }
        return $value;
    }

    //获取资讯详情
    public function getNewsInfo($nid){
        $info= self::where('nid', $nid)->first();
        if($info){
            $info=$info->toArray();
        }
        return $info;
    }

    public function getModifyTimeAttribute($value) {
        return date('Y-m-d H:i:s', $value);
    }

    public function getMoneyAttribute($value) {
        $this->utility = new FaceUtility();
        return $this->utility->ncPriceFen2Yuan($value);;
    }

    public function getIconAttribute($value) {
        return explode(',', $value);
    }

    public function getDryTopLevelAttribute($value) {
        if ($value == self::DEFAULT_DRY_TOP_LEVEL) {
            return 0;
        }
        return $value;
    }



}
