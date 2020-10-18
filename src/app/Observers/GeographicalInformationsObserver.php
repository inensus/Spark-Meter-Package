<?php


namespace Inensus\SparkMeter\app\Observers;


use App\Models\GeographicalInformation;
use App\Models\Person\Person;
use Illuminate\Support\Facades\Log;
use Inensus\SparkMeter\app\Helpers\SmTableEncryption;
use Inensus\SparkMeter\app\Services\CustomerService;
use Inensus\SparkMeter\Models\SmCustomer;

class GeographicalInformationsObserver
{
    private $customerService;
    private $smTableEncryption;
    public function __construct(CustomerService $customerService, SmTableEncryption $smTableEncryption)
    {
        $this->customerService = $customerService;
        $this->smTableEncryption = $smTableEncryption;
    }
    public function updated(GeographicalInformation $geographicalInformation)
    {

        if ($geographicalInformation->owner_type === 'meter_parameter') {
            $meterParameterId=$geographicalInformation->owner_id;
            $customer = Person::with(['meters.tariff','meters.geo', 'meters.geo','meters.meter'])->whereHas('meters',function ($q) use ($meterParameterId){return $q->where('id',$meterParameterId);})->first();
            $smCustomer = SmCustomer::query()->where('mpm_customer_id', $customer->id)->first();
            if ($smCustomer) {
                $address = $customer->addresses()->where('is_primary', 1)->first();
                $customerData = [
                    'id'=>$smCustomer->customer_id,
                    'active' => true,
                    'meter_tariff_name' => $customer->meters[0]->tariff->name,
                    'name' => $customer->name . ' ' . $customer->surname,
                    'code' => strval($customer->id),
                    'phone_number' => $address->phone,
                    'coords' => $customer->meters[0]->geo->points,
                    'address' => $address->street
                ];

                $this->customerService->updateSparkCustomerInfo($customerData);
                $smModelHash = $this->smTableEncryption->makeHash([
                    $customer->name . ' ' . $customer->surname,
                    $address->phone,
                    $customer->meters[0]->tariff->name,
                    $customer->meters[0]->meter->serial_number,
                ]);
                $smCustomer->update([
                    'hash'=>$smModelHash
                ]);
            }
        }

    }

}
