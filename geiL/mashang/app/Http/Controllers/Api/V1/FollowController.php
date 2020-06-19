<?php
/**
 * User: WangHui
 * Date: 2018/9/26
 * Time: 17:43
 */

namespace App\Http\Controllers\Api\V1;

use App\models\client;
use App\models\client_subscribe;
use App\models\follow;
use Dingo\Api\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use \QKPHP\SNS\Weixin as WeixinService;
use \QKPHP\Common\Config\Config;
use \QKPHP\Common\Utils\Url;
use \QKPHP\SNS\Consts\Platform;

class FollowController extends BaseController
{
    /**
     * 获取关注列表
     * @param Request $request
     * @param $uid
     * @return \Illuminate\Http\JsonResponse
     */
    public function followList(Request $request, $uid) {
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (empty($token) || !isset($clients['id']) || $clients['id'] != $uid) {
            $return['status_code'] = 10001;
            $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
            return response()->json($return);
        }
        $page = $request->input('page', '0');
        $numberpage = $request->input('numberpage', '20');
        $nickname = $request->input('nickname', '');
        $offset = ($page-1) * $numberpage;

//        $followList = follow::select('star')->where('fans', $uid)->where('status', 1)->offset($offset)->limit($numberpage)->get()->toArray();
//        $followCount = follow::select('star')->where('fans', $uid)->where('status', 1)->count();
//        $followResult = [];
//        foreach ($followList as $key => $followInfo) {
//            $startInfo = client::where('id', $followInfo['star'])->first()->toArray();
//            $follow['id'] = $followInfo['star'];
//            $follow['avatarurl'] = $startInfo['avatarurl'];
//            $follow['nickname'] = $startInfo['nickname'];
//            $followResult[] = $follow;
//        }

        $followListQuery = follow::select('follow.star', 'client.id', 'client.avatarurl', 'client.nickname')->LeftJoin('client', 'client.id', 'follow.star')->where('follow.fans', $uid)->where('follow.status', 1);
        if($nickname){
            $followListQuery->where('client.nickname','like','%'.$nickname.'%');
        }
        $followList = $followListQuery->offset($offset)->limit($numberpage)->get()->toArray();


        $followCountQuery = follow::select('follow.star')->LeftJoin('client', 'client.id', 'follow.fans')->where('follow.fans', $uid)->where('follow.status', 1);
        if($nickname){
            $followCountQuery->where('client.nickname','like','%'.$nickname.'%');
        }
        $followCount = $followCountQuery->count();

        $return['status_code'] = '200';
        $return['pagenum'] = $followCount;
        $return['data'] = $followList;

        return response()->json($return);
    }

    /**
     * 取消关注
     * @param Request $request
     * @param $uid
     * @return \Illuminate\Http\JsonResponse
     */
    // public function setFollow(Request $request, $uid) {
    // 	$token = JWTAuth::getToken();
    // 	$clients = $this->UserInfo($token);
    // 	if (empty($token) || !isset($clients['id']) || $clients['id'] != $uid) {
    // 		$return['status_code'] = 10001;
    // 		$return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
    // 		return response()->json($return);
    // 	}
    // 	$followId = $request->input('followid', 0);
    // 	if (empty($followId)) {
    // 		$return['status_code'] = 10002;
    // 		$return['error_message'] = "请传入关注id";
    // 		return response()->json($return);
    // 	}


    // 	follow::where('star', $followId)->where('fans', $uid)->where('status', 1)->update([
    // 		'status' => 0
    // 	]);
    // 	$return['status_code'] = '200';
    // 	$return['data'] = [];

    // 	return response()->json($return);
    // }

    /**
     * 关注操作
     * @param Request $request
     * @param         $uid
     * @return \Illuminate\Http\JsonResponse
     */
    public function setFollow(Request $request, $uid){
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        $redirect = request('r', '');
        if (empty($token) || !isset($clients['id']) || $clients['id'] != $uid) {
            $return['status_code'] = 10001;
            $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
            Header("Location:".$redirect);
            //return response()->json($return);
        }
        $followId = $request->input('followid', 0);
        if (empty($followId)) {
            $return['status_code'] = 10002;
            $return['error_message'] = "请传入关注id";
            return response()->json($return);
        }

        $code = request('code', '');
        $appid = config("wxxcx.wechat_subscribe_appid");
        $appsecret = config("wxxcx.wechat_subscribe_appsecret");
        //仅关注
        $just_follow = request('just_follow', 0);

        if (!empty($code)) {
            if (strpos($redirect, '?')) {
                $redirect .= '&';
            } else {
                $redirect .= '?';
            }
            if ($just_follow) {
                $redirect .= 'end=1';
            } else {
                $redirect .= 'tanchuang=1&uid=' . $followId;
            }
            $weixinService = new WeixinService($appid, $appsecret, ['platform' => Platform::H5, 'scope' => 'user']);
            $wxUser = $weixinService->getUserInfo($code);
            //$weixinService = new WeixinService($appid, $appsecret);
            //$accessToken = $weixinService->getSessionAccessTokenByAuth($code);
            //$wxUser = $weixinService->getUserInfoByAuth();
            if(isset($wxUser['errcode'])){
                if (!$just_follow) {
                    Header("Location:".$redirect);
                }
            }

            $openId = $wxUser['openId'];

            $subscribeInfo = client_subscribe::where('openid', $openId)->where('appid', $appid)->first();
            if(empty($subscribeInfo)){
                $userInfo = client::where('unionid',$wxUser['unionId'])->first();
                if(!empty($userInfo)){
                    $data = [];
                    $data['user_id'] = $userInfo['id'];
                    $data['appid'] = $appid;
                    $data['openid'] = $openId;
                    $data['create_time'] = time();
                    client_subscribe::create($data);
                }
            }

            //记录用户关注
            $follow_info = follow::where('star', $followId)->where('fans', $uid)->first();
            if(empty($follow_info)){
                $follow = [];
                $follow['star'] = $followId;
                $follow['fans'] = $uid;
                $follow['create_time'] = time();
                follow::create($follow);
            }else{
                follow::where('star', $followId)->where('fans', $uid)->update([
                    'status' => 1
                ]);
            }

            Header("Location:".$redirect);
        } else {
            $redirect_uri = urlencode(config('constants.backend_domain') . "/pub/user/".$uid."/setfollow?followid=" . $followId . "&token=" . $token . "&just_follow=" . $just_follow . "&r=" . urlencode($redirect));
            $response_type = 'code';
            $scope = 'snsapi_userinfo';
            $state = 'STATE';
            $url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$appid."&redirect_uri=".$redirect_uri."&response_type=".$response_type."&scope=".$scope."&state=".$state."#wechat_redirect";
            Header("Location:".$url);
        }


    }


