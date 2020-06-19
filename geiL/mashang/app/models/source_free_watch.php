<?php
/**
 * User: WangHui
 * Date: 2018/9/14
 * Time: 14:47
 */

namespace App\models;


use Illuminate\Database\Eloquent\Model;

class source_free_watch extends Model
{
	// 用户组
	public $timestamps = false;
	protected $table = "source_free_watch";
	protected $fillable = [
		'id', 'uid', 'sid', 'create_time', 'times', 'last_time'
	];
	protected $casts = [
		'id' => 'int',
		'uid' => 'int'
	];
}