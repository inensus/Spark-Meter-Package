<?php


namespace Inensus\SparkMeter\Console\Commands;

use Illuminate\Console\Command;
use Inensus\SparkMeter\Services\TransactionService;
use Inensus\SparkMeter\Models\SmTransaction;

class SparkMeterTransactionStatusCheck extends Command
{
    protected $signature = 'spark-meter:transaction-check';
    protected $description = 'Checks status of Spark Meter transactions';

    private $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        parent::__construct();
        $this->transactionService = $transactionService;
    }

    public function handle(): void
    {
        $smTransactions =  SmTransaction::where('status','created')->orWhere('status','not-processed')->get();
        foreach ($smTransactions as $key => $smTransaction) {
            $this->transactionService->updateTransactionStatus($smTransaction);
        }
    }
}
