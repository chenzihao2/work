<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;

class sensitives extends Model{

    public $timestamps = false;
    protected $table = "sensitives";
    protected $appends = ['level_text'];
    protected $level_texts = [ 1 =>'禁止', 2 => '危险', 3 => '敏感'];

    public function getLevelTextAttribute()
    {
        return $this->level_texts[$this->attributes['level']];
    }

    static public function wordsList($key_words = '', $page_num = 15){
        $data = [];
        $query_builder = self::select('id', 'words', 'level');
        $query_builder->where('deleted', 0);
        if ($key_words) {
            $query_builder->where('words', 'like', '%' . $key_words . '%');
        }
        $query_builder->orderBy('ctime', 'desc');
        $data = $query_builder->paginate($page_num)->toArray();
        return $data;
    }

    public static function createWords($words, $level) {
        return self::insert(['words' => $words, 'level' => $level]);
    }

    public static function getWords($condition = []) {
        $query_builder = self::select('id', 'words', 'level');
        $query_builder->where('deleted', 0);
        if ($condition) {
            foreach ($condition as $k => $v) {
                $query_builder->where($k, $v);
            }
        }
        $result = $query_builder->first();
        return $result;
    }

    public static function modifyWords($id, $modifys = []) {
        if (!$id) {
            return false;
        }
        return self::where('id', $id)->update($modifys);
    }
}
