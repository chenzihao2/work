<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class buyer extends Model
{
    public $timestamps = false;
    protected $table = "buyer";
    protected $fillable = [
        'id', 'selledid', 'buyerid', 'payed', 'buy_num', 'last_buy_time', 'status', 'create_time'
    ];

    static public function checkBuyerStatus($selledid, $buyerid){
        $buyerInfo = buyer::select()->where('selledid', $selledid)->where('buyerid', $buyerid)->first();
        return !empty($buyerInfo) ? $buyerInfo['status'] : 1 ;
    }

}
