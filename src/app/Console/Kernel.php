<?php

namespace Inensus\SparkMeter\app\Console;

use App\Console\Kernel as ConsoleKernel;
use Illuminate\Console\Scheduling\Schedule;

class Kernel extends ConsoleKernel
{


    protected function schedule(Schedule $schedule)
    {
        $schedule->command('spark-meter:transaction-check')->everyMinute();
        parent::schedule($schedule);

        //
    }
}
