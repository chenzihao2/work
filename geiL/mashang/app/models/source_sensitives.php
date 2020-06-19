<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;
use App\models\source;
use App\models\contents;
use App\models\sensitives;
//use Overtrue\LaravelPinyin\Facades\Pinyin;

class source_sensitives extends Model{

    public $timestamps = false;
    protected $table = "source_sensitives";
    protected $appends = ['level_text', 'suggest_text', 'position_text'];
    protected $level_texts = [ 1 =>'禁止', 2 => '危险', 3 => '敏感'];
    protected $suggest_texts = [ 0 =>'通过', 1 => '不通过'];
    protected $position_texts = [ 0 =>'标题', 1 => '副标题', 2 => '文字'];
    const SUGGEST_TEXTS = [ 0 =>'通过', 1 => '不通过'];
    const POSITION_TEXTS = [ 0 =>'title', 1 => 'sub_title', 2 => 'content'];
    const POSITIONS = [ 0 =>'标题', 1 => '副标题', 2 => '文字'];
    const C_CHECK_TEXTS = [
        0 => '【料内容疑似违规，审核中，请耐心等待】',
        2 => '【料内容审核未通过】',
    ];

    const LIST_KEY = 'gl_source_sensitives';

    public static function is_sensitive($id, $action = 'source') {
        $is_sensitive = 0;
        $query_builder = self::select('id');
        if ($action == 'source') {
            $query_builder->where('sid', $id);
        } else {
            $query_builder->where('cid', $id);
        }
        $res = $query_builder->first();
        if ($res) {
            $is_sensitive = 1;
        }
        return $is_sensitive;
    }

    public static function sensitive_info($cid, $sid = 0, $content = '') {
        $result = [];
        $result['text_data'] = [];
        $suggest = 0;
        if ($sid) {
            $source_info = self::source_info($sid, $cid);
            $info = self::where('sid', $sid)->orderBy('position', 'asc')->get();
            if ($info) {
                $have_pos = [];
                foreach ($info as $item) {
                    if ($item['suggest'] > $suggest) {
                        $suggest = $item['suggest'];
                    }
                    $tmp = [];
                    $tmp['pos'] = $item['position_text'];
                    $tmp['level_text'] = $item['level_text'];
                    $tmp['hit_words'] = $item['words'];
                    $tmp['origin_text'] = $source_info[self::POSITION_TEXTS[$item['position']]];
                    if ($item['position'] == 2) {
                        if ($cid != $item['cid']) {
                            continue;
                        }
                    }
                    $result['text_data'][$item['position']] = $tmp;
                    $have_pos[] = $item['position'];
                }
                $missing_pos = array_diff([0, 1, 2], $have_pos);
                if ($missing_pos) {
                    foreach ($missing_pos as $m_pos) {
                        $tmp = [];
                        $tmp['pos'] =  self::POSITIONS[$m_pos];
                        $tmp['level_text'] = '';
                        $tmp['hit_words'] = '';
                        $tmp['origin_text'] = $source_info[self::POSITION_TEXTS[$m_pos]];
                        $result['text_data'][$m_pos] = $tmp;
                    }
                }
            }
            ksort($result['text_data']);
            $result['text_data'] = array_values($result['text_data']);
        } else {
            $info = self::where('cid', $cid)->first();
            $tmp = [];
            if ($info) {
                if (!$content) {
                    $contents = contents::select('description')->where('cid', $cid)->first();
                    $content = $contents['description'] ?: '';
                }
                if ($info['suggest'] > $suggest) {
                    $suggest = $info['suggest'];
                }
                $tmp['pos'] = $info['position_text'];
                $tmp['level_text'] = $info['level_text'];
                $tmp['hit_words'] = $info['words'];
                $tmp['origin_text'] = $content;
            }
            $result['text_data'] = $tmp;
        }
        $result['suggest'] = self::SUGGEST_TEXTS[$suggest];
        return $result;
    }

