<?php

namespace App\Http\Middleware;

use Closure;

class Cors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {

        //if (isset($_SERVER['HTTP_ORIGIN'])) {
            //header("Access-Control-Allow-Credentials: true");
            //header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
            //header('Access-Control-Allow-Origin: *');
            //header("Access-Control-Allow-Headers: *, X-Requested-With, Content-Type");
            //header('Access-Control-Allow-Methods:OPTIONS, GET, POST,PUT,DELETE');
            //header('Access-Control-Max-Age: 3600');
        //}
	//https://servicewechat.com/wx1ad97741a12767f9/devtools/page-frame.html
      $referer = $request->header('referer');
      $path = substr($referer,strlen('https://servicewechat.com/'));
      $pathArr = explode('/',$path);
      if($pathArr[0] == 'wx1ad97741a12767f9'){
        config(['wxxcx.client_target'=>'gl']);
        config(['wxxcx.appid'=>$pathArr[0]]);
        config(['wxxcx.secret'=>'001b7d3059af1a707a5d4e432aa45b7a']);
        config(['wxxcx.mchid'=>'1487651632']);
        config(['wxxcx.mch_secret_key'=>'SDJy3O9G8pQevPY1Jb5Ayy2LSIzK4yB3']);
        config(['wxxcx.sslcert_path'=>'cert/apiclient_cert.pem']);
        config(['wxxcx.sslkey_path'=>'cert/apiclient_key.pem']);
      }else if($pathArr[0] == 'wx26febf4b18436b7c'){
        config(['wxxcx.client_target'=>'gl+']);
        config(['wxxcx.appid'=>$pathArr[0]]);
        config(['wxxcx.secret'=>'a6d572554e877110ec7125e1cc1f3a19']);
        config(['wxxcx.mchid'=>'1498731322']);
        config(['wxxcx.mch_secret_key'=>'SDJy3O9G8pQevPY1Jb5Ayy2LSIzK4yB3']);
        config(['wxxcx.sslcert_path'=>'cert/gl+/apiclient_cert.pem']);
        config(['wxxcx.sslkey_path'=>'cert/gl+/apiclient_key.pem']); 
      }else if($pathArr[0] == 'wx899ded1a3c0a5c33'){
        config(['wxxcx.client_target'=>'jczc']);
        config(['wxxcx.appid'=>$pathArr[0]]);
        config(['wxxcx.secret'=>'8d0cf4e33c0fb8a43a70bb660400f2c6']);
        config(['wxxcx.mchid'=>'1498197032']);
        config(['wxxcx.mch_secret_key'=>'SDJy3O9G8pQevPY1Jb5Ayy2LSIzK4yB3']);
        config(['wxxcx.sslcert_path'=>'cert/jczc/apiclient_cert.pem']);
        config(['wxxcx.sslkey_path'=>'cert/jczc/apiclient_key.pem']);
      }
        $response = $next($request);
        return $response;
    }
}
