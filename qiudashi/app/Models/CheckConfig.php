<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckConfig extends Model
{
    protected $connection = 'mysql_origin';
    protected $table = 'check_config';
    protected $pagesize = 20;
    protected $default_bottom = '1,2,3,4,5';
    protected $default_pay = 1;
    protected $default_match = 1;
    protected $show_comment_model=1;
    protected $bindmobile=1;
    protected $default_toptab = [
        '方案' => 1,
        '专家' => 1,
        '免费' => 1,
    ];
    protected $bottom_map = [
        1 => 'Information',
        2 => 'Video',
        3 => 'Competition',
        4 => 'Recommend',
        5 => 'My',
    ];

    protected $bottom_map_map = [
        'Information' => '资讯',
        'Video' => '视频',
        'Competition' => '赛事',
        'Recommend' => '推荐',
        'My' => '我的',
    ];

    public function __construct() {
    }

    public function show($channel, $version) {
        $data = self::where('channel', $channel)->where('version', $version)->first();
        if (!$data) {
            $insert_data['channel'] = $channel;
            $insert_data['version'] = $version;
            $insert_data['bottom'] = $this->default_bottom;
            $insert_data['toptab'] = json_encode($this->default_toptab);
            $insert_data['pay'] = $this->default_pay;
            $insert_data['match'] = $this->default_match;
            $insert_data['show_comment_model'] = $this->show_comment_model;
            $insert_data['bindmobile'] = $this->bindmobile;
            self::insert($insert_data);
            $data = $insert_data;
        }
        return $this->assemble($data);
    }

    public function editShow($data) {
        $this->id = $data['id'];
        $ins_show = $this->find($this->id);
        $ins_show->pay = (int)$data['pay'];
        $ins_show->match = (int)$data['match'];
        $ins_show['show_comment_model'] = (int)$data['show_comment_model'];
        $ins_show['bindmobile'] = (int)$data['bindmobile'];
        $ins_show->toptab = '';
        $ins_show->bottom = '';
        $tmp_bottom = $tmp_toptab = [];
        $map_bottom = array_flip($this->bottom_map);
        foreach ($data['bottom'] as $item) {
            if (!in_array($item['name'], $this->bottom_map)) {
                continue;
            }
            $item['show'] = (int)$item['show'];
            if ($item['show'] == 1) { 
                $tmp_bottom[] = $map_bottom[$item['name']];
            } else {
                $tmp_bottom[] = $map_bottom[$item['name']] . 0;
            }
        }
        empty($tmp_bottom) && $tmp_bottom = $this->default_bottom;
        foreach ($data['toptab'] as $item) {
            $item['show'] = (int)$item['show'];
            if ($item['show'] == 1) {
                $tmp_toptab[$item['name']] = 1; 
            } else {
                $tmp_toptab[$item['name']] = 0; 
            }
        }
        empty($tmp_toptab) && $tmp_toptab = json_encode($this->default_toptab);
        $ins_show->bottom = implode(',', $tmp_bottom);
        $ins_show->toptab = json_encode($tmp_toptab);
        $ins_show->save();
    }

    public function showInfo($id) {
        $info = $this->find($id);
        $bottom = $info['bottom'];
        $tmp_bottom = [];
        foreach ($bottom as $item) {
            $tmp = [];
            $tmp['show'] = 1;
            if ($item >= 10) {
                $item = bcdiv($item, 10);
                $tmp['show'] = 0;
            }
            $tmp['name'] = $this->bottom_map[$item];
            $tmp['name_extra'] = $this->bottom_map_map[$this->bottom_map[$item]];
            $tmp_bottom[] = $tmp;
        }
        $info['bottom'] = $tmp_bottom;
        $toptab = json_decode($info['toptab'], 1);
        $tmp_toptab = [];
        $key_toptab = array_keys($this->default_toptab);$i = 0;
        foreach ($toptab as $k => $item) {
            $tmp = [];
            $tmp['show'] = 0;
            if ($item) {
                $tmp['show'] = 1;
            }
            $tmp['name'] = $k;
            if (isset($key_toptab[$i])) {
                $tmp['name_extra'] = $key_toptab[$i];
            }
            $i++;
            $tmp_toptab[] = $tmp;
        }
        $info['toptab'] = $tmp_toptab;
        return $info;
    }

    public function showList($condition) {
        $query_builder = self::select();
        if ($condition['channel']) {
            $query_builder->where('channel', $condition['channel']);
        }
        if ($condition['version']) {
            $version = (int)str_replace('.', '', $condition['version']); 
            $query_builder->where('version', $version);
        }
        $pagesize = $condition['pagesize'];
        $query_builder->latest('version');
        $query_builder->orderBy('channel', 'asc');
        $data = $query_builder->paginate($pagesize);
        foreach ($data as $k => $v) {
            $data[$k] = $this->assembleAdmin($v);
        }
        return $data;
    }

    private function assembleAdmin($data) {
        $result = [];
        $tmp_bottom = $data['bottom'] ?: explode(',', $this->default_bottom);
        $tmp_tablist = [];
        foreach ($tmp_bottom as $item) {
            if ($item < 10) {
                $tmp_tablist[] = $this->bottom_map_map[$this->bottom_map[$item]];
            }
        }
        $data['bottom'] = implode(',', $tmp_tablist); 
        $tmp_toptab = json_decode($data['toptab'], 1) ?: $this->default_toptab;
        $tmp_tablist1 = []; 
        foreach ($tmp_toptab as $k => $item) {
            if ($item) {
                $tmp_tablist1[] = $k;
            }
        }
        $data['toptab'] = implode(',', $tmp_tablist1);
        return $data;
    }

    private function assemble($data) {
        $result = [];
        $tmp_bottom = is_array($data['bottom']) ? $data['bottom'] : explode(',', $this->default_bottom);
        $tmp_tablist = [];
        foreach ($tmp_bottom as $item) {
            if ($item < 10) {
                $tmp_tablist[] = $this->bottom_map[$item];
            }
        }

        $tmp_toptab = json_decode($data['toptab'], 1) ?: $this->default_toptab;
        $tmp_tablist1 = []; $i = 1;
        foreach ($tmp_toptab as $k => $item) {
            if ($item) {
                $tmp_tablist1[] = ['key' => $i, 'value' => $k];
            }
            $i++;
        }

        $result['show_comment_model'] = $data['show_comment_model'];
        $result['bindmobile'] = $data['bindmobile'];
        $result['show'] = $data['pay'];
        $result['display'] = $data['match'];
        $result['tablist'] = $tmp_tablist;
        $result['tablist1'] = $tmp_tablist1;
        return $result;
    }

    public function setVersionAttribute($value) {
        $this->attributes['version'] = (int)str_replace('.', '', $value);
    }

    public function getVersionAttribute($value) {
        $value = (string)$value;
        $result = '';
        for ($i =0; $i < strlen($value); $i++) {
            if (!empty($result)) {
                $result .= '.' . $value[$i] ;
            } else {
                $result .= $value[$i];
            }
        }
        return $result;
    }

    public function getBottomAttribute($value) {
        if (is_string($value) && !empty($value)) {
            return explode(',', $value);
        }
        return $value;
    }
}
