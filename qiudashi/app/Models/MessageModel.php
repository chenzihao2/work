<?php
/**
 * IDE Name: PhpStorm
 * Author  : zyj
 * DateTime: 2020-01-12 11:47:00
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageModel extends Model
{
    protected $connection = 'mysql_chat';
    protected $table = 'chat_message';
    protected $primaryKey = 'msg_id';
    public $timestamps = false;
    protected $guarded = [];



}
