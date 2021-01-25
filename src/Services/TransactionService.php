<?php


namespace Inensus\SparkMeter\Services;


use App\Models\Meter\MeterToken;
use App\Models\Transaction\ThirdPartyTransaction;
use App\Models\Transaction\Transaction;
use Exception;
use Illuminate\Support\Facades\Log;
use Inensus\SparkMeter\Http\Requests\SparkMeterApiRequests;
use Inensus\SparkMeter\Models\SmOrganization;
use Inensus\SparkMeter\Models\SmSite;
use Inensus\SparkMeter\Models\SmTariff;
use Inensus\SparkMeter\Models\SmTransaction;


class TransactionService
{

    private $sparkMeterApiRequests;
    private $sparkOrganization;
    private $sparkCredentialService;
    private $sparkSiteService;
    private $sparkCustomerService;
    private $sparkMeterModelService;
    private $sparkTariffService;
    private $sparkTransaction;
    private $sparkTariff;
    private $thirdPartyTransaction;
    private $transaction;
    private $meterToken;
    private $sparkSite;
    private $rootUrl = '/transaction/';

    public function __construct(
        SparkMeterApiRequests $sparkMeterApiRequests,
        CredentialService $sparkCredentialService,
        SiteService $sparkSiteService,
        CustomerService $sparkCustomerService,
        MeterModelService $sparkMeterModelService,
        SmTariff $sparkTariff,
        SmSite $sparkSite,
        TariffService $sparkTariffService,
        SmTransaction $sparkTransaction,
        SmOrganization $sparkOrganization,
        ThirdPartyTransaction $thirdPartyTransaction,
        Transaction $transaction,
        MeterToken $meterToken

    ) {
        $this->sparkMeterApiRequests = $sparkMeterApiRequests;
        $this->sparkOrganization = $sparkOrganization;
        $this->sparkCredentialService = $sparkCredentialService;
        $this->sparkSiteService = $sparkSiteService;
        $this->sparkCustomerService = $sparkCustomerService;
        $this->sparkTariff = $sparkTariff;
        $this->sparkSite = $sparkSite;
        $this->sparkMeterModelService = $sparkMeterModelService;
        $this->sparkTariffService = $sparkTariffService;
        $this->sparkTransaction = $sparkTransaction;
        $this->thirdPartyTransaction = $thirdPartyTransaction;
        $this->transaction = $transaction;
        $this->meterToken = $meterToken;
    }

    public function updateTransactionStatus($smTransaction)
    {
        try {
            $smTransactionResult = $this->sparkMeterApiRequests->getInfo($this->rootUrl, $smTransaction->transaction_id,
                $smTransaction->site_id);
            $smStatus = $smTransactionResult['transaction']['status'];
        } catch (Exception $e) {
            $smStatus = $e->getMessage();
            Log::critical('Updating SmTransaction status information failed.', ['Error :' => $e->getMessage()]);
        }
        switch ($smStatus) {
            case "processed":
                $status = 1;
                break;
            case "pending":
                $status = 0;
                break;
            case "not-processed":
            case "error":
                $status = -1;
                break;
            default:
                $status = 1;
        }
        $transaction = $this->transaction->newQuery()->with('originalAirtel', 'originalVodacom', 'orginalAgent',
            'originalThirdParty')->find($smTransaction['external_id']);
        if ($transaction->originalAirtel) {

            $transaction->originalAirtel->update([
                'status' => $status
            ]);
        } else {
            if ($transaction->originalVodacom) {
                $transaction->originalVodacom->update([
                    'status' => $status
                ]);
            } else {
                if ($transaction->orginalAgent) {
                    $transaction->orginalAgent->update([
                        'status' => $status
                    ]);
                } else {
                    if ($transaction->originalThirdParty) {
                        $transaction->originalThirdParty->update([
                            'status' => $status
                        ]);
                    }
                }
            }
        }
        $smTransaction->update([
            'status' => $smStatus
        ]);
    }

