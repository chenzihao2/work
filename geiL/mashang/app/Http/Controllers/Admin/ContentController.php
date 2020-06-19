<?php
/**
 * User: WangHui
 * Date: 2018/9/18
 * Time: 17:57
 */
namespace App\Http\Controllers\Admin;
use App\models\contents;
use App\models\resource;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class ContentController extends BaseController
{
	public function contentList(Request $request) {
		$token = JWTAuth::getToken();
		$clients = $this->getUserInfo($token);
		$page = $request->input('page', '0');
		$size = $request->input('numperpage', '20');
		$query = $request->input('query', '');
		$query = json_decode($query, True);
		$offset = $page * $size;
		if (!empty($clients['status_code'])) {
			if ($clients['status_code'] == '401') {
				$error['status_code'] = '10001';
				$error['error_message'] = '用户token验证失败， 请刷新重试';
				return response()->json($error);
			}
		}

		// 验证权限
		$roles = ['root','admin','audit1'];
		if ( !in_array($clients['role'], $roles)) {
			$return['status_code'] = '10002';
			$return['error_message'] = '权限不足';
			return response()->json($return);
		}


		$contentQuery = contents::orderBy('is_check', 'asc')->orderBy('sid','desc');
		if ( isset($query['check'])&&( $query['check']!="" ) ){
			$contentQuery->where('is_check', $query['check']);
		}
		$contents = $contentQuery->offset($offset)->limit($size)->get()->ToArray();
		// 修改信息
		foreach ($contents as $key=>$content){
			$contentResource = resource::where('cid',$content['cid'])->get()->toArray();
			$contents[$key]['resource']= $contentResource;
		}

		$contentQuery = contents::orderBy('is_check', 'asc');
		if ( isset($query['check'])&&( $query['check']!="" ) ){
			$contentQuery->where('is_check', $query['check']);
		}
		$count = $contentQuery->count();

		$pagenum = ceil($count/$size);
		$return['status_code'] = '200';
		$return['data'] = $contents;
		$return['pagenum'] = $pagenum;
		$return['count'] = $count;
		return response()->json($return);
	}

	public function contentCheck(Request $request,$id) {

		$token = JWTAuth::getToken();
		$clients = $this->getUserInfo($token);
		$check = $request->input('check', 1);

		if (!empty($clients['status_code'])) {
			if ($clients['status_code'] == '401') {
				$error['status_code'] = '10001';
				$error['error_message'] = '用户token验证失败， 请刷新重试';
				return response()->json($error);
			}
		}

		// 验证权限
		$roles = ['root','admin','audit1'];
		if ( !in_array($clients['role'], $roles)) {
			$return['status_code'] = '10002';
			$return['error_message'] = '权限不足';
			return response()->json($return);
		}

		// 修改信息
		contents::where('cid', $id)->update(['is_check' => $check]);

		$return['status_code'] = '200';
		$return['check'] = $check;
		return response()->json($return);
	}
}