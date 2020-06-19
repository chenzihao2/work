<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class source extends Model
{
    // 料
    public $timestamps = false;
    protected $table = "source";
    protected $fillable = [
        'id','sid', 'uid', 'title', 'price', 'description', 'thresh', 'status', 'createtime', 'modifytime', 'url', 'play_time', 'play_start', 'play_end', 'order_status', 'form_id', 'is_recommend', 'recommend_sort'
    ];
    protected $casts = [
        'id' => 'string',
        'sid' => 'string'
    ];
}
