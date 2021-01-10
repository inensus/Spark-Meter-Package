<?php


namespace Inensus\SparkMeter\Console\Commands;


use App\Models\Sms;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Inensus\SparkMeter\Exceptions\CronJobException;
use Inensus\SparkMeter\Services\CustomerService;

class SparkMeterLowBalanceLimitNotifier extends Command
{
    protected $signature = 'spark-meter:lowBalanceLimitNotifier';
    protected $description = 'Notifies to customers if their credit balances reduce under low balance limit.';


    private $smCustomerService;
    private $sms;
    public function __construct(
        CustomerService $smCustomerService,
        Sms $sms
    ) {
        parent::__construct();
        $this->smCustomerService=$smCustomerService;
        $this->sms=$sms;
    }

    public function handler()
    {
        try {
            $customers = $this->smCustomerService->getLowBalancedCustomers();
            if ($customers) {
                foreach ($customers as $customer) {
                    $phone = $customer->addresses[0]->phone;
                    if ($phone !== null && $phone !== "") {
                        $message = 'Dear ' . $customer->name . ' ' . $customer->surname . ' your credit balance has reduced under ' . $customer->low_balance_limit . ', your currently balance is :' . $customer->credit_balance;
                        $this->sms->newQuery()->create([
                            'receiver' => $phone,
                            'body' => $message,
                            'direction' => 1,
                        ]);
                        resolve('SmsProvider')
                            ->sendSms(
                                $phone,
                                $message,
                                'manual'
                            );
                    }
                }

            }
        }catch (CronJobException $e){
            Log::critical('spark-meter:low-balance-limit-notifier failed.' ,['message'=>$e->getMessage()]);
        }
    }
}