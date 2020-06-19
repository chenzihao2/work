<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class LoginLog extends Model
{
    protected $table = 'login_log';
    protected $timestamp = false;

    public static function doLog($user_id, $channel) {
        self::insert(['user_id' => $user_id, 'channel' => $channel]);
        User::where('user_id', $user_id)
            ->update(['last_login_time' => date('Y-m-d H:i:s', time())]);
    }
}
