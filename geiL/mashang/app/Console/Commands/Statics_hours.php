<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\models\client;
use App\models\client_extra;
use App\models\client_withdraw;
use App\models\order;
use App\models\resource;
use App\models\source;
use App\models\source_extra;
use App\models\statics_hours as Shour;
use Illuminate\Support\Facades\DB;

class Statics_hours extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'statics_hours';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '统计半个小时的数据';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // 当日完成的订单数量， 资源数量， 料， 用户， 获得的服务费， 完成的提现金额， 当日提出的提现金额， 当日完成的订单总额
        $date = date('Y-m-d H:i:s', strtotime('-30 minute'));
        $current = date('Y-m-d H:i:s', time());
        $statics['order'] = order::where('orderstatus', 1)->whereBetween('createtime', [$date, $current])->count();
        $statics['resource'] = resource::whereBetween('createtime', [$date, $current])->count();
        $statics['source'] = source::whereBetween('createtime', [$date, $current])->count();
        $statics['user'] = client::whereBetween('createtime', [$date, $current])->count();
        $statics['service_fee'] = client_withdraw::whereBetween('completetime', [$date, $current])->where('status', 4)->sum('service_fee');
        $statics['withdrawed'] = client_withdraw::whereBetween('completetime', [$date, $current])->where('status', 4)->sum('balance');
        $statics['withdrawing'] = client_withdraw::whereBetween('completetime', [$date, $current])->sum('balance');
        $statics['total'] = order::where('orderstatus', 1)->whereBetween('createtime', [$date, $current])->sum('price');
        $statics['statictime'] = $current;
        $statics['active'] = client_extra::whereBetween('lastlogin', [$date, $current])->count();
        $sell = DB::select("select count(*) as num from client_extra where substring(bin(role), -1, 1) = 1 and lastlogin between '{$date}' and '{$current}'");
        $buy = DB::select("select count(*) as num from client_extra where substring(bin(role), -2, 1) = 1 and lastlogin between '{$date}' and '{$current}'");
        $statics['active_sell'] = $sell[0]->num;
        $statics['active_buy'] = $buy[0]->num;
        Shour::create($statics);
    }
}
