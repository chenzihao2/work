<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;

class purchase_record extends Model{

//    用户购买步骤记录
    public $timestamps = false;
    protected $table = "purchase_record";

    static public function setPurchaseRecord($sourceid, $buyerid, $selledid, $step){
        $rdsKey = "purchase_record_" . $sourceid . '_' . $buyerid . '_' . $selledid;

        switch ($step){
            case 1:
                $purchase_record = [];
                $purchase_record['sourceid'] = $sourceid;
                $purchase_record['buyerid'] = $buyerid;
                $purchase_record['selledid'] = $selledid;
                $purchase_record['step1'] = 1;
                $purchase_record['step1_time'] = time();
                $res = self::insertGetId($purchase_record);
                if($res){
                    Redis::setex($rdsKey, 3600, $res);
                }
                break;
            case 2:
                $purchase_record_id = Redis::get($rdsKey);
                $purchase_record = [];
                $purchase_record['step2'] = 1;
                $purchase_record['step2_time'] = time();
                $res = self::where('id', $purchase_record_id)->update($purchase_record);
                break;
            case 3:
                $purchase_record_id = Redis::get($rdsKey);
                $purchase_record = [];
                $purchase_record['step3'] = 1;
                $purchase_record['step3_time'] = time();
                $res = self::where('id', $purchase_record_id)->update($purchase_record);
                Redis::del($rdsKey);
                break;
        }
        return $res;
    }
}
