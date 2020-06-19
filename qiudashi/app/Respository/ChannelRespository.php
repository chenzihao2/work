<?php

namespace App\Respository;


use App\Http\Requests\requiredValidator;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use App\Respository\FaceUtility;
use App\Models\User;
use App\Models\hl_user;
use App\Models\hl_login_channel;
use App\Models\Order;
use App\Models\UsersChannel;

class ChannelRespository
{

    protected $model;
    protected $user;
    protected $channel;
    protected $date=[0,3,7,15,30,60,90,120,240,365];
    /*
     * 依赖注入
     */
    public function __construct(Order $order,hl_user $user,hl_login_channel $channel)
    {
        $this->model = $order;
        $this->user = $user;
        $this->channel = $channel;
    }

    /*
     * 渠道列表
     */
    public function channelList(){
        $where[]=['channel','!=',''];
        $where[]=['channel','!=','0'];
        $where2[]=['platform','!=',''];
        $where2[]=['platform','!=','0'];
        $channel=$this->channel->where($where)->groupBy('channel')->select('channel')->get();

        $platform=$this->channel->where($where2)->groupBy('platform')->select('platform')->get();

        return ['channel'=>$channel,'platform'=>$platform];
    }

    /*
     * 渠道 日充值统计
     * $startTime 开始时间
     * $endTime 结束时间
     * $channel 渠道
     * $platform 平台
     * $order_type 100 充值，1 消费
     */
    public function lists($startTime,$endTime,$channel='',$platform='',$page=1,$pageSize=20,$order_type=100){
        $channel=$channel=='all'?'':$channel;
        /*if($channel=='other'){
            $where[]=['channel','=',''];//其他

        }else if($channel){

            $where[]=['channel','=',$channel];//指定渠道
        }
        $platform=$platform=='all'?'':$platform;
        if($platform=='other'){
             $where[]=['platform','=',''];
        }else if($platform){
            $where[]=['platform','=',$platform];
        }

        $where[]=['created_at','>=',$startTime];
        $where[]=['created_at','<=',$endTime];

        */
        //$where[]=['is_one','=',1];



        $condtion=' 1=1 ';
        if($channel=='other'){
            $where[]=['channel','=',''];//其他
            $condtion.=" and channel= '' ";
        }else if($channel){
            $where[]=['channel','=',$channel];//指定渠道
            $condtion.=" and channel = '".$channel."' ";
        }
        $platform=$platform=='all' ? '':$platform;
        if($platform=='other'){
            $where[]=['platform','=',''];
            $condtion.=" and platform = '' ";
        }else if($platform){
            $where[]=['platform','=',$platform];
            $condtion.=" and platform = '".$platform."' ";
        }

        $where[]=['created_at','>=',$startTime];
        $where[]=['created_at','<=',$endTime];

        $condtion.=" and created_at >= '".$startTime."' ";
        $condtion.=" and created_at <= '".$endTime."' ";
        //$where[]=['is_one','=',1];

        //$sql="select count(*) as counts from (select * from hl_login_channel group by DATE_FORMAT(created_at,'%Y-%m-%d'),platform,channel) temps where $condtion";
        $sql="select count(*) as counts from (select * from haoliao.hl_login_channel group by DATE_FORMAT(created_at,'%Y-%m-%d'),platform,channel) temps where $condtion";

        $countRes=DB::select($sql);
        $count=$countRes[0]->counts;
        $pageSize=$pageSize?$pageSize:20;
        $page=$page?$page:1;
        //$totalPage = ceil($count/$pageSize); //总页数
        $startPage=($page-1)*$pageSize;//开始记录


        $orderList=$this->channel
            ->where($where)
            ->groupBy(DB::raw("DATE_FORMAT(created_at,'%Y-%m-%d')"),'platform','channel')
            ->select(DB::raw("DATE_FORMAT(created_at,'%Y-%m-%d') as created_at"),'platform','channel',DB::raw('count(*) as total'))
            ->orderBy('created_at','asc')->offset($startPage)->limit($pageSize)->get()->toArray();

        foreach($orderList as $k=>&$v){
            $times=date("Y-m-d 23:59:59",strtotime($v['created_at']));
            //根据时间统计
            foreach($this->date as $day){
                $day2=$day;
                if($day>0){
                    $day=$day-1;
                }
                $endTime=date("Y-m-d 23:59:59",strtotime($v['created_at'].' +'.$day.' day'));

                $key='_'.$day2.'Day';
                $v[$key]=$this->union($v['created_at'],$times,$v['created_at'],$endTime,$v['channel'],$v['platform'],$order_type);

            }

            //新增注册人数
            $v['regCount']=$this->registerNum($v['created_at'],$times,$v['channel'],$v['platform']);
            //新增设备数
            $v['devCount']=$this->newChannelCount($v['created_at'],$times,$v['channel'],$v['platform']);

            //新增充值用户数
            $v['czCount']=$this->thisDayTotal($v['created_at'],$times,$v['channel'],$v['platform']);

            $v['created_at']=date("Y-m-d",strtotime($v['created_at']));
        }

        return ['count'=>$count,'list'=>$orderList];

    }

