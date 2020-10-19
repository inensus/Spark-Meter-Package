<?php


namespace Inensus\SparkMeter\Models;


use App\Models\Transaction\Transaction;

class SmTransaction extends BaseModel
{
    protected $table = 'sm_transactions';

    public function mpmTransaction()
    {
        return $this->belongsTo(Transaction::class,'mpm_transaction_id');
     }
}
