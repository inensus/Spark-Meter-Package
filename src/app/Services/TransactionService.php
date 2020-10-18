<?php


namespace Inensus\SparkMeter\app\Services;


use Exception;
use Illuminate\Support\Facades\Log;
use Inensus\SparkMeter\app\Http\Requests\SparkMeterApiRequests;
use Inensus\SparkMeter\Models\SmCustomer;
use Inensus\SparkMeter\Models\SmTransaction;

class TransactionService
{
    private $sparkMeterApiRequests;
    private $rootUrl = '/transaction/';

    public function __construct(SparkMeterApiRequests $sparkMeterApiRequests)
    {
        $this->sparkMeterApiRequests = $sparkMeterApiRequests;
    }


    public function updateTransactionStatus(SmTransaction $smTransaction)
    {
        try {
            $smTransactionResult = $this->sparkMeterApiRequests->getInfo($this->rootUrl, $smTransaction->transaction_id);
            $status = $smTransactionResult['transaction']['status'];
        } catch (Exception $e) {
            $status =$e->getMessage();
            Log::critical('Updating SmTransaction status information failed.', ['Error :' => $e->getMessage()]);
        }
        $smTransaction->update([
            'status'=>$status
        ]);
    }

}
