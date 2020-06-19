<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use App\models\resource;
use Ufile;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Ramsey\Uuid\Uuid;

class ResourceController extends BaseController
{

    /**
     * 料上传接口
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function postUpload(Request $request)
    {
        // 根据token 获取用户信息
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);

		$path = $this->checkPathExist();
        $types = ['2', '3', '1', '8', '16'];

        // 文件类型（1，文字 2，语音 3，图片 4，视频 5，文件）
        $type = $request->input('type', "3");
        $file = $request->file('file', "");
        $sindex = $request->file('index', "");
        $uid = $request->input('uid', "");

        if ( empty($token) || $clients['id'] != $uid ) {
            $return['status_code'] = "10001";
            $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
            return response()->json($return);
        }

        // 判断类型是否符合
        if (!in_array($type, $types)) {
            $error['status_code'] = "10002";
            $error['message'] = "不符合指定类型";
            return response()->json($error);
        }

        // 文件不可为空
        if ( empty($file) ) {
            $error['status_code'] = "10003";
            $error['message'] = "文件不可为空";
            return response()->json($error);
        }

        $realPath = $file->getRealPath();   // 临时文件的绝对路径
        $mimeTye = $file->getMimeType();    // 临时文件的类型
        $extension = $file->getClientOriginalExtension();    // 扩展名

        $uuid1 = Uuid::uuid1();
        $resource_id = $uuid1->getHex();
        $name = $resource_id.'.'.$extension;
        $position = 'ziyuan/'.$name;

//        $file->move('source', $name);
        $file->move($path, $name);

        // 异步执行的目标脚本
        $url = env('APP_URL')."/pub/resource/async";

        $post_data = array(
          'name' => $name,
        );

        // 执行异步上传， 保存至ucloud服务器
        //$this->trigger_async_request($url, $post_data);
        $this->upload2Ucloud($path,$name);

        $model = new resource();
        $model->id = $resource_id;
        $model->uid = $uid;
        if ( !empty($sindex) )
            $model->sindex = $sindex;
        $model->stype = $type;
        $model->url = 'https://zy.qiudashi.com/'.$position;
        $model->save();

        $return['status_code'] = "200";
        $return['rid'] = $resource_id;
	$return['data'] = array('rid'=>$resource_id);

        return response()->json($return);

    }


    /**
     * 料上传接口
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function postUploadNew(Request $request)
    {
        // 根据token 获取用户信息
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);

		$path = $this->checkPathExist();
        $types = ['2', '3', '1', '8', '16'];

        // 文件类型（1，文字 2，语音 3，图片 4，视频 5，文件）
        $type = $request->input('type', "3");
        $file = $request->file('file', "");
        $sindex = $request->file('index', "");
        $uid = $request->input('uid', "");

        if ( empty($token) || $clients['id'] != $uid ) {
            $return['status_code'] = "10001";
            $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
            return response()->json($return);
        }

        // 判断类型是否符合
        if (!in_array($type, $types)) {
            $error['status_code'] = "10002";
            $error['message'] = "不符合指定类型";
            return response()->json($error);
        }

        // 文件不可为空
        if ( empty($file) ) {
            $error['status_code'] = "10003";
            $error['message'] = "文件不可为空";
            return response()->json($error);
        }

        $realPath = $file->getRealPath();   // 临时文件的绝对路径
        $mimeTye = $file->getMimeType();    // 临时文件的类型
        $extension = $file->getClientOriginalExtension();    // 扩展名

        $uuid1 = Uuid::uuid1();
        $resource_id = $uuid1->getHex();
        $name = $resource_id.'.'.$extension;
        $position = 'ziyuan/'.$name;
//        $position = substr($path,1).'/'.$name;;

//        $file->move('source', $name);
        $file->move($path, $name);

        // 异步执行的目标脚本
        $url = env('APP_URL')."/pub/resource/async";

        $post_data = array(
          'name' => $name,
        );

        // 执行异步上传， 保存至ucloud服务器
        //$this->trigger_async_request($url, $post_data);
        //$this->upload2Ucloud($path,$name);
	$url = $this->upload2Qiniu($path, $name);

//        $model = new resource();
//        $model->id = $resource_id;
//        $model->uid = $uid;
//        if ( !empty($sindex) )
//            $model->sindex = $sindex;
//        $model->stype = $type;
//        $model->url = 'https://zy.qiudashi.com/'.$position;
//        $model->save();

        $return['status_code'] = "200";
        $return['rid'] = config('qiniu.host') . '/' . $url;
	$return['data'] = array('rid'=>config('qiniu.host') . '/' . $url);

        return response()->json($return);

    }


        /**
         * 文字内容上传（已弃用）
         */
        public function postUploadDesc(Request $request)
    {
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);

        $types = '1';

        // 文件类型（1，文字 2，图片 3，语音 4，视频 5，文件）
        $type = $request->input('type', "");
        $desc = $request->input('desc', "");
        $uid = $request->input('uid', "");

        if ( empty($token) || $clients['id'] != $uid ) {
            $return['status_code'] = "10001";
            $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
            return response()->json($return);
        }

