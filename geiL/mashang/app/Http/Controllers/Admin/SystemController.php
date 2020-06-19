<?php
/**
 * User: WangHui
 * Date: 2018/6/4
 * Time: 17:18
 */

namespace App\Http\Controllers\Admin;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class SystemController extends BaseController {
	public function getSetting() {
		$key = "FirstPageDisplay";
		$re = Redis::exists($key);

		if ($re) {
			$display = Redis::get($key);
		} else {
			$display = 0;
			Redis::set($key,0);
		}

		$data['status_code']=200;
		$data['display'] = $display;
		return response()->json($data);
	}

	public function setSetting(Request $request) {
		$setting = $request->input('setting', '0');
		$key = "FirstPageDisplay";
		Redis::set($key,$setting);

		return array('status_code' => 200);
	}

}