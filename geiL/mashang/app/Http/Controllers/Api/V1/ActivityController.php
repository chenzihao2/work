<?php
/**
 * User: WangHui
 * Date: 2018/6/14
 * Time: 13:58
 */

namespace App\Http\Controllers\Api\V1;


use Illuminate\Support\Facades\DB;

class ActivityController extends BaseController {

	/**
	 * 世界杯uv pv
	 * @param $id
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function sjbData($id){
		$currentTime = strtotime(date("Y-m-d",time()));
		$data['uid'] = $id;
		$data['times'] = 1;
		$data['time'] = $currentTime;
		//获取本日此用户登录信息
		$userLogin = DB::table('sjb')->where('uid',$id)->where('time',$currentTime)->first();
		if(!$userLogin){
			//新增
			DB::table('sjb')->insert($data);
		}else{
			DB::table('sjb')->where('uid',$id)->where('time',$currentTime)->increment('times', 1);
		}

		$return['status_code'] = '200';
		$return['data'] = array();
		return response()->json($return);
	}

}