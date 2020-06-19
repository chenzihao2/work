<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
        //Commands\Statics::class,
        //Commands\Statics_hours::class
        //\App\Console\Commands\client_daily::class,
	      \App\Console\Commands\refund::class,
	      //\App\Console\Commands\refund2::class,
	      \App\Console\Commands\refund_manual::class,
	      \App\Console\Commands\refund_check::class,
        \App\Console\Commands\discount_rate::class,
        \App\Console\Commands\financial_data_count::class,
        \App\Console\Commands\sendnotice::class,
        \App\Console\Commands\sendSourceUpdateNotice::class,
        \App\Console\Commands\setBuyer::class,
        \App\Console\Commands\sendRecallNotice::class,
        \App\Console\Commands\statSellerData::class,
        \App\Console\Commands\sendSellerDataNotice::class,
        \App\Console\Commands\staticMove::class,
        \App\Console\Commands\updateRecommendList::class,
        \App\Console\Commands\delRecommend::class,
        \App\Console\Commands\sendAdminNotice::class,
		    \App\Console\Commands\subscribe::class,
		    \App\Console\Commands\sensitive_words::class,
		    \App\Console\Commands\scan_records::class,
		    \App\Console\Commands\sendnotice_patch::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')
        //          ->hourly();
        //$schedule->command('Statics_hours')->everyMinute();
        //$schedule->command('Statics')->daily();
//        $schedule->command('client_daily');

        $schedule->command('refund')->everyMinute()->sendOutputTo('/data/wwwroot/yingxun/api/mashang/test1.log');
        $schedule->command('refund_check')->everyMinute()->sendOutputTo('/data/wwwroot/yingxun/api/mashang/test2.log');
        $schedule->command('discount_rate')->everyMinute()->sendOutputTo('/data/wwwroot/yingxun/api/mashang/discount_rate.log');
        $schedule->command('financial_data_count')->everyMinute()->sendOutputTo('/data/wwwroot/yingxun/api/mashang/financial_data_count.log');
        $schedule->command('sendnotice')->everyMinute()->sendOutputTo('/data/wwwroot/yingxun/api/mashang/test1.log');
        $schedule->command('sendSourceUpdateNotice')->everyMinute()->sendOutputTo('/data/wwwroot/yingxun/api/mashang/sendSourceUpdateNotice.log');
        $schedule->command('setBuyer')->everyMinute()->sendOutputTo('/data/wwwroot/yingxun/api/mashang/setBuyer.log');
        $schedule->command('sendRecallNotice')->everyMinute()->sendOutputTo('/data/wwwroot/yingxun/api/mashang/sendRecallNotice.log');
        $schedule->command('statSellerData')->everyMinute()->sendOutputTo('/data/wwwroot/yingxun/api/mashang/statSellerData.log');
        $schedule->command('sendSellerDataNotice')->everyMinute()->sendOutputTo('/data/wwwroot/yingxun/api/mashang/sendSellerDataNotice.log');
        $schedule->command('staticMove')->everyMinute()->sendOutputTo('/data/wwwroot/yingxun/api/mashang/staticMove.log');
        //$schedule->command('refund2')->everyMinute()->sendOutputTo('/data/wwwroot/yingxun/api/mashang/refund2.log');
        $schedule->command('subscribe')->everyMinute()->sendOutputTo('/data/wwwroot/yingxun/api/mashang/subscribe.log');

    }

    /**
     * Register the Closure based commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        require base_path('routes/console.php');
    }
}
