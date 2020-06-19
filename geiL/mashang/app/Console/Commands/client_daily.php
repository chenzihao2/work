<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class client_daily extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'client_daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'client_daily';

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
      $day = date('Y-m-d', strtotime('-1 day'));
	    $starttime = date('Y-m-d H:i:s',strtotime($day));
      $endtime = date('Y-m-d H:i:s',strtotime($day)+86400);
      $income = DB::table('order')->select(DB::raw('sum(price) as client_income,selledid as uid'))
        ->where('createtime','>=',$starttime)->where('createtime','<',$endtime)->whereRaw('orderstatus&1')->groupBy('selledid')->get();
      foreach($income as $client_daily){
        $client_daily = (array)$client_daily;
        if($client_daily['client_income']>=25000){
          $rate = 1.5;
        }else if($client_daily['client_income']>=15000){
          $rate = 2;
        }else if($client_daily['client_income']>=10000){
          $rate = 2.5;
        }else if($client_daily['client_income']>=5000){
          $rate = 3;
        }else if($client_daily['client_income']>=3000){
          $rate = 3.5;
        }else if($client_daily['client_income']>=2000){
          $rate = 4;
        }else{
          $client_rate = DB::table('client_rate')->where('uid',$client_daily['uid'])->first();
          $rate = $client_rate->rate;
        }
        $service_fee = $client_daily['client_income']*$rate/100;
        $data['day'] = date('Ymd',strtotime($day));
        $data['uid'] = $client_daily['uid'];
        $data['rate'] = $rate;
        $data['income'] = $client_daily['client_income'];
        $data['service_fee'] = $service_fee;
        $data['profit'] = $client_daily['client_income']-$service_fee;
        DB::table('client_daily')->insert($data);
      }
    }
}
