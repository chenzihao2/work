<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class SmsLog extends Model
{
    //
    protected $table = 'sms_log';
    protected $timestamp = false;

    public static function doLog($phone, $content, $status = 1) {
        $user_info = User::where('phone', $phone)->select('user_id')->first();
        $user_id = 0;
        if ($user_info) {
            $user_id = $user_info['user_id'];
        }
        $i_data = ['phone' => $phone, 'user_id' => $user_id, 'content' => $content, 'status' => $status];
        return self::insert($i_data);
    }

    public static function todaySendCount($phone) {
        $start = date('Y-m-d 00:00:00', time());
        $end = date('Y-m-d 23:59:59', time());
        $count = self::where('phone', $phone)
            ->where('status', 1)
            ->whereBetween('send_time', [$start, $end])
            ->count();
        return $count;
    }
}
