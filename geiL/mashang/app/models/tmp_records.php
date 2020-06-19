<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\models\order;

class tmp_records extends Model{
    public $timestamps = false;

    public static function record_scan($uid, $sid) {
        if (!$uid) {
            return false;
        }
        $day = date('Y-m-d', time());
        //$sid = ltrim($sid, 's.');
        //$exists = self::where('uid', $uid)->where('day', $day)->first();
        //if ($exists) {
        //    $id = $exists['id'];
        //    self::where('id', $id)->increment('scan');
        //} else {
        $i_data = ['uid' => $uid, 'day' => $day, 'scan' => $sid];
        return  self::insertGetId($i_data);
        //}
    } 

    public static function record_order() {
        $day = date('Y-m-d', time());
        $start_time = date('Y-m-d 00:00:00', time());
        $end_time = date('Y-m-d 23:59:59', time());
        $uids = self::select('uid', 'id')->where('day', $day)->get();
        foreach ($uids as $item) {
            $uid = $item['uid'];
            $id = $item['id'];
            $query_builder = order::where('createtime', '>=', $start_time);
            $query_builder->where('createtime', '<', $end_time);
            $query_builder->where('buyerid', $uid);
            $query_builder->where('orderstatus', 1);
            $pay = $query_builder->count();
            $total = $query_builder->sum('price');
            $query_builder_f = order::where('createtime', '>=', $start_time);
            $query_builder_f->where('createtime', '<', $end_time);
            $query_builder_f->where('buyerid', $uid);
            $query_builder_f->where('orderstatus', 0);
            $pay_failed = $query_builder_f->count();
            $u_data = [];
            $u_data = ['pay' => $pay, 'total' => $total, 'pay_failed' => $pay_failed];
            self::where('id', $id)->update($u_data);
        }
    }
    
    public static function lose_weight() {
        $key_time = date('Y-m-01 00:00:00');
        self::where('ctime', '<', $key_time)->delete();
    }

}
