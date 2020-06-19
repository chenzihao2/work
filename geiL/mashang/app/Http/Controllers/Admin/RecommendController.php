<?php

namespace App\Http\Controllers\Admin;

use App\models\recommend;
use App\models\section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Ramsey\Uuid\Uuid;
use Tymon\JWTAuth\Facades\JWTAuth;

class RecommendController extends BaseController
{
    /*
     * recommend后台管理接口
     * admin/recommend    GET           获取“精彩推荐”对应的料列表
     * admin/recommend    POST          新增一条料数据到“精彩推荐”栏目中
     * admin/recommend/:id    PUT       更新数据
     * admin/recommend/:id    DELETE    从“精彩推荐”删除一条数据
     */

    /**
     * 获取推荐栏目的列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRecommends(Request $request)
    {
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);
        //用户token和权限的验证
        $this->checkToken($clients);

        $page = $request->input('page', '0');
        $numberpage = $request->input('numberpage', '50');
        $query = $request->input('query', '');
        $query = json_decode($query, True);
        $offset = $page * $numberpage;

        $recommend = DB::table('recommend');

        /*$recommend = recommend::select()
            ->LeftJoin('source','source.id','recommend.source_id')
            ->LeftJoin('source_extra','source_extra.id','recommend.source_id')
            ->LeftJoin('tag','tag.tag_value','source.tag_value');*/

        if ( !empty( $query['source_id'] ) )
            $recommend->where('source_id', '=', $query['source_id']);

        if ( !empty( $query['type'] ) )
            $recommend->where('type', '=', $query['type']);

        if ( !empty( $query['createtime']['from'] ) )
            $recommend->whereBetween('createtime', [$query['createtime']['from'], $query['createtime']['to']]);
        //总数量
        $count = $recommend->count();

        $recommend->orderBy('rank', 'asc');

        if ( $offset != 0 )
            $recommend->offset($offset);
        $recommend->limit($numberpage);
        $data = $recommend->get();

        $pagenum = ceil($count/$numberpage);

        $return['status_code'] = '200';
        $return['count'] = $count;
        $return['pagenum'] = $pagenum;
        $return['data'] = $data->ToArray();

        return response()->json($return);

    }

    /**
     * 在推荐列表添加一个料
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function postRecommend(Request $request)
    {
        Redis::zAdd('sourcekey', 1, 'val2');
        Redis::zAdd('sourcekey', 1, 'val1');
        Redis::zAdd('sourcekey', 0, 'val0');
        Redis::zAdd('sourcekey', 5, 'val5');
        Redis::zRange('sourcekey', 0, -1);

        /*$token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);
        //用户token和权限的验证
        $this->checkToken($clients);

        $recommend_section = section::where('rank','=',1)->first();
        $section_id = $recommend_section['id'];
        $source_id = $request->input('source_id', '');
        $rank = $request->input('rank','');
        $type = $request->input('type','');

        $recommends = DB::table('recommend')->where('section_id',$section_id)->where('source_id',$source_id)->first();
        if($recommends){
            $return['status_code'] = '10003';
            $return['error_message'] = '此料已存在列表中';
            return response()->json($return);
        }

        $currenttime = date("Y-m-d H:i:s", time());
        $recommend['id'] = Redis::incr('recommend_id');
        $recommend['section_id'] = $section_id;
        $recommend['source_id'] = $source_id;
        $recommend['type'] = $type;
        $recommend['rank'] = $rank;
        $recommend['createtime'] = $currenttime;
        $recommend['modifytime'] = $currenttime;
        $res = DB::table('recommend')->insert($recommend);
        if($res){
            $return['status_code'] = '200';
            $return['data'] = $recommend;
        }else{
            $return['status_code'] = '10010';
            $return['error_message'] = '写入数据失败';
        }
        return response()->json($return);*/
    }

    /**
     * 更新一个推荐料的信息
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function putRecommend(Request $request, $id)
    {
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);
        //用户token和权限的验证
        $this->checkToken($clients);

        $source_id = $request->input('source_id', '');
        $rank = $request->input('rank','');
        $type = $request->input('type','');

        if($source_id != ''){
            $recommend['source_id'] = $source_id;
        }
        if($rank != ''){
            $recommend['rank'] = $rank;
        }
        if($type != ''){
            $recommend['type'] = $type;
        }
        $recommend['modifytime'] = date("Y-m-d H:i:s", time());
        $res = DB::table('recommend')->where('id', $id)->update($recommend);

        if($res){
            $return['status_code'] = '200';
        }else{
            $return['status_code'] = '10010';
            $return['error_message'] = '写入数据失败';
        }
        return response()->json($return);
    }

    /**
     * 从推荐料列表删除一个料关联
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteRecommend(Request $request, $id)
    {
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);
        //用户token和权限的验证
        $this->checkToken($clients);

        $recommend = DB::table('recommend')->where('id', $id)->first();
        if( !$recommend ) {
            $return['status_code'] = '10006';
            $return['error_message'] = '您要删除的数据不存在';
            return response()->json($return);
        }

        $res = DB::table('recommend')->where('id', $id)->delete();
        if(!$res){
            $return['status_code'] = '10008';
            $return['error_message'] = '删除数据失败';
            return response()->json($return);
        }
        $return['status_code'] = '200';
        return response()->json($return);
    }
}
