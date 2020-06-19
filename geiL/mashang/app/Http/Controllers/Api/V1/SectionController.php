<?php

namespace App\Http\Controllers\Api\V1;

use App\models\source;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tymon\JWTAuth\Facades\JWTAuth;
use Ramsey\Uuid\Uuid;

class SectionController extends BaseController
{
    /*
     * section用户获取数据接口
     * pub/section    GET                   获取section栏目列表
     * pub/section/:id/source    GET        获取一个section下面对应的source列表
     */

    /**
     *获取section栏目列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSections(Request $request){
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (empty($token) || !isset($clients['id'])) {
            $return['status_code'] = 10001;
            $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
            return response()->json($return);
        }

        $data = DB::table('section')->select('id','name')->orderBy('rank','asc')->get();

        $return['status_code'] = '200';
        $return['data'] = $data->ToArray();

        return response()->json($return);
    }

    /**
     * 获取一个section下面对应的source列表
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSectionSources(Request $request,$id){
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (empty($token) || !isset($clients['id'])) {
            $return['status_code'] = 10001;
            $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
            return response()->json($return);
        }

        $page = $request->input('page', 0);
        $numberpage = $request->input('numberpage', 50);
        $offset = $page * $numberpage;

        //推荐的料列表
        $count = Redis::zCard(config('constants.SECTION_KEY').$id);

        $dataList = [];
        $source_ids = Redis::zRevRange(config('constants.SECTION_KEY').$id, $offset, $numberpage);
        foreach($source_ids as $sourceid){
            $data = DB::table('source')->select('id','uid','title','price','tag_value','createtime','status')
                ->where('id',$sourceid)->first();
            $data = (array)$data;
            if(!empty($data)){
                $tagInfo = DB::table('tag')->select('tag_name')->where('tag_value',$data['tag_value'])->first();
                if($tagInfo){
                    $data['tag_name'] = $tagInfo->tag_name;
                }
                $userInfo = DB::table('client')->select('nickname','avatarurl')->where('id',$data['uid'])->first();
                if($userInfo){
                    $data['nickname'] = $userInfo->nickname;
                    $data['avatarurl'] = $userInfo->avatarurl;
                }
                array_push($dataList,$data);
            }
        }

        if ( $count == 0) {
            $pagenum = 0;
        } else {
            $pagenum = ceil($count/$numberpage);
        }

        $return['status_code'] = '200';
        $return['count'] = $count;
        $return['pagenum'] = $pagenum;
        $return['data'] = $dataList;

        return response()->json($return);
    }
}
