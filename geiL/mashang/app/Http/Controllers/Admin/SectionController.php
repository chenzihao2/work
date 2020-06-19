<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\models\section;
use App\models\source;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Ramsey\Uuid\Uuid;
use Tymon\JWTAuth\Facades\JWTAuth;

class SectionController extends BaseController
{
    /*
     * section后台管理接口
     * admin/section    GET         获取section栏目列表
     * admin/section    POST        新增一个section栏目
     * admin/section/:id    PUT     更新一个section栏目
     * admin/section/:id    DELETE  删除一个section栏目
     */
    /**
     * 获取section栏目列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSections(Request $request)
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

        // 进行查询
        $select = DB::table('section');

        if ( !empty( $query['name'] ) )
            $select->where('name', 'like', $query['name']);

        if ( !empty( $query['createtime']['from'] ) )
            $select->whereBetween('createtime', [$query['createtime']['from'], $query['createtime']['to']]);

        $count = $select->count();      // 总数

        $select->orderBy('rank', 'asc');
        if ( $offset != 0 )
            $select->offset($offset);
        $select->limit($numberpage);
        $data = $select->get();

        $pagenum = ceil($count/$numberpage);

        $return['status_code'] = '200';
        $return['count'] = $count;
        $return['pagenum'] = $pagenum;
        $return['data'] = $data->ToArray();

        return response()->json($return);

    }

    /**
     * 运营添加一条栏目信息
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function postSectionInfo(Request $request)
    {
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);
        //用户token和权限的验证
        $this->checkToken($clients);

        $name = $request->input('name', '');
        $rank = $request->input('rank', '');

        if(empty($name)){
            $return['status_code'] = '10003';
            $return['error_message'] = '栏目名称不可为空';
            return response()->json($return);
        }

        $only = DB::table('section')->where('name', $name)->first();
        if ( $only ) {
            $return['status_code'] = '10004';
            $return['error_message'] = '栏目名称已存在';
            return response()->json($return);
        }


        $currenttime = date("Y-m-d H:i:s", time());
        $section['id'] = Redis::incr('section_id');
        $section['name'] = $name;
        $section['rank'] = $rank;
        $section['createtime'] = $currenttime;
        $section['modifytime'] = $currenttime;

        $res = DB::table('section')->insert($section);
        if($res){
            $return['status_code'] = '200';
            $return['data'] = $section;
        }else{
            $return['status_code'] = '10010';
            $return['error_message'] = '新建数据失败';
        }
        return response()->json($return);
    }

    /**
     * 管理人员更新section数据
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function putSection(Request $request, $id)
    {
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);
        //用户token和权限的验证
        $this->checkToken($clients);

        $name = $request->input('name', '');
        $rank = $request->input('rank', '');

        $curr_section = DB::table('section')->where('id', $id)->first();
        if($curr_section->rank == '1'){
            $return['status_code'] = '10005';
            $return['error_message'] = '系统默认配置，不可更改';
            return response()->json($return);
        }

        if($name && $name != ""){
            $section['name'] = $name;
        }
        if($rank){
            $section['rank'] = $rank;
        }
        $section['modifytime'] = date("Y-m-d H:i:s", time());
        $res = DB::table('section')->where('id', $id)->update($section);
        if($res){
            $return['status_code'] = '200';
        }else{
            $return['status_code'] = '10010';
            $return['error_message'] = '更新数据失败';
        }
        return response()->json($return);
    }

    /**
     * 删除栏目信息
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteSection(Request $request, $id)
    {
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);
        //用户token和权限的验证
        $this->checkToken($clients);

        $section = DB::table('section')->where('id', $id)->first();
        if( !$section ) {
            $return['status_code'] = '10006';
            $return['error_message'] = '您要删除的栏目不存在';
            return response()->json($return);
        }

        if( $section->rank == '1') {
            $return['status_code'] = '10007';
            $return['error_message'] = '您不可删除系统栏目数据';
            return response()->json($return);
        }

        try {
            DB::beginTransaction();
            Redis::del(config('constants.SECTION_KEY').$id);
            DB::table('source')->where('section_id',$id)->update(['section_id' => 0]);
            DB::table('section')->where('id', $id)->delete();
            DB::commit();

            $return['status_code'] = '200';
            return response()->json($return);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::INFO($e->getCode());
            \Log::INFO($e->getMessage());
            $return['status_code'] = '10010';
            $return['error_message'] = '删除数据失败';
            return response()->json($return);
        }
    }


    /**
     * 获取某个栏目对应的推荐料列表
     * @param Request $request
     * @param $secid
     * @return \Illuminate\Http\JsonResponse
     */
    public function recommendSources(Request $request,$secid){
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);
        //用户token和权限的验证
        $this->checkToken($clients);

        $page = $request->input('page', '0');
        $numberpage = $request->input('numberpage', '50');
        $offset = $page * $numberpage;

        $count = Redis::zCard(config('constants.SECTION_KEY').$secid);
        $pagenum = ceil($count/$numberpage);

        $list = Redis::zRevRange(config('constants.SECTION_KEY').$secid, $offset, $numberpage);
        \Log::INFO($list);
        $dataList = [];
        foreach ($list as $source){
            $data = DB::table('source')->select('id','uid','title','price','thresh','status','createtime','modifytime')
                ->where('id',$source)->first();
            $data = (array)$data;
            if(count($data)>0){
                $status = decbin($data['status']);
                $oldStatus = sprintf('%08d', $status);
                $data['status'] = $oldStatus;
                $soldnumber = DB::table('source_extra')->select('soldnumber')->where('id',$source)->first();
                if($soldnumber){
                    $data['soldnumber'] = $soldnumber->soldnumber;
                }
                array_push($dataList,$data);
            }
        }
        $return['status_code'] = '200';
        $return['count'] = $count;
        $return['pagenum'] = $pagenum;
        $return['data'] = $dataList;
        return response()->json($return);
    }
}
