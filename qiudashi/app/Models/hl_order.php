<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class hl_order extends Model
{
    protected $connection = 'mysql_origin';
    protected $table = 'hl_order';
    protected $pagesize = 20;
    public $timestamps = false;

    //检查是否已购买 文章/视频/情报
    public function checkBuy($user_id, $order_param, $order_type) {
        $data = self::where(['user_id' => $user_id,
                     'order_param' => $order_param,
                     'order_type' => $order_type,
                     'order_status' => 1
                 ])->first();
        if ($data) {
            return 1;
        }
        return 0;
    }
}
