<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class ComplaintsController extends BaseController
{
    /**
     * 用户投诉列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getComplaints(Request $request)
    {
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);
        //用户token和权限的验证
        $this->checkToken($clients);

        $page = $request->input('page', '0');
        $numberpage = $request->input('pagesize', '50');
        $query = $request->input('query', '');
        $sort = $request->input('sort', '');
        $query = json_decode($query, True);
        $sort = json_decode($sort, True);
        $offset = $page * $numberpage;

        $complaints = DB::table('complaints')->select('complaints.*','client.nickname','source.title')
            ->leftJoin('client', 'client.id', '=', 'complaints.uid')
            ->leftJoin('source','source.sid','=', 'complaints.sid');

        if ( !empty( $query['sid'] ) )
            $complaints->where('complaints.sid', '=', $query['sid']);

        if ( !empty( $query['uid'] ) )
            $complaints->where('complaints.uid', '=', $query['sid']);

        if ( !empty( $query['suid'] ) )
            $complaints->where('complaints.suid', '=', $query['suid']);

        if ( !empty( $query['type'] ) )
            $complaints->where('complaints.type', '=', $query['type']);

        if ( !empty( $query['status'] ) )
            $complaints->where('complaints.status', '=', $query['status']);

        if ( !empty( $query['createtime']['from'] ) )
            $complaints->whereBetween('complaints.createtime', [$query['createtime']['from'], $query['createtime']['to']]);
        //总数量

        $count = $complaints->count();

        if ( $offset != 0 )
            $complaints->offset($offset);
        $complaints->limit($numberpage);
        $complaints->orderBy('complaints.createtime', 'desc');

        $data = $complaints->get()->ToArray();
        $typeText = array(
          '1' => '欺诈（虚假资源售卖）',
          '2' => '色情',
          '3' => '政治谣言',
          '4' => '诱导分享',
          '5' => '恶意营销',
          '6' => '骚扰',
          '7' => '其他'
        );
	      foreach($data as $index=>$datavalue){
          $datavalue = (array)$datavalue;
          if(isset($datavalue['type']) && !empty($datavalue['type'])) {
            $com_type = explode(',', $datavalue['type']);
            $datavalue['type_text'] = $typeText[$com_type[0]];
          } else{
            $datavalue['type_text'] = '';
          }
          $data[$index] = $datavalue;
	      }

        $pagenum = ceil($count/$numberpage);
        $return['status_code'] = '200';
        $return['data'] = array(
          'total' => $count,
          'pagesize' => $numberpage,
          'pagenum' => $pagenum,
          'list' => $data
        );

        return response()->json($return);
    }

    public function updateStatus(Request $request,$id)
    {
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);
        //用户token和权限的验证
        $this->checkToken($clients);

        $complaint = DB::table('complaints')->where('id',$id)->first();
        if($complaint->status == 0){
            $update['status'] = 1;
        }
        if($complaint->status == 1){
            $update['status'] = 2;
        }
        $update['modifytime'] = date('Y-m-d H:i:s', time());
        $res = DB::table('complaints')->where('id',$id)->update($update);
        if(!$res){
            $return['status_code'] = '10003';
            $return['error_message'] = '更新状态失败';
        }
        $return['status_code'] = 200;
        return response()->json($return);
    }
}
