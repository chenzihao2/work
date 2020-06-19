<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Respository\FaceUtility;
use App\Models\ResourceScheduleModel;
use Illuminate\Support\Facades\DB;

class ResourceModel extends Model
{
    //
    protected $connection = 'mysql_origin';
    protected $table = 'hl_resource';
    protected $extra_table = 'hl_resource_extra';
    protected $pagesize = 20;
    protected $primaryKey = 'resource_id';
    protected $divisor = 5;
    public $timestamps = false;

    public function __construct() {
        $this->utility = new FaceUtility();
        $this->resource_schedule = new ResourceScheduleModel();
    }

    public function getResourceByQuery($query, $order = [], $limit = []) {
        $query_builder = self::select('r.*');
        $query_builder->from($this->table . ' as r');
        $query_builder->leftJoin('hl_resource_extra as re', function ($join) {
            $join->on('r.resource_id', '=', 're.resource_id');
                //->where('re.bet_status', '<>', 0)
        });
        $query_builder->leftJoin('hl_resource_schedule as rs', function ($join) {
            $join->on('r.resource_id', '=', 'rs.resource_id')
                ->where('rs.schedule_status',  0);
        });
        if ($query) {
            foreach ($query as $k => $v) {
                if (is_array($v)) {
                    $query_builder->where('r.' . $k, '>', $v[0]);
                    $query_builder->where('r.' . $k, '<', $v[1]);
                    continue;
                }
                $query_builder->where('r.' . $k, $v);
            }
        }
        $query_builder->whereRaw('(re.bet_status <> 0 or rs.bet_status <> 0)');
        if ($order) {
            foreach ($order as $k => $v) {
                $query_builder->orderBy('r.' . $k, $v);
            }
        }
        if ($limit) {
            $query_builder->offset($limit[0])->limit($limit[1]);
        }
        return $query_builder->get()->toArray();
        var_dump($query_builder->toSql());die;
    }

    public function getAllResourceByQuery($query, $order = [], $limit = []) {
        $query_builder = self::select();
        if ($query) {
            foreach ($query as $k => $v) {
                if (is_array($v)) {
                    $query_builder->where($k, '>', $v[0]);
                    $query_builder->where($k, '<', $v[1]);
                    continue;
                }
                $query_builder->where($k, $v);
            }
        }
        if ($order) {
            foreach ($order as $k => $v) {
                $query_builder->orderBy($k, $v);
            }
        }
        if ($limit) {
            $query_builder->offset($limit[0])->limit($limit[1]);
        }
        return $query_builder->get()->toArray();
    }

    //获取料关联的比赛
    public function getRelatedMatch($resource_id) {
        $schedules = $this->resource_schedule->getScheduleByResourceId($resource_id); 
        $old_schedules = $this->resource_schedule->getOldScheduleByResourceId($resource_id); 
        $result = array_merge($schedules, $old_schedules);
        //$bet_status = $this->getBetResult($resource_id, $schedules);
        return $result;
    }

    //获取料的判定结果
    //返回值：0未判完,1红单,2走单,3黑单,5二中一,6三中一,7三中二
    public function getBetResult($resource_id, $schedules = []) {
        $bet_status = 0;
        $manual = (array)DB::connection($this->connection)->table($this->extra_table)->where('resource_id', $resource_id)->first();        
        if ($manual['bet_status']) {
            return $manual['bet_status'];
        }
        if (empty($schedules)) {
            $schedules = $this->resource_schedule->getScheduleByResourceId($resource_id);
        }
        $scheduleBetList = array_column($schedules, 'bet_status');
        if (!in_array(0, $scheduleBetList) && !empty($scheduleBetList)) {
          $total = count($scheduleBetList);
          $bet_count = array_count_values($scheduleBetList);
          $red_count = $black_count = $balance_count = 0;
          foreach($bet_count as $bet_key => $count) {
            if (in_array($bet_key, [1,4,5,6])) {
              $red_count += $count;
            } else if (in_array($bet_key, [3,7])) {
              $black_count += $count;
            } else {
              $balance_count += $count;
            }
          }

          if ($red_count == 0) {
            $bet_status = ($black_count != 0) ? 3 : 2;
          } else {
            $bet_status = 1;
            if ($total == 2) {
              $bet_status = ($red_count == $total) ? 1 : 5;
            } else if ($total == 3) {
              $bet_status = ($red_count == $total) ? 1 : ($red_count == 1) ? 6 : 7;
            }
          }
        }
        return $bet_status;
    }

}
