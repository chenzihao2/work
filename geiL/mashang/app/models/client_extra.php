<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class client_extra extends Model
{
    // 用户副表
    public $timestamps = false;
    protected $table = "client_extra";

    protected $fillable = [
        'id', 'balance', 'total', 'withdrawed', 'withdrawing', 'payed', 'publishednum', 'soldnum', 'buynum', 'role', 'lastlogin', 'service_fee'
    ];

    protected $casts = [
        'id' => 'string',
    ];

    const PAYED_LIMIT = 200;
    const BUYNUM_LIMIT = 5;

    //是否支持微信支付
    public static function can_wx($uid, $is_buyer = 0) {
        $can_wx = 0;
        $info = self::where('id', $uid)->first();
        if ($info) {
            $can_wx = $info['can_wx'];
            if ($is_buyer) {
                $can_wx = $info['can_wx_pay'];
                if ($can_wx == 2) {
                    $can_wx = 0;
                }
            }
        }
        return $can_wx;
    }

    //开通微信支付
    public static function open_wx_pay($uid, $is_buyer = 0) {
        if (!$uid) {
            return false;
        }
        $key_fields = 'can_wx';
        if ($is_buyer) {
            $key_fields = 'can_wx_pay';
        }
        $update_data = [$key_fields => 1];
        $info = self::where('id', $uid)->first();
        if ($info && $info[$key_fields]) {
            $update_data[$key_fields] = 0;
            if ($is_buyer) {
                if ($info[$key_fields] == 2) {
                    $update_data[$key_fields] = 1;
                } else {
                    $update_data[$key_fields] = 2;
                }
            }
        }
        self::where('id', $uid)->update($update_data);
    }

    //买家自动开通微信支付
    public static function auto_open_wx($uid) {
        if (!$uid) {
            return false;
        }
        $info = self::where('id', $uid)->first();
        $can_wx_pay = $info['can_wx_pay'];
        if ($can_wx_pay > 0) {
            return true;
        }
        $payed = $info['payed'];
        $buynum = $info['buynum'];
        if ($payed >= self::PAYED_LIMIT && $buynum >= self::BUYNUM_LIMIT) {
            self::where('id', $uid)->update(['can_wx_pay' => 1]);
        }
    }
}
