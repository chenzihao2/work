<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Respository\FaceUtility;
use Illuminate\Support\Facades\DB;

class ResourceScheduleModel extends Model
{
    //
    protected $connection = 'mysql_origin';
    protected $table = 'hl_resource_schedule';
    protected $extra_table = 'hl_resource_match';
    //protected $subscribe_table = 'hl_user_expert_subscribe';
    protected $pagesize = 20;
    protected $divisor = 5;
    public $timestamps = false;

    private $match_status = [
          0 => '未',
          1 => '上半场',
          2 => '中',
          3 => '下半场',
          4 => '完',
          5 => '中断',
          6 => '取消',
          7 => '加',
          8 => '加时',
          9 => '加时',
          10 => '完场',
          11 => '点',
          12 => '全场结束',
          13 => '延',
          14 => '腰斩',
          15 => '待定',
          16 => '金球',
          17 => '未开始'
    ];

    public function __construct() {
        $this->utility = new FaceUtility();
    }

    //和获取料相关的比赛信息
    public function getScheduleByResourceId($resource_id, $where = []) {
        $datas = [];
        $query_builder = self::where('resource_id', $resource_id)->where('schedule_status', 0);
        //if ($where) {
        //    foreach ($where as $item) {
        //        $query_builder->where($item[0], $item[1], $item[2]);
        //    }
        //}
        $origin = $query_builder->get()->toArray();
        foreach ($origin as $item) {
            $match_table = 'hl_soccer_match';
            $lottery_table = 'hl_soccer_lottery';
            $query_builder = null;
            $query_builder = self::select('s.*','l.short_name as league_name', 'm.match_num',
                'm.host_team', 'm.guest_team', 'm.status as result', 'm.date', 't.name as master_team',
                't1.name as guest_team', 'lo.id as is_bd', 'lo1.id as is_jc');
            $query_builder->from($this->table . ' as s');
            $query_builder->leftJoin('hl_league as l', function($join) {
                $join->on('s.league_id', '=', 'l.league_num')
                    ->on('s.type', '=', 'l.type');
            });
            if ($item['type'] == 2) {
                $match_table = 'hl_basketball_match';
                $lottery_table = 'hl_basketball_lottery';
            }
            $query_builder->leftJoin($match_table . ' as m', 's.schedule_id', '=', 'm.match_num');
            $query_builder->leftJoin('hl_match_team as t', function($join) {
                $join->on('t.team_num', '=', 'm.host_team')
                    ->on('t.type', '=', 's.type');
            });
            $query_builder->leftJoin('hl_match_team as t1', function($join) {
                $join->on('t1.team_num', '=', 'm.guest_team')
                    ->on('t1.type', '=', 's.type');
            });
            $query_builder->leftJoin($lottery_table . ' as lo', function($join) {
                $join->on('m.match_num', '=', 'lo.match_num')
                    ->where('lo.lottery_type', '=', 2);
            });
            $query_builder->leftJoin($lottery_table . ' as lo1', function($join) {
                $join->on('m.match_num', '=', 'lo1.match_num')
                    ->where('lo1.lottery_type', '=', 1);
            });
            $query_builder->where('s.resource_id', $item['resource_id']);
            $query_builder->where('s.id', $item['id']);
            $data = $query_builder->first()->toArray();
            $data['is_bd'] = (int)(boolean)$data['is_bd'];
            $data['is_jc'] = (int)(boolean)$data['is_jc'];
            $data['match_type'] = $data['type'];
            $data['schedule_time'] = strtotime($data['date']);
            $data['match_type_icon'] = config('app.qn_domain') . 'match_type/' . $data['match_type'] . '.png';
            $data['schedule_status'] = $data['result'] == null ? 0 : $this->match_status[$data['result']];
            $times = $this->utility->formatScheduleTime($data['schedule_time']);
            $data = array_merge($data, $times);
            $datas[] = $data;
        }

        return $datas;
    }

    //和获取料相关的旧的比赛信息
    public function getOldScheduleByResourceId($resource_id) {
        $result =  [];
        $matchs = DB::connection($this->connection)->table($this->extra_table)->where('resource_id', $resource_id)->get()->toArray();
        if ($matchs) {
            foreach ($matchs as $v) {
                $v = (array)$v;
               $tmp['match_type'] = $v['type'];
               $tmp['master_team'] = $v['host_name'];
               $tmp['guest_team'] = $v['guest_name'];
               $tmp['league_name'] = $v['league_name'];
               $tmp['is_signle'] = 0;    //以前的数据全部默认不是单关
               $tmp['is_jc'] = 0;   //以前数据全部默认按“所有”处理
               $tmp['is_bd'] = 0;   //以前数据全部默认按“所有”处理
               $tmp['schedule_time'] = strtotime($v['date']);
               $tmp['match_type_icon'] = config('app.qn_domain') . 'match_type/' . $tmp['match_type'] . '.png';
               $times = $this->utility->formatScheduleTime($tmp['schedule_time']);
               $tmp = array_merge($tmp, $times);
               $result[] = $tmp;
            }
        }

        return $result;
    }
}
