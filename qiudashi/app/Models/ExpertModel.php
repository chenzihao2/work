<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Respository\FaceUtility;
use App\Models\ResourceModel;
use App\Models\ResourceScheduleModel;
use App\Models\hl_news;
use App\Models\hl_order_refund;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ExpertModel extends Model
{
    //
    protected $connection = 'mysql_origin';
    protected $table = 'hl_user_expert';
    protected $extra_table = 'hl_user_expert_extra';
    protected $subscribe_table = 'hl_user_expert_subscribe';
    protected $user_follow_expert_table = 'hl_user_follow_expert';
    protected $pagesize = 20;
    protected $primaryKey = 'expert_id';
    protected $divisor = 5;
    protected $record_text = '近%s中%s';
    protected $recent_ten_text = '%s红%s走%s黑';
    protected $default_red_top_level = 21;
    protected $default_bs_recommend = 15;
    protected $red_man_snapshot = 'red_man_snapshot';
    protected $redman_refresh_time = 'redman_refresh_time';
    public $timestamps = false;

    public function __construct() {
        $this->utility = new FaceUtility();
        $this->resource = new ResourceModel();
        $this->resource_schedule = new ResourceScheduleModel();
        $this->hl_order_refund = new hl_order_refund();
    }

    public function expertList($query, $pagesize,$order='') {
        $fields = ['eu.expert_id', 'eu.platform', 'eu.expert_name', 'eu.headimgurl',
            'eu.push_resource_time', 'eu.expert_status', 'eu.expert_type', 'e.subscribe_num',
            'e.follow_num', 's.subscribe_price','eu.is_recommend','eu.is_placement','eu.is_wx_placement','eu.is_wx_recommend','eu.bs_recommend','e.recent_ten','t.income'
        ];
        $query_builder = self::select($fields);
        $query_builder->from($this->table . ' as eu');
        $query_builder->leftJoin($this->extra_table . ' as e', 'e.expert_id', '=', 'eu.expert_id');
        $query_builder->leftJoin($this->subscribe_table . ' as s', function ($join) {
            $join->on('eu.expert_id', '=', 's.expert_id')
                ->where('s.length_day', 30);
        });
        $query_builder->leftJoin(DB::raw('(select expert_id,sum(order_amount) as income from hl_order where order_type<100 and order_status=1 GROUP BY expert_id) t '),'eu.expert_id','=','t.expert_id');

        if ($query) {
            foreach ($query as $k => $v) {
                if ($v === '') {
                    continue;
                }
                if ($k == 'expert_name') {
                    if ($v != '') {
                        $query_builder->where('eu.' . $k, 'like', '%'.$v.'%');
                    }
                    continue;
                }
                if($k == 'platform'){
                    $query_builder->whereIn('eu.' . $k,[0,$v]);
                    continue;
                }
                if ($v != '' || $v === 0) {
                    $query_builder->where('eu.' . $k, $v);
                }
            }
        }
        !$pagesize && $pagesize = $this->pagesize;
        if($order){
            foreach($order as $k=>$v){
                if($v){
                    if($k=='income'){
                        $query_builder->orderBy('t.' . $k,$v);
                    }else{
                        $query_builder->orderBy('e.' . $k,$v);
                    }

                }
            }
        }
        // $query_builder->oldest('bs_recommend');
        //$query_builder->latest('expert_id');
        $query_builder->orderBy('eu.bs_recommend','asc');
        $query_builder->orderBy('eu.expert_id','desc');

        $data = $query_builder->paginate($pagesize)->toArray();
        //var_dump($query_builder->toSql());die;

        foreach ($data['data'] as &$item) {
            //$refundAmount=$this->hl_order_refund->refundAmount($item['expert_id']);
            $item['income']=$this->utility->ncPriceFen2YuanInt($item['income']);
        }
        return $data;
    }

    public function recommend($expert_id, $bs_recommend) {
        if ($bs_recommend == 0) {
            $bs_recommend = $this->default_bs_recommend;
        }
        $expert_info = $this->find($expert_id);
        if ($expert_info['expert_status'] != 1) {
            throw new \Exception('', 2000302);
        }
        if ($bs_recommend > 15) {
            throw new \Exception('', 2000303);
        }
        if ($expert_info->expert_type == 1 && $bs_recommend > 7 && $bs_recommend != $this->default_bs_recommend) {
            throw new \Exception('', 2000308);
        }
        if ($expert_info->expert_type == 2 && $bs_recommend < 8 && $bs_recommend != $this->default_bs_recommend) {
            throw new \Exception('', 2000309);
        }
        $expert_info->bs_recommend = $bs_recommend;
        if ($bs_recommend != $this->default_bs_recommend) {
            self::where('bs_recommend', $bs_recommend)->update(['bs_recommend' => $this->default_bs_recommend]);
        }
        return $expert_info->save();
    }

    public function redMan($pagesize) {
        $fields = ['eu.expert_id', 'eu.expert_name', 'eu.headimgurl', 'eu.red_top_level', 'e.recent_red', 'e.recent_record', 'e.red_man_show'];
        $query_builder = self::select($fields);
        $query_builder->from($this->table . ' as eu');
        $query_builder->leftJoin($this->extra_table . ' as e', 'e.expert_id', '=', 'eu.expert_id');
        !$pagesize && $pagesize = $this->pagesize;
        //$query_builder->where('eu.red_top_level', '>', 0);
        //$query_builder->where('eu.red_top_level', '<', 21);
        $query_builder->where('eu.expert_status', 1);
        $query_builder->oldest('eu.red_top_level');
        $query_builder->latest('e.recent_red');
        $query_builder->oldest('eu.expert_id');
        $data = $query_builder->paginate($pagesize);
        foreach ($data as $k => $v) {
            $cache = Redis::hget($this->red_man_snapshot, $v['expert_id']);
            if ($cache) {
                $cache_info = json_decode($cache, 1);
                $data[$k]['recent_red'] = $cache_info['recent_red'];
                $data[$k]['recent_record'] = $cache_info['recent_record'];
                continue;
            }
            if ($v['recent_record']) {
                $tmps = [];
                $tmp = json_decode($v['recent_record'], 1);
                if ($tmp) {
                    foreach ($tmp as $kk => $item) {
                        $tmps[$kk] = sprintf($this->record_text, $item[1], $item[0]);
                    }
                    $data[$k]['recent_record'] = $tmps;
                } else {
                    $data[$k]['recent_record'] = [];
                }
            }
        }
        return $data;
    }

    public function setTopRedMan($expert_id, $top_level) {
        if ($top_level != $this->default_red_top_level) {
            self::where('red_top_level', $top_level)->update(['red_top_level' => $this->default_red_top_level]);
        }
        self::where('expert_id', $expert_id)->update(['red_top_level' => $top_level]);
    }

    public function setShowRedMan($expert_id, $red_man_show) {
        DB::connection($this->connection)->table($this->extra_table)->where('expert_id', $expert_id)->update(['red_man_show' => $red_man_show]);
    }

    public function getExpertResource($expert_info, $order = ['release_time' => 'desc']) {
        if (!is_array($expert_info)) {
            $expert_info = $this->find($expert_info)->toArray();
        }
        $expert_id = $expert_info['expert_id'];
        $platform = $expert_info['platform'];
        $resource_query = ['expert_id' => $expert_id, 'resource_status' => 1];
        if ($platform != 0) {
            $platform_key = ($platform == 1) ? 'bd_display' : 'wx_display';
            $resource_query[$platform_key] = 1;
        }
        return $this->resource->getAllResourceByQuery($resource_query, $order);
    }

    public function getAllExpert() {
        
    }

    public function soccerBasketRecommend($expert_type) {
        $limit = [];
        if ($expert_type == 1) {
            $limit = [1, 7];
        } else {
            $limit = [8, 14];
        }
        $fields = ['eu.expert_id', 'eu.expert_name', 'eu.headimgurl', 'eu.bs_recommend', 'e.max_bet_record'];
        $query_builder = self::select($fields);
        $query_builder->from($this->table . ' as eu');
        $query_builder->leftJoin($this->extra_table . ' as e', 'e.expert_id', '=', 'eu.expert_id');
        $query_builder->where('eu.expert_status', 1);
        return $query_builder->where('bs_recommend', '>=', $limit[0])->where('bs_recommend', '<=', $limit[1])->oldest('bs_recommend')->get();
    }

    public function dryStuffList() {
        $query['article_source'] = 'expert';
        $data = hl_news::getNews($query, $this->pagesize);
        if ($data && is_array($data)) {
            foreach ($data['data'] as $k => $v) {
                $data['data'][$k]['create_time'] = $this->utility->friendlyDate(strtotime($v['create_time']));
            }
        }
        return $data;
    }
    public function dryStuffListRelated($cid, $nid) {
        $query['article_source'] = 'expert';
        $cid = 0;
        if ($cid) {
            $query['cid'] = $cid;
        }
        if ($nid) {
            $query['nid'] = $nid;
        }
        $data = hl_news::getNews($query, 2);
        foreach ($data['data'] as $k => $v) {
            $data['data'][$k]['create_time'] = $this->utility->friendlyDate(strtotime($v['create_time']));
        }
        return $data;

    }

    public function expertInfo($expert_id) {
        if (empty($expert_id)) {
            return [];
        }
        $query_builder = self::select('eu.*', 'e.*', 's.*', 'eu.expert_id as expert_id', 'eu.desc as desc');
        $query_builder->from($this->table . ' as eu');
        $query_builder->leftJoin($this->extra_table . ' as e', 'e.expert_id', '=', 'eu.expert_id');
        $query_builder->leftJoin($this->subscribe_table . ' as s', function ($join) {
            $join->on('eu.expert_id', '=', 's.expert_id')
                ->where('s.length_day', 30);
        });
        $data = $query_builder->where('eu.expert_id', $expert_id)->first();
        $recent_record = json_decode($data['recent_record'], 1);
        $recent_records = '';
        $lately_red = [];
        if (isset($recent_record[1]) && $recent_record[1]) {
            $lately_red[] = $recent_record[1][1];
            $lately_red[] = $recent_record[1][0];
            $recent_records = sprintf($this->record_text, $recent_record[1][1], $recent_record[1][0]);
        }
        $data['lately_red'] = $lately_red;
        $data['recent_record'] = $recent_records;
        return $data;
    }

    public function expertNameList() {
        $query_builder = self::select(['eu.expert_id', 'eu.expert_name', 'eu.platform', 'e.recent_red', 'e.recent_record']);
        $query_builder->from($this->table . ' as eu');
        $query_builder->leftJoin($this->extra_table . ' as e', 'e.expert_id', '=', 'eu.expert_id');
        $query_builder->where('eu.expert_status', 1);
        $query_builder->latest('expert_id');
        $data = $query_builder->get()->toArray();
        foreach ($data as $k => $v) {
            if ($v['recent_record']) {
                $recent_record = json_decode($v['recent_record'], 1);
                if (isset($recent_record[1])) {
                    $data[$k]['recent_record'] = sprintf($this->record_text, $recent_record[1][1], $recent_record[1][0]);
                }
            }
            $data[$k]['recent_red'] = $v['recent_red'] . '连红';
        }
        return $data;
    }

    public function redManApp($pagesize = 0) {
        $fields = ['eu.expert_id', 'eu.expert_name', 'eu.headimgurl', 'eu.identity_desc', 'eu.red_top_level', 'e.recent_red', 'e.recent_record', 'e.red_man_show'];
        $query_builder = self::select($fields);
        $query_builder->from($this->table . ' as eu');
        $query_builder->leftJoin($this->extra_table . ' as e', 'e.expert_id', '=', 'eu.expert_id');
        !$pagesize && $pagesize = $this->pagesize;
        $query_builder->oldest('eu.red_top_level');
        $query_builder->latest('e.recent_red');
        $query_builder->oldest('eu.expert_id');
        $query_builder->where('eu.red_top_level', '<', $this->default_red_top_level);
        $query_builder->where('eu.red_top_level', '>', 0);
        $query_builder->where('eu.expert_status', 1);
        if ($pagesize && $pagesize != $this->pagesize) {
            $data = $query_builder->paginate($pagesize);
        } else {
            $data = $query_builder->get();
        }
        foreach ($data as $k => $v) {
            $cache = Redis::hget($this->red_man_snapshot, $v['expert_id']);
            if ($cache) {
                $cache_info = json_decode($cache, 1);
                $data[$k]['recent_red'] = $cache_info['recent_red'];
                $data[$k]['recent_record'] = $cache_info['recent_record'];
                $data[$k]['show_content'] = $cache_info['recent_red'] . '连红';
                if ($v['red_man_show']) {
                    if ($cache_info['recent_record'] && $cache_info['recent_record'][$v['red_man_show']]) {
                        $data[$k]['show_content'] = $cache_info['recent_record'][$v['red_man_show']];
                    }
                }
                continue;
            }
            if ($v['recent_record']) {
                $tmps = [];
                $tmp = json_decode($v['recent_record'], 1);
                foreach ($tmp as $kk => $item) {
                    $tmps[$kk] = sprintf($this->record_text, $item[1], $item[0]);
                }
                $data[$k]['recent_record'] = $tmps;
            }
            $data[$k]['show_content'] = $v['recent_red'] . '连红';
            if ($v['red_man_show']) {
                if ($tmps && $tmps[$v['red_man_show']]) {
                    $data[$k]['show_content'] = $tmps[$v['red_man_show']];
                }
            }
        }
        return $data;
    }

    public function expertRank($key_fields, $tab_type = 5) {
        $select_fields = 'e.' . $key_fields;
        $fields = ['eu.expert_id', 'eu.expert_name', 'eu.headimgurl', 'eu.identity_desc', $select_fields];
        if ($key_fields == 'bet_rate') {
            $start = 1;
            switch($tab_type) {
            case 10:
                $start = 5;
                break;
            //case 20:
            //    $start = 9;
            //    break;
            case 30:
                $start = 9;
                break;
            }
            $select_fields = "(substring(e.bet_rate, $start, 4) + 0) as $key_fields";
            $fields = ['eu.expert_id', 'eu.expert_name', 'eu.headimgurl', 'eu.identity_desc'];
        }
        $query_builder = self::select($fields);
        if ($key_fields == 'bet_rate') {
            $query_builder->selectRaw($select_fields);
        }
        $query_builder->from($this->table . ' as eu');
        $query_builder->leftJoin($this->extra_table . ' as e', 'e.expert_id', '=', 'eu.expert_id');
        $pagesize = $this->pagesize;
        if ($key_fields == 'bet_rate') {
            $query_builder->orderByRaw("(substring(e.bet_rate, $start, 4) + 0) desc");
        } else {
            $query_builder->latest('e.' . $key_fields);
        }
        $query_builder->latest('eu.expert_id');
        if ($key_fields == 'recent_red') {
            $query_builder->where('e.' . $key_fields, '>=', 3);
        }
        if ($key_fields == 'profit_all') {
            $query_builder->where('e.' . $key_fields, '>', 100);
        }
        if ($key_fields == 'bet_rate') {
            $start_time = strtotime('-30 days'); 
            $query_builder->where('eu.push_resource_time', '>', $start_time);
            $query_builder->whereRaw("(substring(e.bet_rate, $start, 4) + 0) >= ?", [600]);
        }
        $query_builder->where('eu.expert_status', 1);
        $data = $query_builder->paginate($pagesize)->toArray();
        foreach ($data['data'] as $k => $v) {
            if ($key_fields == 'recent_red') {
                $data['data'][$k]['recent_red'] = $v['recent_red'] . '连红';
            }
            if ($key_fields == 'bet_rate') {
                $data['data'][$k]['bet_rate'] = (int)bcdiv($v['bet_rate'], 10);
            }
        }
        return $data;
    }

    public function allExpert($user_id) {
        $fields = ['eu.expert_id', 'eu.expert_name', 'eu.headimgurl', 'eu.identity_desc', 'e.profit_all', 'e.max_bet_record', 'e.recent_red'];
        $query_builder = self::select($fields);
        $query_builder->from($this->table . ' as eu');
        $query_builder->leftJoin($this->extra_table . ' as e', 'e.expert_id', '=', 'eu.expert_id');
        $query_builder->where('eu.expert_status', 1);
        $data = $query_builder->get()->toArray();
        foreach ($data as $k => $v) {
            $first_letter = $this->utility->getfirstchar($v['expert_name']);
            $data[$k]['first_letter'] = $first_letter;
            $data[$k]['isFollowExpert'] = $this->userIsFollow($user_id, $v['expert_id']);
            //命中率展示
            //$hit_info = $this->calHitRate($v['expert_id']);
            //if (isset($hit_info['bet_rate'])) {
            //    $bet_rate = $hit_info['bet_rate'];
            //    if (substr($bet_rate, 0, 4)) {
            //        $data[$k]['七场命中率'] = substr($bet_rate, 0, 4) / 10; 
            //    }
            //    if (substr($bet_rate, 4, 4)) {
            //        $data[$k]['十场命中率'] = substr($bet_rate, 4, 4) / 10;
            //    }
            //    if (substr($bet_rate, 8, 4)) {
            //        $data[$k]['二十场命中率'] = substr($bet_rate, 8, 4) / 10;
            //    }
            //    if (substr($bet_rate, 12, 4)) {
            //        $data[$k]['三十场命中率'] = substr($bet_rate, 12, 4) / 10;
            //    }
            //}
            //命中率展示
        }
        $tmp_list = [];
        foreach ($data as $k => $v) {
            $tmp_list[$v['first_letter']][] = $v;
        }
        ksort($tmp_list);
        $special = [];
        if (isset($tmp_list['#'])) {
            $special = $tmp_list['#'];
            unset($tmp_list['#']);
        }
        if ($special) {
            $tmp_list['#'] = $special;
        }
        $datas = [];
        foreach ($tmp_list as $k => $v) {
            $tmp['title'] = $k;
            $tmp['data'] = $v;
            $datas[] = $tmp;
        }
        return $datas;
    }

    public function redManResource($user_id=0) {
        $query_builder = self::select();
        $query_builder->from($this->table . ' as eu');
        $query_builder->leftJoin($this->extra_table . ' as e', 'e.expert_id', '=', 'eu.expert_id');
        $query_builder->where('eu.red_top_level', '<', $this->default_red_top_level);
        $query_builder->where('eu.red_top_level', '>', 0);
        $query_builder->oldest('eu.red_top_level');
        $red_man = $query_builder->get()->toArray();
        $expert_ids = array_column($red_man, 'expert_id');
        foreach ($red_man as $k => $v) {
            $red_man[$v['expert_id']] = $v;
        }
        
        $query_builder = null;
        $query_builder = self::select();
        $query_builder->from('hl_resource as r');
        $query_builder->leftJoin('hl_resource_extra as hr', function($join) {
            $join->on('r.resource_id', '=', 'hr.resource_id');
        });
        $query_builder->where('r.resource_status', 1);
        $query_builder->where('r.is_free', 0);
        $query_builder->where('r.is_over_bet', 0);
        $query_builder->whereIn('r.expert_id', $expert_ids);
        $query_builder->where('hr.bet_status', 0);
        $query_builder->latest('r.release_time');
        $resource = $query_builder->paginate($this->pagesize)->toArray();
        foreach ($resource['data'] as $k => $v) {
            $resource['data'][$k]['create_time_friendly'] = $this->utility->friendlyDate($v['create_time']);
            $resource['data'][$k]['expert'] = $this->expertInfo([$v['expert_id']]);
            if ($resource['data'][$k]['expert']['max_red_num'] < 5) {
                $resource['data'][$k]['expert']['max_red_num'] = 0;
            }
            $resource['data'][$k]['schedule'] = $this->resource->getRelatedMatch($v['resource_id']);
            $resource['data'][$k]['price'] = $this->utility->ncPriceFen2Yuan($v['price']);
            $resource['data'][$k]['price_int'] = $this->utility->ncPriceFen2YuanInt($v['price']);
            $resource['data'][$k]['expert']['isFollowExpert'] = $this->userIsFollow($user_id, $v['expert_id']);
            $resource['data'][$k]['bet_status']=$this->resource->getBetResult($v['resource_id']);


        }

        return $resource; 
    }


    /*
     *所有专家列表
     */
    public function expertAllList(){
        $fields = ['eu.expert_id', 'eu.expert_name', 'eu.headimgurl', 'eu.identity_desc', 'eu.platform', 'e.recent_red', 'e.recent_record', 'e.red_man_show'];
        $query_builder = self::select($fields);
        $query_builder->from($this->table . ' as eu');
        $query_builder->leftJoin($this->extra_table . ' as e', 'e.expert_id', '=', 'eu.expert_id');
        $query_builder->where('eu.expert_status',1);
        $query_builder->orderBy('eu.sort','desc');
        $data = $query_builder->get();
        foreach ($data as $k => $v) {
            if ($v['recent_record']) {
                $tmps = [];
                $tmp = json_decode($v['recent_record'], 1);
                foreach ($tmp as $kk => $item) {
                    $tmps[$kk] = sprintf($this->record_text, $item[1], $item[0]);
                }
                $data[$k]['recent_record'] = $tmps;
            }
            $data[$k]['show_content'] = $v['recent_red'] . '连红';
            if ($v['red_man_show']) {
                if ($tmps && $tmps[$v['red_man_show']]) {
                    $data[$k]['show_content'] = $tmps[$v['red_man_show']];
                }
            }
        }

        return $data;
    }



    //计算最大连红
    private function calMaxRed($expert_info) {
        $order = ['release_time' => 'asc'];
        $resources = $this->getExpertResource($expert_info, $order);
        $max_red_num = $red_num = $i = 0;
        foreach($resources as $k => $item) {
            $bet_status = $this->resource->getBetResult($item['resource_id']);
            if ($bet_status == 1) {
                $i++;
                if ($max_red_num < $i) {
                    $max_red_num = $i;
                }
                if ($k == (count($resources) - 1) && $i > 1) {
                    $red_num++;
                }
            } else {
                if ($i > 1) {
                    $red_num++;
                }
                $i = 0;
            }
        }
        $result =  ['red_num' => $red_num, 'max_red_num' => $max_red_num];
        return $result;
    }

    //计算近期连红
    private function calRecent($expert_info) {
        if (!is_array($expert_info)) {
            $expert_info = $this->find($expert_info)->toArray();
        }
        $expert_id = $expert_info['expert_id'];
        $platform = $expert_info['platform'];
        $resource_query = ['expert_id' => $expert_id, 'resource_status' => 1];
        if ($platform != 0) {
            $platform_key = ($platform == 1) ? 'bd_display' : 'wx_display';
            $resource_query[$platform_key] = 1;
        }
        $tendayago = strtotime('-30 day');
        $now = time();
        $resource_query['release_time'] = [$tendayago, $now];
        $resources = $this->resource->getAllResourceByQuery($resource_query, ['release_time' => 'desc']);
        $recent_red = $i = 0;
        foreach ($resources as $item) {
            $bet_status = $this->resource->getBetResult($item['resource_id']);
            if ($bet_status == 0) {
                continue;
            }
            if ($bet_status == 1) {
                $i++;
                if ($i > $recent_red && $i > 2) {
                    $recent_red = $i;
                }
            } else {
                break;
            }
           //  else {
           //     break;
           //     $i = 0;
           //     if ($recent_red > 2) {
           //        break; 
           //     }
           // }
        }
        $result = ['recent_red' => $recent_red];
        return $result;
    }

    //计算回报率
    public function calProfit($expert_info) {
        $result = ['profit_all' => '', 'profit_rate' => '', 'profit_resource_num' => ''];
        $profit_resource_num = $profit_all = $profit_rate = 0;
        if (!is_array($expert_info)) {
            $expert_info = $this->find($expert_info)->toArray();
        }
        $expert_id = $expert_info['expert_id'];
        $platform = $expert_info['platform'];
        $resource_query = ['expert_id' => $expert_id, 'resource_status' => 1];
        if ($platform != 0) {
            $platform_key = ($platform == 1) ? 'bd_display' : 'wx_display';
            $resource_query[$platform_key] = 1;
        }
        $week_start = mktime(0,0,0,date('m'),date('d')-date('w')+1-7,date('Y'));
        $week_end = mktime(23,59,59,date('m'),date('d')-date('w')+7-7,date('Y'));
        $resource_query['release_time'] = [$week_start, $week_end];
        $resources = $this->resource->getAllResourceByQuery($resource_query, ['release_time' => 'desc'], [0, 30]);
        foreach ($resources as $item) {
            $resource_id = $item['resource_id'];
            $manual_bet_status = 0;
            $manual = (array)DB::connection($this->connection)->table('hl_resource_extra')->where('resource_id', $resource_id)->first();
            if ($manual['bet_status']) {
                $manual_bet_status = $manual['bet_status'];
            }
            $schedule = $this->resource_schedule->getScheduleByResourceId($resource_id); 
            foreach ($schedule as $k => $v) {
                $bet_status = $v['bet_status']; 
                if ($manual_bet_status) {
                    $bet_status = $manual_bet_status;
                }
                if ($bet_status == 0) {
                    continue;
                }
                $recommend_list = explode(',', $v['recommend']);
                $main_recommend = $recommend_list[0];
                $extra_recommend = count($recommend_list) == 2 ? $recommend_list[1] : '';
                $odds = $v[$main_recommend];
                if ($bet_status == 4 || $bet_status == 6) {
                    $odds = $v[$extra_recommend];
                }
                if ($v['lottery_type'] == 2) {
                    $lotteryInfo = (array)DB::connection($this->connection)->table('hl_soccer_lottery')->where('id', $v['lottery_id'])->first();
                    $odds = ($bet_status == 4 || $bet_status == 6) ? $lotteryInfo[$extra_recommend] : $lotteryInfo[$main_recommend];
                }
                $profit = 0;
                switch($bet_status) {
                case 1: //红
                    $profit = floatval($odds) - 1;
                    break;
                case 3: //黑
                    $profit = -1;
                    break;
                case 4: //副推红单
                    $profit = floatval($odds) - 1;
                    break;
                case 5:
                case 6:
                    $profit = (floatval($odds) - 1)/2;   //主推，副推半红单
                    break;
                case 7:
                    $profit = -0.5;   //半黑单
                    break;
                }
                $profit_all += $profit;
            }
            $profit_resource_num++;
        }
        if ($profit_resource_num) {
            $profit_all = $profit_all * 100;
            $profit_rate = ceil($profit_all / $profit_resource_num);
        }
        $result['profit_all'] = $profit_all;
        $result['profit_rate'] = $profit_rate;
        $result['profit_resource_num'] = $profit_resource_num;
        return $result;
    }

    //计算命中率
    private function calHitRate($expert_info) {
        $result = ['recent_record' => ''];
        $bet_rate = [];
        if (!is_array($expert_info)) {
            $expert_info = $this->find($expert_info)->toArray();
        }
        $expert_id = $expert_info['expert_id'];
        $platform = $expert_info['platform'];
        $resource_query = ['expert_id' => $expert_id, 'resource_status' => 1];
        if ($platform != 0) {
            $platform_key = ($platform == 1) ? 'bd_display' : 'wx_display';
            $resource_query[$platform_key] = 1;
        }
        //$start_time = strtotime('-30 days');
        //$resource_query['release_time'] = [$start_time, time()];
        $resources = $this->resource->getResourceByQuery($resource_query, ['release_time' => 'desc'], [0, 30]);
        //var_dump($resources);die;
        if (count($resources) < 5) {
            return $result;
        }
        $bets = $record = [];
        foreach ($resources as $item) {
            $bets[] = $this->resource->getBetResult($item['resource_id']);
        }
        $red_num = 0;
        $recent_ten = ['red' => 0, 'go' => 0, 'black' => 0];
        foreach ($bets as $k => $v) {
            if ($v == 1) {
                $red_num++;
            }
            if ($k > 3) {
                $tmp = [];
                $tmp = [$red_num, $k+1, bcdiv($red_num*100, $k+1, 1)];
                $record[] = $tmp;
                if (in_array($k+1, [5, 10, 30])) {
                    $bet_rate[$k+1] = sprintf("%04d",bcdiv($red_num*1000, $k+1));
                }
            }
            if ($k < 10) {
                switch ($v) {
                case 1:
                    $recent_ten['red']++;
                    break;
                case 2:
                    $recent_ten['go']++;
                    break;
                case 3:
                    $recent_ten['black']++;
                    break;
                }
            }
        }
        $result['recent_ten'] = sprintf($this->recent_ten_text, $recent_ten['red'], $recent_ten['go'], $recent_ten['black']);
        $rates = array_column($record, 2);
        array_multisort($rates, SORT_DESC, $record);
        $max_bet_record = 0;
        if ($record[0]) {
            $max_bet_record = $record[0][2];
        }
        $recent = []; $i = 1;
        foreach ($record as $item) {
            if ($item[2] < 100) {
                $recent[$i] = [$item[0], $item[1], $item[2]];
                $i++;
            }
            if ($i == 4) {
                break;
            }
        }
        $bet_rate = implode('', $bet_rate);
        $result['recent_record'] = json_encode($recent);
        $result['bet_rate'] = $bet_rate;
        $result['max_bet_record'] = round($max_bet_record);
        return $result;
    }

    public function userIsFollow($user_id, $expert_id) {
        if (!$user_id || !$expert_id) {
            return 0;
        }
        $res = DB::connection($this->connection)->table($this->user_follow_expert_table)->where('expert_id', $expert_id)->where('user_id', $user_id)->where('follow_status', 1)->first();
        if ($res) {
            return 1;
        } else {
            return 0;
        }
    }

    public function assembleDataFollow($data, $user_id) {
        $origin = $data;
        if (isset($data['data'])) {
            $origin = $data['data'];
        }
        if (is_object($origin)) {
            $origin = $origin->toArray();
        }
        foreach ($origin as $k => $v) {
            if ($k === 'expert_id') {
                $origin['isFollowExpert'] = $this->userIsFollow($user_id, $v);
                return $origin;
            }
            if (isset($v['expert_id'])) {
                $origin[$k]['isFollowExpert'] = $this->userIsFollow($user_id, $v['expert_id']);
            }
        }
        if (isset($data['data'])) {
            $data['data'] = $origin;
        } else {
            $data = $origin;
        }
        return $data;
    }

    public function refreshRedMan($time = 0) {
        if ($time) {
            return Redis::get($this->redman_refresh_time) ?: '';
        }
        $data = $this->expertAllList();
        foreach ($data as $item) {
            $tmp['recent_red'] = $item['recent_red'];
            $tmp['recent_record'] = $item['recent_record'];
            $expert_id = $item['expert_id'];
            Redis::hset($this->red_man_snapshot, $expert_id, json_encode($tmp));
        }
        $time = date('Y-m-d H:i:s', time());
        Redis::set($this->redman_refresh_time, $time);
    }

    public function updateExpertExtra($expert_id) {
        if (empty($expert_id)) {
            return false;
        }
        $max_red = $this->calMaxRed($expert_id);
        $recent = $this->calRecent($expert_id);
        $hit_rate = $this->calHitRate($expert_id);
        $profit = $this->calProfit($expert_id);
        $data = array_merge($max_red, $recent, $hit_rate, $profit);
        return DB::connection($this->connection)->table($this->extra_table)->where('expert_id', $expert_id)->update($data);
    }

    public function getPushResourceTimeAttribute($value) {
        return date('Y-m-d H:i:s', $value);
    }

    public function getSubscribePriceAttribute($value) {
        $this->utility = new FaceUtility();
        return $this->utility->ncPriceFen2Yuan($value);
    }

    public function getRedTopLevelAttribute($value) {
        if ($value == $this->default_red_top_level) {
            return 0;
        }
        return $value;
    }

    public function getBsRecommendAttribute($value) {
        if ($value == $this->default_bs_recommend) {
            return 0;
        }
        return $value;
    }

    //public function getRecentRecordAttribute($value) {
    //    return json_decode($value, 1);
    //}

}
