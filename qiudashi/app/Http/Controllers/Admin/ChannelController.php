<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Respository\ChannelRespository;
use App\Models\Order;
class ChannelController extends Controller
{

    protected $ChannelRespository;

    /*
     * 依赖注入
     */
    public function __construct(ChannelRespository $ChannelRespository)
    {
        $this->ChannelRespository = $ChannelRespository;
    }
    /*
     * 所有渠道接口
     */
    public function channelList(Request $request){
        $list=$this->ChannelRespository->channelList();
        return ['code'=>1,'msg'=>'SUCCESS','data'=>$list];
    }

    /*
     * 渠道日充值明细
     * page 页数
     * pagesize 条数
     * channel 渠道
     * platform 平台
     * times[开始时间，结束时间]
     */
    public function lists(Request $request)
    {

        $page=$request->page;
        $pageSize=$request->pagesize;
        $channel=$request->channel;
        $platform=$request->platform;
        $times=$request->times;
        $startTime=date("Y-m-d 00:00:00",bcdiv($times[0], 1000));
        $endTime=date("Y-m-d 23:59:59",bcdiv($times[1], 1000));
        $list=$this->ChannelRespository->lists($startTime,$endTime,$channel,$platform,$page,$pageSize);

        return ['code'=>1,'msg'=>'SUCCESS','data'=>['totalCount'=>$list['count'],'list'=>$list['list']]];
    }


    /*
     * 平台日充值总合
     * page 页数
     * pagesize 条数
     * platform 平台
     * times[开始时间，结束时间]
     */
    public function listsSum(Request $request)
    {

        $page=$request->page;
        $platform=$request->platform;
        $pageSize=$request->pagesize;
        $times=$request->times;
        $startTime=date("Y-m-d 00:00:00",bcdiv($times[0], 1000));
        $endTime=date("Y-m-d 23:59:59",bcdiv($times[1], 1000));
        //$list=$this->ChannelRespository->lists($startTime,$endTime,'',$platform,$page,$pageSize);
        $list=$this->ChannelRespository->listsSum($startTime,$endTime,'',$platform,$page,$pageSize);

        return ['code'=>1,'msg'=>'SUCCESS','data'=>['totalCount'=>$list['count'],'list'=>$list['list']]];
    }

    /*
     * 渠道日消费明细
     */
    public function consumeList(Request $request){
        $page=$request->page;
        $pageSize=$request->pagesize;
        $channel=$request->channel;
        $platform=$request->platform;
        $times=$request->times;
        $startTime=date("Y-m-d 00:00:00",bcdiv($times[0], 1000));
        $endTime=date("Y-m-d 59:59:59",bcdiv($times[1], 1000));
        $list=$this->ChannelRespository->lists($startTime,$endTime,$channel,$platform,$page,$pageSize,1);

        return ['code'=>1,'msg'=>'SUCCESS','data'=>['totalCount'=>$list['count'],'list'=>$list['list']]];
    }


    /*
    * 平台日消费总合
    * page 页数
    * pagesize 条数
    * platform 平台
    * times[开始时间，结束时间]
    */
    public function consumeSum(Request $request)
    {

        $page=$request->page;
        $platform=$request->platform;
        $pageSize=$request->pagesize;
        $times=$request->times;
        $startTime=date("Y-m-d 00:00:00",bcdiv($times[0], 1000));
        $endTime=date("Y-m-d 59:59:59",bcdiv($times[1], 1000));
        $list=$this->ChannelRespository->lists($startTime,$endTime,'',$platform,$page,$pageSize,1);

        return ['code'=>1,'msg'=>'SUCCESS','data'=>['totalCount'=>$list['count'],'list'=>$list['list']]];
    }


}
