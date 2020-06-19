<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\models\tag;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class TagController extends BaseController
{
    /*
     * tag后台管理接口
     * admin/tag    GET                 获取所有标签列表
     * admin/tag/:tagvalue    PUT       更新标签信息
     * admin/source/:sid/tag   PUT      为source添加标签
     */

    /**
     * 获取标签列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTags(Request $request)
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

        $taglist = DB::table('tag');
        if ( !empty( $query['tag_value'] ) )
            $taglist->where('tag_value', '=', $query['tag_value']);

        if ( !empty( $query['tag_name'] ) )
            $taglist->where('tag_name', 'like', $query['tag_name']);

        if ( !empty( $query['createtime']['from'] ) )
            $taglist->whereBetween('createtime', [$query['createtime']['from'], $query['createtime']['to']]);

        // 总数
        $count = $taglist->count();

        if ( $offset != 0 )
            $taglist->offset($offset);
        $taglist->limit($numberpage);
        $data = $taglist->get();

        $pagenum = ceil($count/$numberpage);

        $return['status_code'] = '200';
        $return['count'] = $count;
        $return['pagenum'] = $pagenum;
        $return['data'] = $data->ToArray();

        return response()->json($return);

    }

    /**
     * 更新tag标签的数据
     * @param Request $request
     * @param $tagvalue
     * @return \Illuminate\Http\JsonResponse
     */
    public function putTagInfo(Request $request, $tagvalue)
    {
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);
        //用户token和权限的验证
        $this->checkToken($clients);

        if ( $request->input('tag_name', '') != '')
            $tagInfo['tag_name'] = $request->input('tag_name', '');

        $tagInfo['modifytime'] = date("Y-m-d H:i:s", time());

        $res = DB::table('tag')->where('tag_value', $tagvalue)->update($tagInfo);

        if($res){
            $return['status_code'] = '200';
        }else{
            $return['status_code'] = '10010';
            $return['error_message'] = '写入数据失败';
        }
        return response()->json($return);
    }

    /**
     * 给一个料(source)添加标签(tag)
     * @param Request $request
     * @param $sid
     * @return \Illuminate\Http\JsonResponse
     */
    public function putSourceTag(Request $request, $sid)
    {
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);
        //用户token和权限的验证
        $this->checkToken($clients);

        $tagName = $request->input('tag_name', '');

        if(empty($tagName)){
            $return['status_code'] = '10003';
            $return['error_message'] = '标签名不可为空';
            return response()->json($return);
        }

        $only = DB::table('tag')->where('tag_name', $tagName)->first();
        $source_tag = DB::table('source')->select('tag_value')->where('id',$sid)->first();
        if ( $only ) {
            if(in_array($only->tag_value,explode(',',$source_tag))){
                $return['status_code'] = '10005';
                $return['error_message'] = '该料已有此标签';
            }
            $res = DB::table('source')->where('id', $sid)->update(['tag_value'=>$only->tag_value]);
            if($res){
                $return['status_code'] = '200';
            }else{
                $return['status_code'] = '10010';
                $return['error_message'] = '新建数据失败';
            }
            return response()->json($return);
        }

        $currenttime = date("Y-m-d H:i:s", time());
        $sourceTag['id'] = Redis::incr('tag_id');
        $sourceTag['tag_value'] = Redis::incr('tag_value');
        $sourceTag['tag_name'] = $tagName;
        $sourceTag['createtime'] = $currenttime;
        $sourceTag['modifytime'] = $currenttime;
        try {
            DB::beginTransaction();
            DB::table('tag')->insert($sourceTag);
            DB::table('source')->where('id', $sid)->update(['tag_value'=>$sourceTag['tag_value']]);
            DB::commit();

            $return['status_code'] = '200';
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::INFO($e->getCode());
            \Log::INFO($e->getMessage());
            $return['status_code'] = '10010';
            $return['error_message'] = '新建数据失败';
        }
        return response()->json($return);
    }
}
