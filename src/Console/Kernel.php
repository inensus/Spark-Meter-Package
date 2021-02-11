<?php

namespace Inensus\SparkMeter\Console;

use App\Console\Kernel as ConsoleKernel;
use Illuminate\Console\Scheduling\Schedule;

class Kernel extends ConsoleKernel
{

    protected function schedule(Schedule $schedule)
    {
        parent::schedule($schedule);
        $schedule->command('spark-meter:transactionStatusCheck')->withoutOverlapping(50)
            ->appendOutputTo(storage_path('logs/cron.log'))->everyTenMinutes();
        $schedule->command('spark-meter:updatesGetter')->everyFiveMinutes()
            ->appendOutputTo(storage_path('logs/cron.log'));
   /*     $schedule->command('spark-meter:lowBalanceLimitNotifier')->hourly()
            ->appendOutputTo(storage_path('logs/cron.log'));*/
        $schedule->command('spark-meter:transactionSync')->withoutOverlapping(50)->everyTenMinutes()
            ->appendOutputTo(storage_path('logs/cron.log'));
        //
    }
}
