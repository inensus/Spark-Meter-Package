<?php


namespace Inensus\SparkMeter\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Inensus\SparkMeter\Exceptions\CronJobException;
use Inensus\SparkMeter\Services\TransactionService;

class SparkMeterTransactionSync extends Command
{
    protected $signature = 'spark-meter:transactionSync';
    protected $description = 'Synchronise transactions from Spark Meter.';

    private $sparkTransactionsService;

    public function __construct(TransactionService $sparkTransactionsService)
    {
        parent::__construct();
        $this->sparkTransactionsService = $sparkTransactionsService;
    }

    public function handle(): void
    {

        $timeStart = microtime(true);
        $this->info('#############################');
        $startedAt=Carbon::now()->toIso8601ZuluString();
        $this->info('transactionSync command started at '.$startedAt);

        try {
             $this->sparkTransactionsService->sync();
             $this->info('transactionSync command is finished' );
        } catch (CronJobException $e) {
            $this->warn('transactionSync command is failed. message => ' . $e->getMessage());
        }
        $timeEnd = microtime(true);
        $totalTime=$timeEnd - $timeStart;
        $this->info("Took ".$totalTime." seconds.");
        $this->info('#############################');
    }
}