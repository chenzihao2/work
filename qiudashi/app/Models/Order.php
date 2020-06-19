<?php
/**
 * IDE Name: PhpStorm
 * Author  : zyj
 * DateTime: 2020-01-12 11:47:00
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $connection = 'mysql_origin';
    protected $table = 'hl_order';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $guarded = [];



}