        // 判断类型是否符合
        if ( $type != $types || empty($desc)) {
            $error['status_code'] = "10002";
            $error['message'] = "不符合指定类型";
            return response()->json($error);
        }

        $uuid1 = Uuid::uuid1();
        $resource_id = $uuid1->getHex();
        $model = new resource();
        $model->id = $resource_id;
        $model->uid = $uid;
        $model->stype = $type;
        $model->description = $desc;
        $model->save();

        $return['status_code'] = "200";
        $return['resourceid'] = $resource_id;

        return response()->json($return);

    }

    private function upload2Qiniu($path, $name) {
      $upToken = $this->getUploadToken();
      $key = 'ziyuan/'.$name;
      $filePath = $path.'/'.$name;
      $res = $this->qiniuUploadFile($upToken, $key, $filePath);
      return $res;
    }


    /**
     * 异步执行方法
     * string $url 地址
     * array  $post_data 参数
    */
    public function trigger_async_request($url, $post_data = array())
    {
        \Log::INFO('异步上传开始执行');
        $method = empty($post_data) ? "GET" : "POST";
        $url_array = parse_url($url);
        $port = isset($url_array['port']) ? $url_array['port'] : 80;
        if($url_array['scheme'] == "https") {
            $url_array['host'] = 'ssl://'.$url_array['host'];
            $port = '443';
        }
        $fp = fsockopen($url_array['host'], $port, $errno, $errstr, 30);
        if (!$fp) {
            return false; // 无法打开socket连接
        }

        $getPath = isset($url_array['query']) ? $url_array['path'] . "?" . $url_array['query'] : $url_array['path'];
        \Log::INFO('正在执行');
        $header = $method . " " . $getPath . " HTTP/1.1\r\n";
        $header .= "Host: 10.10.139.114\r\n";
        if (!empty($post_data)) {
            $post_data = http_build_query($post_data);
            $header .= "Content-Type: application/x-www-form-urlencoded\r\n";
            $header .= "Content-Length: " . strlen($post_data) . " \r\n";
        }
        $header .= "Connection: Close\r\n\r\n";
        if (!empty($post_data)) {
            $header .= $post_data . "\r\n\r\n"; //传递POST数据
        }

        fwrite($fp, $header);

        // 等待30ms,这对于nginx服务器很重要,让nginx有足够的时间将请求转交给php-fpm。
        // 否则,如果在nginx转交请求前识别到用户断开连接,那么就不会继续转交请求了。
        usleep(30000);

//        fclose($fp);
        \Log::INFO('执行成功');
        return true;
    }

    /**
     * 执行异步上传
    */
    public function postAsync(Request $request)
    {
        $name = $request->input("name", "");
        \Log::INFO("接收到请求".$name);
        if( empty($name) ) {
            \Log::INFO("未获取到资源".$name);exit();
        }
        $objUfile = new Ufile();
        $bucket = "qiudashizy";
        $position = 'ziyuan/'.$name;
        $path = 'source/'.$name;

        $re = $objUfile->put($bucket, $position, $path);
        if( $re ) {
            \Log::INFO("上传成功".$name);
        } else {
            \Log::INFO("上传失败".$name);
        }
    }

    private function upload2Ucloud($path,$name) {
        \Log::INFO("接收到请求".$name);
        if( empty($name) ) {
            \Log::INFO("未获取到资源".$name);exit();
        }
        $objUfile = new Ufile();
        $bucket = "qiudashizy";
        $position = 'ziyuan/'.$name;
//        $path = 'source/'.$name;
        $path = $path.'/'.$name;

        $re = $objUfile->put($bucket, $position, $path);
        if( $re ) {
            \Log::INFO("上传成功".$name);
        } else {
            \Log::INFO("上传失败".$name);
        }
    }
    private function upload2UcloudNew($path,$name) {
        \Log::INFO("接收到请求".$name);
        if( empty($name) ) {
            \Log::INFO("未获取到资源".$name);exit();
        }
        $objUfile = new Ufile();
        $bucket = "qiudashizy";
        $position = 'ziyuan/'.$name;
        $position = substr($path,1).'/'.$name;
//        $path = 'source/'.$name;
		$path = public_path().$path.'/'.$name;

        $re = $objUfile->put($bucket, $position, $path);
        if( $re ) {
            \Log::INFO("上传成功".$name);
        } else {
            \Log::INFO("上传失败".$name);
        }
    }

	/**
	 * 检查今日目录是否存在
	 */
	private function checkPathExistNew() {
    	$time = date("Ymd",time());
    	$pathString = public_path()."/newsource/".$time;
    	if(!is_dir($pathString)){
			mkdir($pathString,0777,true);
		}
		return "/newsource/".$time;
    }
    /**
     * 检查今日目录是否存在
     */
    private function checkPathExist() {
        $time = date("Ymd",time());
        $pathString = public_path()."/newsource/".$time;
        if(!is_dir($pathString)){
            mkdir($pathString,0777,true);
        }
        return $pathString;
    }

}
