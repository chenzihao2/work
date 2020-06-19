<?php

namespace App\Console\Commands;

use App\models\client;
use App\models\client_extra;
use App\models\client_withdraw;
use App\models\order;
use App\models\resource;
use App\models\source;
use App\models\source_extra;
use App\models\statics as tics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Statics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'statics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '统计每日的数据';

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
        $date = date('Y-m-d', strtotime('-1 day'));
        $statics['order'] = order::where('orderstatus', 1)->where('createtime', 'like', $date.'%')->count();
        $statics['resource'] = resource::where('createtime', 'like', $date.'%')->count();
        $statics['source'] = source::where('createtime', 'like', $date.'%')->count();
        $statics['user'] = client::where('createtime', 'like', $date.'%')->count();
        $statics['service_fee'] = client_withdraw::where('completetime', 'like', $date.'%')->where('status', 4)->sum('service_fee');
        $statics['withdrawed'] = client_withdraw::where('completetime', 'like', $date.'%')->where('status', 4)->sum('balance');
        $statics['withdrawing'] = client_withdraw::where('completetime', 'like', $date.'%')->sum('balance');
        $statics['total'] = order::where('orderstatus', 1)->where('createtime', 'like', $date.'%')->sum('price');
        $statics['statictime'] = date('Y-m-d H:i:s', time());
        $statics['active'] = client_extra::where('lastlogin', 'like', $date.'%')->count();
        $sell = DB::select("select count(*) as num from client_extra where substring(bin(role), -1, 1) = 1 and lastlogin like '{$date}%'");
        $buy = DB::select("select count(*) as num from client_extra where substring(bin(role), -2, 1) = 1 and lastlogin like '{$date}%'");
        $statics['active_sell'] = $sell[0]->num;
        $statics['active_buy'] = $buy[0]->num;
        tics::create($statics);
    }
}
