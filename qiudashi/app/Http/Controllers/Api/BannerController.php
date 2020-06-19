<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\hl_banner;
use App\Models\CheckConfig;
class BannerController extends Controller
{
    //protected $pagesize = 20;
    protected $version ='';
    protected $channel ='';
    protected $show_comment_model =1;//评论模块
    public function __construct(hl_banner $hl_banner,CheckConfig $CheckConfig,Request $request) {
        $this->hl_banner = $hl_banner;
        $this->CheckConfig = $CheckConfig;
        $this->channel=$request->input('channel', '');
        $version = $request->input('version', '');
        $this->version =  (int)str_replace('.', '', $version);
        if($version && $this->channel){
            $congInfo=$this->CheckConfig->show($this->channel,$this->version);
            $this->show_comment_model=$congInfo['show_comment_model'];
        }
    }

    //轮播图列表
    public function lists(Request $request) {
        $platform = $request->input('platform', 1);
        $source = $request->input('source', 0);
        $where['platform']=$platform;
        $where['source']=$source;
        $list = $this->hl_banner->getBannerList($where,$this->show_comment_model);
        return $this->rtJson_($list);
    }




}