    public static function apply($origin_data, $c = 0, $is_white = 0) {
        $csids = [];
        if (empty($origin_data)) {
            return $origin_data;
        }
        if (is_object($origin_data)) {
            $origin_data = $origin_data->toArray();
        }
        $key_column = 'sid';
        if ($c) {
            $key_column = 'cid';
        }
        $csids = array_column($origin_data, $key_column);
        if ($csids) {
            foreach ($origin_data as $k => $item) {
                $origin_data[$k] = self::apply($item, $c, $is_white);
            }
        } else {
            if ($c) {
                $cid = isset($origin_data['cid']) ? $origin_data['cid'] : 0;
                if ($cid) {
                    $c_is_check = 0;
                    if ($is_white) {
                        $c_is_check = contents::where('cid', $cid)->value('is_check'); 
                    }
                    if ($c_is_check != 1 && $is_white) {
                        $origin_data['description'] = self::C_CHECK_TEXTS[$c_is_check] ?: '';
                    } else {
                        $info = self::where('cid', $cid)->first();
                        if ($info) {
                            $origin_data['description'] = $info['worked_text'];
                        } else {
                            if ($is_white) {
                                $tmp_data = ['content' => $origin_data['description']];
                                $tmp_result = self::white_filter($tmp_data, 1, 0);
                                $origin_data['description'] = $tmp_result['content'];
                            }
                        }
                    }
                }
            } else {
                $sid = $origin_data['sid'];
                foreach ($origin_data as $k => $v) {
                    if (in_array($k, ['title', 'sub_title', 'content'])) {
                        $pos_map = array_flip(self::POSITION_TEXTS);
                        $pos = $pos_map[$k];
                        $info = self::where('sid', $sid)->where('position', $pos)->first();
                        if ($info) {
                            $origin_data[$k] = $info['worked_text'];
                        }
                    }
                }
            }
        }
        return $origin_data;
    }

    public function getLevelTextAttribute()
    {
        return $this->level_texts[$this->attributes['level']];
    }

    public function getSuggestTextAttribute()
    {
        return $this->suggest_texts[$this->attributes['suggest']];
    }

    public function getPositionTextAttribute()
    {
        return $this->position_texts[$this->attributes['position']];
    }

    //job
    public static function push_source($id) {
        return Redis::lpush(self::LIST_KEY, $id);
    }

    public static function push_source_content($sid, $cid) {
        return Redis::lpush(self::LIST_KEY, $sid . '_' . $cid);
    }

    public static function pop_source() {
        return Redis::rpop(self::LIST_KEY);
    }

    public static function source_info($sid, $cid = 0) {
        $sources = source::select('title', 'sub_title', 'is_check', 'uid')->where('sid', $sid)->first(); 
        $content_query = contents::select('cid', 'description', 'is_check')->where('sid', $sid);
        if ($cid) {
            $content_query->where('cid', $cid);
        }
        $contents = $content_query->first();
        $result = [];
        if ($sources && $contents) {
            $result['sid'] = $sid;
            $result['uid'] = $sources['uid'];
            $result['title'] = $sources['title'];
            $result['sub_title'] = $sources['sub_title'];
            $result['s_is_check'] = $sources['is_check'];
            $result['content'] = $contents['description'];
            $result['cid'] = $contents['cid'];
            $result['c_is_check'] = $contents['is_check'];
        }
        return $result;
    }

    public static function white_filter($data, $apply = 0, $sid = 0 , $cid = 0) {
        $sensitives = sensitives::select('id', 'words', 'level')->where('deleted', 0)->where('is_white', 1)->get()->toArray();
        $hit_level = 3;
        $source_info = [];
        if ($sid) {
            $source_info = self::source_info($sid, $cid);
        }
        foreach ($sensitives as $item) {
            foreach ($data as $k => $v) {
               if (strpos($v, $item['words']) !== FALSE) {
                   if ($item['level'] < $hit_level) {
                       $hit_level = $item['level'];
                   }
                   if ($hit_level == 1) {
                       return false;
                   }
                   if ($apply) {
                       if (in_array($k, ['title', 'sub_title'])) {
                           $data[$k] =  self::text_replace($data[$k], $item);
                       }
                       if (in_array($k, ['content'])) {
                           if ($source_info) {
                               self::hit('content', $source_info, $item);
                           } else {
                               $data[$k] = self::text_replace($data[$k], $item);
                           } 
                       }
                   }
               }
            }
        }
        if ($apply && !$sid) {
            return $data;
        }
        return true;
    }


    public static function filter($sid) {
        $sid_info = explode('_', $sid);
        if (count($sid_info) > 1) {
            return self::filter_content($sid_info);
        }
        $source_info = self::source_info($sid);
        $sid = $source_info['sid'];
        $s_is_check = $source_info['s_is_check'];
        $sensitives = sensitives::select('id', 'words', 'level')->where('deleted', 0)->get()->toArray();
        $hit_level = 3;
        $is_hit = 0;
        foreach ($sensitives as $item) {
            foreach (['title', 'sub_title', 'content'] as $v) {
                if ($source_info[$v]) {
                    if (strpos($source_info[$v], $item['words']) !== FALSE) {
                        if ($item['level'] < $hit_level) {
                            $hit_level = $item['level'];
                        }
                        self::hit($v, $source_info, $item);
                        if (in_array($v, ['title', 'sub_title'])) {
                            $is_hit = 1;
                        }
                    }
                }
            }
        }
        if ($hit_level == 1) {
            if ($s_is_check == 1) {
                source::where('sid', $sid)->update(['is_check' => 0]);
            }
        }
        if ($is_hit) {
            self::apply_pic($source_info);
        }
        var_dump($sid . 'done');
        return;
    }

