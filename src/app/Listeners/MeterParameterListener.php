<?php

namespace Inensus\SparkMeter\App\Listeners;

use App\Models\Meter\MeterParameter;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Inensus\SparkMeter\app\Services\CustomerService;
use Inensus\SparkMeter\app\Services\TariffService;
use Inensus\SparkMeter\Models\SmCustomer;
use Inensus\SparkMeter\Models\SmTariff;

class MeterParameterListener
{
    private $tariffService;
    private $customerService;
    public function __construct(TariffService $tariffService,CustomerService $customerService)
    {
        $this->tariffService=$tariffService;
        $this->customerService=$customerService;
    }

    /**
     * Sets the in_use to true
     * @param int $meter_id
     */
    public function onParameterSaved(int $meter_id)
    {
        Log::debug('listener Package',[]);
        $meterInfo=  MeterParameter::with(['tariff.tou','meter.manufacturer','geo','owner.addresses' =>
            static function ($q) {
                $q->where('is_primary', 1);
            }])->whereHas('meter', function ($q) use ($meter_id) {
            $q->where('id',$meter_id);
        })->first();
        if ($meterInfo->meter->manufacturer->name==="Spark Meters"){
            $tariffId=$meterInfo->tariff->id;
            $smTariff= SmTariff::query()->whereHas('mpmTariff',function ($q) use ($tariffId){
                $q->where('mpm_tariff_id',$tariffId);
            })->first();
            if (!$smTariff){
                $this->tariffService->createSmTariff($meterInfo->tariff);
            }
              if($meterInfo->owner){
                if ($meterInfo->owner->is_customer==1){
                    $customerId=$meterInfo->owner->id;
                    $smCustomer= SmCustomer::query()->whereHas('mpmPerson',function ($q) use ($customerId){
                        $q->where('mpm_customer_id',$customerId);
                    })->first();
                    if (!$smCustomer){
                        $this->customerService->createCustomer($meterInfo);
                    }
                }

            }


        }

    }

    public function subscribe(Dispatcher $events)
    {
        $events->listen('meterparameter.saved', 'Inensus\SparkMeter\App\Listeners\MeterParameterListener@onParameterSaved');
    }

}
