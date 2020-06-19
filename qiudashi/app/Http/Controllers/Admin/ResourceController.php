<?php

namespace App\Http\Controllers\Admin;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Respository\FaceUtility;
use App\Models\hl_resource_record;

class ResourceController extends Controller
{

    public function __construct(hl_resource_record $hl_resource_record) {
        $this->hl_resource_record=$hl_resource_record;
    }

    /*
     * 获取方案修改记录
     */
    public function getRecord(Request $request){
        $resource_id=$request->input('resource_id','');
        $data=$this->hl_resource_record->getRecord($resource_id);
        return $this->rtJson($data);
    }

    public function addRecord(Request $request){
        $param=$request->all();
        $this->hl_resource_record->addRecord($param);
        return $this->rtJson();
    }
}