    /**
     * 取消关注
     * @param Request $request
     * @param $uid
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelFollow(Request $request, $uid) {
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (empty($token) || !isset($clients['id']) || $clients['id'] != $uid) {
            $return['status_code'] = 10001;
            $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
            return response()->json($return);
        }
        $followId = $request->input('followid', 0);
        if (empty($followId)) {
            $return['status_code'] = 10002;
            $return['error_message'] = "请传入关注id";
            return response()->json($return);
        }


        follow::where('star', $followId)->where('fans', $uid)->where('status', 1)->update([
            'status' => 0
        ]);
        $return['status_code'] = '200';
        $return['data'] = [];

        return response()->json($return);
    }



    /**
     * 关注检查
     * @param Request $request
     * @param $uid
     * @return \Illuminate\Http\JsonResponse
     */
    public function followCheck(Request $request, $uid) {
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (empty($token) || !isset($clients['id']) || $clients['id'] != $uid) {
            $return['status_code'] = 10001;
            $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
            return response()->json($return);
        }
        $starId = $request->input('starid', 0);
        if (empty($starId)) {
            $return['status_code'] = 10002;
            $return['error_message'] = "请传入关注id";
            return response()->json($return);
        }

        $userInfo = client::select('id', 'nickname', 'avatarurl')->where('id', $starId)->first();

        $followInfo = follow::where('star', $starId)->where('fans', $uid)->first();
        if(empty($followInfo)){
            $result = false;
        }else{
            if ($followInfo['status']==1){
                $result = true;
            }else{
                $result = false;
            }
        }
        $return['status_code'] = '200';
        $return['data'] = array(
          'suid' => $userInfo['id'],
          'nickname' => $userInfo['nickname'],
          'avatarurl' => $userInfo['avatarurl'],
          'follow' => $result
        );

        return response()->json($return);
    }

    /**
     * 获取粉丝列表
     * @param Request $request
     * @param $uid
     * @return \Illuminate\Http\JsonResponse
     */
    public function fansList(Request $request, $uid) {
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (empty($token) || !isset($clients['id']) || $clients['id'] != $uid) {
            $return['status_code'] = 10001;
            $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
            return response()->json($return);
        }
        $page = $request->input('page', '0');
        $numberpage = $request->input('numberpage', '20');
        $nickname = $request->input('nickname', '');
        $offset = ($page-1) * $numberpage;

        $fansListQuery = follow::select('follow.fans', 'client.id', 'client.avatarurl', 'client.nickname')->LeftJoin('client', 'client.id', 'follow.fans')->where('follow.star', $uid)->where('follow.status', 1);
        if($nickname){
            $fansListQuery->where('client.nickname','like','%'.$nickname.'%');
        }
        $fansList = $fansListQuery->offset($offset)->limit($numberpage)->get()->toArray();


        $fansCountQuery = follow::select('follow.fans')->LeftJoin('client', 'client.id', 'follow.fans')->where('follow.star', $uid)->where('follow.status', 1);
        if($nickname){
            $fansCountQuery->where('client.nickname','like','%'.$nickname.'%');
        }
        $fansCount = $fansCountQuery->count();

        $return['status_code'] = '200';
        $return['pagenum'] = $fansCount;
        $return['data'] = $fansList;

        return response()->json($return);
    }

    /**
     * 删除粉丝
     * @param Request $request
     * @param $uid
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelFans(Request $request, $uid) {
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (empty($token) || !isset($clients['id']) || $clients['id'] != $uid) {
            $return['status_code'] = 10001;
            $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
            return response()->json($return);
        }
        $fansId = $request->input('fansid', 0);
        if (empty($fansId)) {
            $return['status_code'] = 10002;
            $return['error_message'] = "请传入粉丝id";
            return response()->json($return);
        }


        follow::where('fans', $fansId)->where('star', $uid)->where('status', 1)->update([
            'status' => 0
        ]);
        $return['status_code'] = '200';
        $return['data'] = [];

        return response()->json($return);
    }
}
