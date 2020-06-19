<?php
/**
 *  用户金额变更记录
 */
namespace App\models;

use Illuminate\Database\Eloquent\Model;

class client_money_change extends Model{

    public $timestamps = false;
    protected $table = "client_money_change";

    /**
     * 记录用户金额变更
     * @param $uid      用户ID
     * @param $money    金额（分）
     * @param $type     1增加  2减少
     * @param $source   操作来源
     * * @return array
     */
    static public function setChange($uid, $money, $type, $source){
        $lastChange = self::select('id', 'uid', 'total')->where('uid', $uid)->orderBy('id', 'desc')->limit(1)->first();

        $lastTotal = 0;
        if(!empty($lastChange)){
            $lastChange = $lastChange->toArray();
            $lastTotal = $lastChange['total'];
        }

        if($lastTotal == 0){
            $clientTotal = client_extra::select('id', 'total')->where('id', $uid)->first();
            $lastTotal = $clientTotal['total']*100;
        }

        $moneyBranch = $money*100;
        $change = [];
        $change['uid'] = $uid;
        $change['source'] = $source;
        $change['type'] = $type;
        $change['money'] = $moneyBranch;
        if($type == 1){
            $total = $lastTotal+$moneyBranch;
        } elseif ($type == 2) {
            $total = $lastTotal-$moneyBranch;
        } else {
            return [];
        }
        $change['total'] = $total;
        $change['create_time'] = time();

        return self::insert($change);
    }
}