    /*
     * 渠道 日总和
     * $startTime 开始时间
     * $endTime 结束时间
     * $channel 渠道
     * $platform 平台
     * $order_type 100 充值，1 消费
     */
    public function listsSum($startTime,$endTime,$channel='',$platform='',$page=1,$pageSize=20,$order_type=100){
        $condtion=' 1=1 ';
        if($channel=='other'){
            $where[]=['channel','=',''];//其他
            $condtion.=" and channel= '' ";
        }else if($channel){
            $where[]=['channel','=',$channel];//指定渠道
            $condtion.=" and channel = '".$channel."' ";
        }
        $platform=$platform=='all' ? '':$platform;
        if($platform=='other'){
            $where[]=['platform','=',''];
            $condtion.=" and platform = '' ";
        }else if($platform){
            $where[]=['platform','=',$platform];
            $condtion.=" and platform = '".$platform."' ";
        }

        $where[]=['created_at','>=',$startTime];
        $where[]=['created_at','<=',$endTime];

        $condtion.=" and created_at >= '".$startTime."' ";
        $condtion.=" and created_at <= '".$endTime."' ";
        //$where[]=['is_one','=',1];


        $sql="select count(*) as counts from (select * from haoliao.hl_login_channel group by DATE_FORMAT(created_at,'%Y-%m-%d'),platform) temps where $condtion";

        $countRes=DB::select($sql);


        $count=$countRes[0]->counts;

        $pageSize=$pageSize?$pageSize:20;
        $page=$page?$page:1;
        //$totalPage = ceil($count/$pageSize); //总页数
        $startPage=($page-1)*$pageSize;//开始记录

        $orderList=$this->channel
            ->where($where)
            ->select(DB::raw("DATE_FORMAT(created_at,'%Y-%m-%d') as created_at"),'platform','channel',DB::raw('count(*) as total'))
            ->groupBy(DB::raw("DATE_FORMAT(created_at,'%Y-%m-%d')"),'platform')
            ->orderBy('created_at','asc')
            ->offset($startPage)
            ->limit($pageSize)
            ->get()->toArray();


        foreach($orderList as $k=>&$v){
            $v['platform']=$v['platform']?$v['platform']:'';
            $times=date("Y-m-d 23:59:59",strtotime($v['created_at']));
            //根据时间统计
            foreach($this->date as $day){
                $day2=$day;
                if($day>0){
                    $day=$day-1;
                }
                $endTime=date("Y-m-d 23:59:59",strtotime($v['created_at'].' +'.$day.' day'));

                $key='_'.$day2.'Day';
                $v[$key]=$this->union($v['created_at'],$times,$v['created_at'],$endTime,'',$v['platform'],$order_type);

            }

            //新增注册人数
            $v['regCount']=$this->registerNum($v['created_at'],$times,'',$v['platform']);
            //新增设备数
            $v['devCount']=$this->newChannelCount($v['created_at'],$times,'',$v['platform']);

            //新增充值用户数
            $v['czCount']=$this->thisDayTotal($v['created_at'],$times,'',$v['platform']);

            $v['created_at']=date("Y-m-d",strtotime($v['created_at']));
        }

        return ['count'=>$count,'list'=>$orderList];

    }

    /*
     * 统计新增注册数
     * $startTime 开始时间
     * $endTime 结束时间
     * $channel 渠道
     * $platform 平台
     */

