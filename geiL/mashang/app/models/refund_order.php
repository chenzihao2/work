<?php
/**
 * User: WangHui
 * Date: 2018/5/15
 * Time: 14:36
 */

namespace App\models;


use Illuminate\Database\Eloquent\Model;

class refund_order extends Model {
	public $timestamps = false;
	protected $table = "refund_order";
	protected $casts = [
//		'id' => 'string',
		'sid' => 'string',
		'order' => 'string',
		'refund' => 'string',
	];

	protected $fillable = [
        'id' ,'sid' ,'buyerid' ,'selledid' ,'order' ,'refund' ,'price' ,'time' ,'edit_time' ,'oper' ,'status', 'is_manual', 'reason', 'is_batch_order', 'batch_ordernum', 'mch_account', 'assumed_host'
	];
}
