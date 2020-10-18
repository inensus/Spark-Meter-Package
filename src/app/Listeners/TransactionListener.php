<?php


namespace Inensus\SparkMeter\App\Listeners;



use App\Models\Transaction\Transaction;
use Illuminate\Contracts\Events\Dispatcher;
use Inensus\SparkMeter\app\Services\TransactionService;
use Inensus\SparkMeter\Models\SmTransaction;

class TransactionListener
{
    private $transactionService;
    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService=$transactionService;
    }

    /**
     * Sets the in_use to true
     * @param Transaction $transaction
     */
    public function onTransactionSuccess(Transaction $transaction)
    {
      $smTransaction = SmTransaction::where('mpm_transaction_id',$transaction->id)->first();
      if ($smTransaction){
         $this->transactionService->updateTransactionStatus($smTransaction);
       }
    }
    public function subscribe(Dispatcher $events)
    {
        $events->listen('transaction.successful', 'Inensus\SparkMeter\App\Listeners\TransactionListener@onTransactionSuccess');
    }
}