    public function sync()
    {
        $syncCheck = $this->syncCheck();

        if (!array_key_exists('error', $syncCheck)) {

            $organization = $this->sparkOrganization->newQuery()->first();
            $koiosUrl = '/organizations/' . $organization->organization_id . '/data/historical';


            $params = [
                "filters" => [
                    "entity_types" => ["transactions"]
                ],
                "cursor" => null
            ];
            $result = $this->sparkMeterApiRequests->postToKoios($koiosUrl, $params);
            $params['cursor'] = $result['cursor'];
            $transactions = $result['results'];
            do {
                if (is_array($transactions) && count($transactions)) {
                    foreach ($transactions as $key => $transaction) {
                        if ($transaction['type'] === 'transaction') {

                            if (array_key_exists($transaction['site'], $syncCheck)) {
                                $syncResult = $syncCheck[$transaction['site']]['result'];
                                $syncMessage = $syncCheck[$transaction['site']]['message'];
                                if ($syncResult) {
                                    switch ($transaction['state']) {
                                        case "processed":
                                            $status = 1;
                                            break;
                                        case "pending":
                                            $status = 0;
                                            break;
                                        case "reversed":
                                        case "error":
                                            $status = -1;
                                            break;
                                        default:
                                            $status = 1;
                                    }
                                    $transactionRecord = $this->sparkTransaction->newQuery()->where('transaction_id',
                                        $transaction['transaction_id'])->first();
                                    if (!$transactionRecord) {
                                        $site = $this->sparkSiteService->getThunderCloudInformation($transaction['site']);
                                        if ($site) {
                                            if ($site->is_authenticated > 0) {
                                                if (array_key_exists('customer', $transaction['to'])) {
                                                    $sparkTransaction = $this->sparkTransaction->newQuery()->create([
                                                        'site_id' => $transaction['site'],
                                                        'customer_id' => $transaction['to']['customer']['id'],
                                                        'transaction_id' => $transaction['transaction_id'],
                                                        'status' => $transaction['state'],
                                                        'external_id' => $transaction['external_id'],

                                                    ]);
                                                    if (!$transaction['reference_id']) {

                                                        $thirdPartyTransaction = $this->thirdPartyTransaction->newQuery()->make([
                                                            'transaction_id' => $transaction['transaction_id'],
                                                            'status' => $status,
                                                        ]);
                                                        $thirdPartyTransaction->manufacturerTransaction()->associate($sparkTransaction);
                                                        $thirdPartyTransaction->save();

                                                        $sparkCustomer = $this->sparkCustomerService->getSmCustomerByCustomerId($sparkTransaction->customer_id);
                                                        if ($sparkCustomer) {
                                                            $meterParameter = $sparkCustomer->mpmPerson->meters[0];
                                                            $mainTransaction = $this->transaction->newQuery()->make([
                                                                'amount' => (int)$transaction['amount'],
                                                                'sender' => $sparkCustomer->mpmPerson->addresses[0]->phone ?? '-',
                                                                'message' => $meterParameter->meter->serial_number,
                                                                'type' => 'energy',
                                                                'created_at' => $transaction['created'],
                                                                'updated_at' => $transaction['created'],
                                                            ]);

                                                            $mainTransaction->originalTransaction()->associate($thirdPartyTransaction);
                                                            $mainTransaction->save();

                                                            $owner = $meterParameter->owner;
                                                            $smTariff = $this->sparkTariff->newQuery()->where('mpm_tariff_id',
                                                                $meterParameter->tariff()->first()->id)->first();
                                                            $tariff = $this->sparkTariffService->singleSync($smTariff);
                                                            $chargedEnergy = (int)$transaction['amount'] / ($tariff->total_price / 100);

                                                            $token = $sparkTransaction->site_id . '-' . $transaction['source'] . '-' . $sparkTransaction->customer_id;

                                                            $token = $this->meterToken->newQuery()->make([
                                                                'token' => $token,
                                                                'energy' => $chargedEnergy,

                                                            ]);
                                                            $token->transaction()->associate($mainTransaction);
                                                            $token->meter()->associate($meterParameter->meter);
                                                            //save token
                                                            $token->save();

                                                            event('payment.successful', [
                                                                'amount' => $mainTransaction->amount,
                                                                'paymentService' => $mainTransaction->original_transaction_type,
                                                                'paymentType' => 'energy',
                                                                'sender' => $mainTransaction->sender,
                                                                'paidFor' => $token,
                                                                'payer' => $owner,
                                                                'transaction' => $mainTransaction,
                                                            ]);
                                                        }
                                                    }
                                                }

                                            }
                                        }
                                    } else {
                                        $transactionRecord->update([
                                            'status' => $transaction['state'],
                                        ]);
                                        $thirdPartyTransaction = $this->thirdPartyTransaction->newQuery()->where('transaction_id',
                                            $transaction['transaction_id'])->first();
                                        if ($thirdPartyTransaction) {
                                            $thirdPartyTransaction->update([
                                                'status' => $status,
                                            ]);
                                        }

                                    }
                                } else {
                                    Log::debug('Transaction synchronising cancelled', ['message' => $syncMessage]);
                                }


                            }

                        }
                    }
                    Log::debug('cursor', ['message' => $params['cursor']]);
                    $result = $this->sparkMeterApiRequests->postToKoios($koiosUrl, $params);
                    $params['cursor'] = $result['cursor'];
                    $transactions = $result['results'];
                }
            } while ($params['cursor']);
        } else {
            Log::debug('Transaction synchronising cancelled', ['message' => $syncCheck['error']['message']]);
        }
    }

    public function syncCheck()
    {

        $returnArray = [];
        $credentials = $this->sparkCredentialService->getCredentials();
        if ($credentials) {
            if ($credentials->is_authenticated > 0) {
                $siteSynchronized = $this->sparkSiteService->syncCheck();

                if ($siteSynchronized['result']) {
                    $sites = $this->sparkSite->newQuery()->where('is_authenticated', 1)->get();
                    foreach ($sites as $site) {
                        $meterModelSynchronized = $this->sparkMeterModelService->syncCheckBySite($site->site_id);
                        if ($meterModelSynchronized['result']) {

                            $tariffSynchronized = $this->sparkTariffService->syncCheckBySite($site->site_id);
                            if ($tariffSynchronized['result']) {
                                $customerSynchronized = $this->sparkCustomerService->syncCheckBySite($site->site_id);

                                $returnArray[$site->site_id] = [
                                    'result' => $customerSynchronized['result'],
                                    'message' => $customerSynchronized['message']
                                ];

                            } else {
                                $returnArray[$site->site_id] = [
                                    'result' => $tariffSynchronized['result'],
                                    'message' => $tariffSynchronized['message']
                                ];

                            }
                        } else {
                            $returnArray[$site->site_id] = [
                                'result' => $meterModelSynchronized['result'],
                                'message' => $meterModelSynchronized['message']
                            ];

                        }
                    }
                } else {
                    $returnArray['error'] = [
                        'result' => false,
                        'message' => 'Site records are not up to date.'
                    ];

                }
            } else {
                $returnArray['error'] = [
                    'result' => false,
                    'message' => 'Credentials records are not up to date.'
                ];
            }
        } else {
            $returnArray['error'] = [
                'result' => false,
                'message' => 'No Credentials record found.'
            ];
        }
        return $returnArray;
    }
}