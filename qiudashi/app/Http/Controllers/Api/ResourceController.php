<?php

namespace App\Http\Controllers\Api;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Respository\FaceUtility;
use App\Models\hl_resource;
class ResourceController extends Controller
{

    public function __construct(hl_resource $hl_resource) {
        $this->hl_resource=$hl_resource;
    }
    /*
     * 浏览记录
     */
    public function viewRecord(Request $request){
        $resource_id=$request->input('resource_id','');
        $device=$request->input('device','');
        $user_id= $request->user_info['user_id'];
        $this->hl_resource->viewRecord($device,$resource_id,$user_id);
        return $this->rtJson();
    }
}
