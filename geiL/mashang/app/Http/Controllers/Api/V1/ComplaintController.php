<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use App\models\resource;
use Ufile;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use App\Console\Commands\sendnotice;

class ComplaintController extends BaseController
{
    private $time_limit = 600; //s

    /**
     * 图片上传接口
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function upload(Request $request)
    {
        // 根据token 获取用户信息
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (empty($token) || !isset($clients['id'])) {
            return $this->errorReturn(10001,"token 失效或异常， 以正常渠道获取重试");
        }

        $file = $request->file('file', "");

        // 文件不可为空
        if ( empty($file) ) {
            $error['status_code'] = "10003";
            $error['message'] = "文件不可为空";
            return response()->json($error);
        }

        $mimeTye = $file->getMimeType();    // 临时文件的类型
        $extension = $file->getClientOriginalExtension();    // 扩展名

		    $path = $this->checkPathExist();
        $name = time().'.'.$extension;
        $position = 'complaints/'.$name;
        $file->move($path, $name);

        $url = $this->upload2Qiniu($path, $name);
	if(!empty($url)) {
          $return['status_code'] = "200";
	        $return['data'] = array('url'=>config('qiniu.host') . '/' . $url);
          return response()->json($return);
        }else {
          $error['status_code'] = "10003";
          $error['message'] = "图片上传失败";
          return response()->json($error);
        }

    }

    /**
     * 投诉接口
     */
    public function complaints(Request $request)
    {
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (empty($token) || !isset($clients['id'])) {
            return $this->errorReturn(10001,"token 失效或异常， 以正常渠道获取重试");
        }

        $sid = $request->input('sid','');
        $s_uid = $request->input('suid','');
        $type = $request->input('type','');
        $s_title = $request->input('title','');
        $telephone = $request->input('telephone','');
        $pictures = $request->input('pictures','');
        if(!empty($pictures)) {
          if(!is_array($pictures)) {
            $pictures = json_decode($pictures, true);
          }
          $pictures = implode(',', $pictures);
        }
        $content = $request->input('content','');

        $complaint['uid'] = $clients['id'];
        $complaint['sid'] = $sid;
        $complaint['suid'] = $s_uid;
        $complaint['telephone'] = $telephone;
        $complaint['status'] =0;
        $complaint['type'] = $type;
        $complaint['s_title'] = $s_title;
        $complaint['content'] = $content;
        $complaint['pictures'] = $pictures;
        $complaint['createtime'] = date('Y-m-d H:i:s');
        $complaint['modifytime'] = date('Y-m-d H:i:s');
        DB::table('complaints')->insert($complaint);
        $this->security_check();

        $return['status_code'] = '200';
        $return['data'] = ['code' => 'success'];
        return response()->json($return);
    }

    private function security_check() {
        $key_time = date('Y-m-d H:i:s', time() - $this->time_limit);
        $info = DB::table('complaints')->where('createtime', '>', $key_time)->limit(2)->get()->toArray();
        if (count($info) == 2) {
            \Log::info('complaints > 2');
            $sendnotice = new sendnotice();
            $sendnotice->warning(2);
        }
    }

    private function upload2Ucloud($path,$name) {
        $objUfile = new Ufile();
        $position = 'complaints/'.$name;
        $path = public_path().'/'.$path.'/'.$name;
        $res = $objUfile->put('qiudashizy', $position, $path);
        return $res;
    }
    
    private function upload2Qiniu($path, $name) {
      $upToken = $this->getUploadToken();
      $key = 'complaints/' . date('Ym') . '/' . date('d') .'/'. $name;
      $filePath = public_path().'/'.$path.'/'.$name;
      $res = $this->qiniuUploadFile($upToken, $key, $filePath);
      return $res;
    }

	/**
	 * 检查今日目录是否存在
	 */
	private function checkPathExist() {
    	$time = date("Ymd",time());
      $pathString = public_path()."/complaints/".$time;
    	if(!is_dir($pathString)){
			  mkdir($pathString,0777,true);
		  }
		  return "complaints/".$time;
  }

}
