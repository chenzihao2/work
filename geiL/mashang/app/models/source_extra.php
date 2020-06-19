<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class source_extra extends Model
{
    public $timestamps = false;
    protected $table = "source_extra";
    protected $fillable = [
        'id', 'soldnumber', 'modifiedtime'
    ];
    protected $casts = [
        'id' => 'string',
    ];
}