    public static function apply_pic($data) {
        if (!$data) {
            return false;
        }
        $sid = $data['sid'];
        $uid = $data['uid'];
        $cid = $data['cid'];
        $url = config('constants.backend_domain') . '/pub/source/applypic';
        $url .= '?sid=' . $sid;
        $url .= '&uid=' . $uid;
        $url .= '&cid=' . $cid;
        self::curl_get_https($url);
    }

    public static function filter_content($sid_info) {
        $sid = $sid_info[0];
        $cid = $sid_info[1];
        if (!$sid || !$cid) {
            return false;
        }
        $sources = source::select('uid', 'is_check')->where('sid', $sid)->first(); 
        $contents = contents::select('description', 'is_check')->where('cid', $cid)->first();
        $sensitives = sensitives::select('id', 'words', 'level')->where('deleted', 0)->get()->toArray();
        $content = $contents['description'];
        $hit_level = 3;
        $s_is_check = $sources['is_check'];
        $c_is_check = $contents['is_check'];
        $source_info['sid'] = $sid;
        $source_info['uid'] = $sources['uid'];
        $source_info['cid'] = $cid;
        $source_info['content'] = $content;
        foreach ($sensitives as $item) {
            if (strpos($content, $item['words']) !== FALSE) {
                if ($item['level'] < $hit_level) {
                    $hit_level = $item['level'];
                }
                self::hit('content', $source_info, $item);
            }
        }
        if ($hit_level == 1) {
            if ($s_is_check == 1) {
                source::where('sid', $sid)->update(['is_check' => 0]);
            }
            if ($c_is_check == 1) {
                contents::where('cid', $cid)->update(['is_check' => 0]);
            } 
        }
        var_dump($sid . '_' . $cid . 'done');
        return;
    }

    public static function hit($position, $source_info, $words_info) {
        $format_data = [];
        $pos = 0;
        switch($position) {
        case 'title':
            $pos = 0;
            break;
        case 'sub_title':
            $pos = 1;
            break;
        case 'content':
            $pos = 2;
            break;
        }
        $format_data['position'] = $pos;
        $format_data['sid'] = $source_info['sid'];
        $format_data['uid'] = $source_info['uid'];
        $format_data['wid'] = $words_info['id'];
        $format_data['words'] = $words_info['words'];
        $format_data['level'] = $words_info['level'];
        $format_data['suggest'] = 0;
        $format_data['cid'] = 0;
        if ($pos == 2) {
            $format_data['cid'] = $source_info['cid'];
        }

        $before = source_sensitives::select('id','wid', 'words', 'level', 'worked_text')->where('sid', $format_data['sid'])->where('position', $format_data['position'])->where('cid', $format_data['cid'])->first();
        if ($before) {
            $before_wid = explode(',', $before['wid']);
            if (in_array($format_data['wid'], $before_wid)) {
                return;
            }
            $before_words = explode('、', $before['words']);
            if (in_array($format_data['words'], $before_words)) {
                return;
            }
            $new_data = [];
            $new_data['wid'] = $before['wid'] . ',' . $format_data['wid'];
            $new_data['words'] = $before['words'] . '、' . $format_data['words'];
            if ($format_data['level'] < $before['level']) {
                $new_data['level'] = $format_data['level'];
                if ($new_data['level'] == 1) {
                    $new_data['suggest'] = 1;
                }
            }
            $new_data['worked_text'] = self::text_replace($before['worked_text'], $words_info);
            return source_sensitives::where('id', $before['id'])->update($new_data);
        }
        $format_data['worked_text'] = self::text_replace($source_info[$position], $words_info);
        if ($format_data['level'] == 1) {
            $format_data['suggest'] = 1;
        }
        return source_sensitives::insert($format_data);
    }


    public static function text_replace($text, $words_info) {
        $level = $words_info['level'];
        $param1 = $words_info['words'];
        $param2 = '';
        $param3 = $text;
        if (in_array($level, [1, 2])) {
            $length = mb_strlen($param1);
            for($i = 0; $i < $length; $i++) {
                $param2 .= '*';
            }
        }
        if (in_array($level, [3])) {
            $pinyin = app('pinyin');
            $param2 = $pinyin->sentence($param1);
        }
        return str_replace($param1, $param2, $param3);
    }
    
    private static function curl_get_https($url){
        $curl = curl_init(); // 启动一个CURL会话
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec($curl);     //返回api的json对象
        //关闭URL请求
        curl_close($curl);
        return $res;    //返回json对象
    }

    //public static function testpy() {
    //    $a = '陈自豪';
    //    $pinyin = app('Pinyin');
    //    var_dump($pinyin::sentence($a));
    //}
    //public function getLevelTextAttribute()
    //{
    //    return $this->level_texts[$this->attributes['level']];
    //}
}
