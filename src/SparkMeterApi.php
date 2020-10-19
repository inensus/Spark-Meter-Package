<?php


namespace Inensus\SparkMeter;


use App\Lib\IManufacturerAPI;

use App\Models\Meter\Meter;
use App\Models\Meter\MeterParameter;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Inensus\SparkMeter\Exceptions\SparkAPIResponseException;
use Inensus\SparkMeter\Services\TransactionService;
use Inensus\SparkMeter\Models\SmCredential;
use Inensus\SparkMeter\Models\SmCustomer;
use Inensus\SparkMeter\Models\SmTransaction;

class SparkMeterApi implements IManufacturerAPI
{
    /**
     * @var Client
     */
    protected $api;

    public function __construct(Client $httpClient)
    {
        $this->api = $httpClient;
    }

    public function chargeMeter($transactionContainer): array
    {

        $amount = $transactionContainer->transaction->amount;
        $externalId = $transactionContainer->transaction->id;
        $meterParameter = MeterParameter::with('owner')->where('id',
            $transactionContainer->meterParameter->id)->firstOrFail();
        $customerId = SmCustomer::where('mpm_customer_id',
            $meterParameter->owner->id)->first();
        $postParams = [
            'customer_id' => $customerId->customer_id,
            'amount' => strval($amount),
            'source' => 'cash',
            'external_id' => strval($externalId),

        ];
        $smCredential = SmCredential::query()->first();
        $url = $smCredential->api_url . '/transaction/';
        try {
            $request = $this->api->post(
                $url,
                [
                    'body' => json_encode($postParams),
                    'headers' => [
                        'Content-Type' => 'application/json;charset=utf-8',
                        'Authentication-Token' => $smCredential->authentication_token
                    ],
                ]
            );
            $transactionResult = json_decode((string)$request->getBody(), true);
        } catch (SparkAPIResponseException $e) {
            Log::critical('Spark API Transaction Failed',
                ['URL :' => $url, 'Body :' => json_encode($postParams), 'message :' => $e->getMessage()]);
        }
        if ($transactionResult['status'] !== 'success') {
            throw new SparkAPIResponseException($transactionResult['error']);
        } else {
            SmTransaction::create([
                'transaction_id' => $transactionResult['transaction_id'],
                'mpm_transaction_id' => $externalId
            ]);
        }
        return [
            'token' => $transactionResult['transaction_id'],
            'energy' => 0
        ];


    }

    public function clearMeter(Meter $meter)
    {
        // TODO: Implement clearMeter() method.
    }

}
