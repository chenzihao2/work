<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class source_show extends Model
{
    public $timestamps = false;
    protected $table = "source_show";
    protected $fillable = [
        'id', 'uid', 'title', 'img_url', 'source_list', 'status', 'create_time'
    ];
}
