<?php


namespace Inensus\SparkMeter\Observers;


use App\Models\Person\Person;
use Illuminate\Support\Facades\Log;
use Inensus\SparkMeter\Helpers\SmTableEncryption;
use Inensus\SparkMeter\Services\CustomerService;
use Inensus\SparkMeter\Models\SmCustomer;


class PersonObserver
{
    private $customerService;
    private $smTableEncryption;
    public function __construct(CustomerService $customerService,SmTableEncryption $smTableEncryption)
    {
        $this->customerService = $customerService;
        $this->smTableEncryption=$smTableEncryption;
    }

    public function updated(Person $person)
    {
        $smCustomer = SmCustomer::query()->where('mpm_customer_id',$person->id)->first();
        if ($smCustomer){
            $personId=$person->id;
            $customer=Person::with(['meters.tariff','meters.geo', 'meters.meter','addresses'=>function($q) {return $q->where('is_primary',1);} ])->where('id',$personId)->first();
            $customerData = [
                'id'=>$smCustomer->customer_id,
                'active'=>true,
                'meter_tariff_name' => $customer->meters[0]->tariff->name,
                'name' => $person->name . ' ' . $person->surname,
                'code' => strval($person->id),
                'phone_number' => $customer->addresses[0]->phone,
                'coords'=>$customer->meters[0]->geo->points,
                'address'=>$customer->addresses[0]->street
            ];

            $this->customerService->updateSparkCustomerInfo($customerData);
            $smModelHash = $this->smTableEncryption->makeHash([
                $person->name . ' ' . $person->surname,
                $customer->addresses[0]->phone,
                $customer->meters[0]->tariff->name,
                $customer->meters[0]->meter->serial_number,
            ]);

            $smCustomer->update([
                'hash'=>$smModelHash
            ]);
        }
    }
}