    public function registerNum($startTime,$endTime,$channel='',$platform=''){
//        $where[]=['hl_user.created_at','>=',$startTime];
//        $where[]=['hl_user.created_at','<=',$endTime];
        $where[]=['hl_user.create_time','>=',strtotime($startTime)];
        $where[]=['hl_user.create_time','<=',strtotime($endTime)];

        if($channel){
            $where[]=['hl_login_channel.channel','=',$channel];
        }
        //if($platform){
        $where[]=['hl_login_channel.platform','=',$platform];
        //}

        // dump($where);
//DB::connection()->enableQueryLog();
        $res=$this->user->leftJoin('hl_login_channel','hl_login_channel.cid','hl_user.cid')->where($where)->count();
        //dump(DB::getQueryLog());die;

        return $res;

    }

    /*
     * 统计新增设备数
     * $startTime 开始时间
     * $endTime 结束时间
     * $channel 渠道
     * $platform 平台
     */
    public function newChannelCount($startTime,$endTime,$channel='',$platform=''){

        $where[]=['created_at','>=',$startTime];
        $where[]=['created_at','<=',$endTime];
        if($channel){
            $where[]=['channel','=',$channel];
        }

        //$where[]=['is_one','=',1];
        if($platform){
            $where[]=['platform','=',$platform];
        }
        //DB::connection()->enableQueryLog();  // 开启QueryLog
        $res=$this->channel->where($where)->groupBy('device')->get();

        //dump(DB::getQueryLog());
        return count($res);
    }

    /*
     * 新增充值/消费用户数
     * $startTime 开始时间
     * $endTime 结束时间
     * $channel 渠道
     * $platform 平台
     */
    public function thisDayTotal($startTime,$endTime,$channel='',$platform='',$order_type=100){
//        $where[]=['hl_user.created_at','>=',$startTime];
//        $where[]=['hl_user.created_at','<=',$endTime];
        $where[]=['hl_user.create_time','>=',strtotime($startTime)];
        $where[]=['hl_user.create_time','<=',strtotime($endTime)];
        if($channel){
            $where[]=['hl_login_channel.channel','=',$channel];
        }

        if($platform){
            $where[]=['hl_login_channel.platform','=',$platform];
        }
        if($order_type==1){
            $orderWhere[]=['order_type','<',100];
        }else{
            $orderWhere[]=['order_type','=',100];
        }
        $orderWhere[]=['order_status','=',1];
        $user_id=$this->user
            ->leftJoin('hl_login_channel','hl_login_channel.user_id', 'hl_user.user_id')->where($where)
            ->groupBy('hl_user.user_id')
            ->pluck('hl_user.user_id')->toArray();
        //DB::connection()->enableQueryLog();  // 开启QueryLog
        $res = $this->model->where($orderWhere)->whereIn('user_id',$user_id)->groupBy('user_id')->select('user_id')->get();
        //dump(DB::getQueryLog());
        $count=count($res);


        return $count;

    }


    /*
     * 统计不同天数得充值/消费金额
	 * $regSstartTime 注册时间范围开始
	 * $resEndTime 注册时间范围结束
     * $startTime 开始时间
     * $endTime 结束时间
     * $channel 渠道
     * $platform 平台
     */
    public function union($regSstartTime,$resEndTime,$startTime,$endTime,$channel,$platform='',$order_type=100){
        // $where[]=['hl_user.created_at','>=',$regSstartTime];
        // $where[]=['hl_user.created_at','<=',$resEndTime];
        $where[]=['hl_user.create_time','>=',strtotime($regSstartTime)];
        $where[]=['hl_user.create_time','<=',strtotime($resEndTime)];
        if($channel){
            $where[]=['hl_login_channel.channel','=',$channel];
        }
        if($platform){
            $where[]=['hl_login_channel.platform','=',$platform];
        }

        $user_id=$this->user
            ->leftJoin('hl_login_channel', 'hl_login_channel.cid', 'hl_user.cid')->where($where)
            ->pluck('hl_user.user_id')->toArray();

        //消费
        if($order_type==1){
            $orderWhere[]=['order_type','<',100];
        }else{
            $orderWhere[]=['order_type','=',100];
        }

        $orderWhere[]=['order_status','=',1];
        $orderWhere[]=['buy_time','>=',strtotime($startTime)];
        $orderWhere[]=['buy_time','<=',strtotime($endTime)];
        // $user_id= $this->channel->where($where)->pluck('user_id');



        $pay_amount = $this->model->where($orderWhere)->whereIn('user_id',$user_id)->sum('pay_amount');
        return (new FaceUtility())->ncPriceFen2Yuan($pay_amount);

    }



}