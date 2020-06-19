<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Respository\FaceUtility;
use Illuminate\Support\Facades\DB;
use App\Respository\CommentRespository;
use App\Models\hl_order;
class hl_videos extends Model
{
    //
    protected $connection = 'mysql_origin';
    protected $table = 'hl_news_video';
    protected $fabulous_table = 'hl_video_attention';
    public $timestamps = false;

    public function __construct() {
        $this->utility = new FaceUtility();
        $this->hl_order=new hl_order();

    }

    //查询
    public  function getVideos($query, $pagesize) {
        $query_builder =  self::select('n.*', 'c.id as cid', 'c.name as cname');
        $query_builder->from('hl_news_video as n');
        $query_builder->leftJoin('hl_category as c', 'n.cid', '=', 'c.id');
        $query['deleted'] = 0;
        if ($query) {
            foreach ($query as $k => $v) {
                if ($v === '') {
                    continue;
                }
                if (in_array($k, ['title', 'target'])) {
                    $v = '%' . trim($v) . '%';
                    $query_builder->where('n.' . $k, 'like', $v);
                    continue;
                }
                if (in_array($k, ['start_time', 'end_time'])) {
                    $v = bcdiv($v, 1000);
                    if ($k == 'start_time') {
                        $query_builder->where('n.create_time', '>', $v);
                    }
                    if ($k == 'end_time') {
                        $query_builder->where('n.create_time', '<=', $v);
                    }
                    continue;
                }
                $query_builder->where('n.' . $k, $v);
            }
        }
        $query_builder->orderBy('n.create_time', 'DESC');

        $data=$query_builder->paginate($pagesize);
        if($data){
            $data=$data->toArray();
            foreach($data['data'] as &$v){
                $v['commentCount']=(new CommentRespository())->topicCount($v['id'],2);
                $v['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
                $v['money']=$this->utility->ncPriceFen2Yuan($v['money']);
            }
        }
        //var_dump($query_builder->toSql());
        return $data;
    }

    //编辑| 更新
    public function editVideo($query) {
        $query['title'] = addslashes($query['title']);
        $query['img_url'] = addslashes($query['image']);
        $query['video_url'] = addslashes($query['video']);
        $query['is_pay'] = intval($query['is_pay']);
        $query['create_time'] = $query['modify_time'] = time();
        $query['money'] = $this->utility->ncPriceYuan2Fen($query['money']);
        $id = intval($query['id']);
        unset($query['image'], $query['video'], $query['id']);
        if ($id) {
            self::updateOrInsert(['id' => $id], $query);
        } else {
            $id = self::insertGetId($query);
        }
        return ['filename' => $query['video_url'], 'id' => $id];
    }

    //随机一个视频
    public function getRandomVideo($except = [],$show_comment_model=1) {
        $data = self::inRandomOrder()->first()->toArray();
        if($data){
            $data['show_comment_model']=$show_comment_model;
        }
        if (in_array($data['id'], $except)) {
            return $this->getRandomVideo($except);
        }
        return $data;
    }

    //视频点赞 || 或取消
    public function fabulous($user_id, $video_id) {
        $info = DB::connection($this->connection)->table($this->fabulous_table)->where('user_id', $user_id)->where('video_id', $video_id)->first();
        if (!$info) {
            $insert_data = ['user_id' => $user_id, 'video_id' => $video_id, 'fabulous' => 1];
            DB::connection($this->connection)->table($this->fabulous_table)->insert($insert_data);
            return self::where('id', $video_id)->increment('fabulous');
        }
        $info = (array)$info;
        if ($info['fabulous']) {
            DB::connection($this->connection)->table($this->fabulous_table)->where('id', $info['id'])->update(['fabulous' => 0]);
            return self::where('id', $video_id)->decrement('fabulous');
        } else {
            DB::connection($this->connection)->table($this->fabulous_table)->where('id', $info['id'])->update(['fabulous' => 1]);
            return self::where('id', $video_id)->increment('fabulous');
        }

    }


    //是否已点赞
    public function isFabulous($user_id, $video_id) {
        $result = ['fabulous_id' => 0, 'is_fabulous' => 0];
        if (!$user_id || !$video_id) {
            return $result;
        }
        $info = DB::connection($this->connection)->table($this->fabulous_table)->where('user_id', $user_id)->where('video_id', $video_id)->where('fabulous', 1)->first();
        if (!$info) {
            return $result;
        }
        $result['fabulous_id'] = $info->id;
        $result['is_fabulous'] = 1;
        return $result;
    }
    //获取视频详情
    public function getVideoInfo($id){
        $info= self::where('id', $id)->first();
        if($info){
            $info=$info->toArray();
            $info['money']=$this->utility->ncPriceFen2Yuan($info['money']);
        }
        return $info;
    }

    //APP视频列表
    public function getVideoList($condition,$user_id=0,$page=1,$pagesize=10,$show_comment_model=1){
        $query = [];
        $query['status'] = 1;
        if ($condition['cid']) {
            $query['cid'] = $condition['cid'];
        }
        $model=self::where($query);
        $count=$model->count();
        $totalPage = ceil($count/$pagesize); //总页数
        $startPage=($page-1)*$pagesize;//开始记录
        $data =$model->orderBy('id', 'DESC')->offset($startPage)->limit($pagesize)->get()->toArray();
        foreach($data as &$v){
            $v['show_comment_model'] = $show_comment_model;
            $v['is_buy'] = $this->hl_order->checkBuy($user_id, $v['id'], 4);
            $v['commentCount']=(new CommentRespository())->topicCount($v['id'],2);//评论数量
            $result=$this->isFabulous($user_id, $v['id']);//是否点赞
            $v['is_fabulous']=$result['is_fabulous'];
            $v['fabulous_id']=$result['fabulous_id'];
            $v['money']=$this->utility->ncPriceFen2Yuan($v['money']);
        }
        return ['total'=>$count,'data'=>$data];
    }


}
