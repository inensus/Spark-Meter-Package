<?php


namespace Inensus\SparkMeter\Services;

use App;
use App\Http\Services\AddressService;
use App\Models\Address\Address;
use App\Models\GeographicalInformation;
use App\Models\Manufacturer;
use App\Models\Meter\Meter;
use App\Models\Meter\MeterParameter;
use Exception;
use App\Models\Person\Person;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inensus\SparkMeter\Helpers\SmTableEncryption;
use Inensus\SparkMeter\Http\Requests\SparkMeterApiRequests;
use Inensus\SparkMeter\Models\SmCustomer;
use Inensus\SparkMeter\Models\SmGrid;
use Inensus\SparkMeter\Models\SmMeterModel;
use Inensus\SparkMeter\Models\SmTariff;

class CustomerService implements ISynchronizeService
{
    private $sparkMeterApiRequests;
    private $rootUrl = '/customer/';
    private $smTableEncryption;

    public function __construct(
        SparkMeterApiRequests $sparkMeterApiRequests,
        AddressService $addressService,
        SmTableEncryption $smTableEncryption
    ) {
        $this->sparkMeterApiRequests = $sparkMeterApiRequests;
        $this->smTableEncryption = $smTableEncryption;
    }

    public function createCustomer($meterInfo)
    {
        $params = [
            'meter_serial' => $meterInfo->meter->serial_number,
        ];
        $sparkCustomersResult = $this->sparkMeterApiRequests->getByParams('/customers', $params);
        $smModelHash = $this->smTableEncryption->makeHash([
            $meterInfo->owner->name . ' ' . $meterInfo->owner->surname,
            $meterInfo->owner->addresses[0]->phone,
            $meterInfo->tariff->name,
            $meterInfo->meter->serial_number,
        ]);
        if ($sparkCustomersResult['status'] === 'failure') {
            $customerExists = false;
        } else {
            $customerExists = true;
            SmCustomer::create([
                'customer_id' => $sparkCustomersResult['customers'][0]['id'],
                'mpm_customer_id' => $meterInfo->owner->id,
                'hash'=>$smModelHash
            ]);
        }
        if (!$customerExists) {
            $grid = SmGrid::take(1)->first();
            $postParams = [
                'serial' => $meterInfo->meter->serial_number,
                'ground_serial' => $grid->grid_serial,
                'meter_tariff_name' => $meterInfo->tariff->name,
                'name' => $meterInfo->owner->name . ' ' . $meterInfo->owner->surname,
                'code' => strval($meterInfo->owner->id),
                'phone_number' => $meterInfo->owner->addresses[0]->phone,
                'operating_mode' => 'on',
                'starting_credit_balance' => "0",
            ];
            $result = $this->sparkMeterApiRequests->post($this->rootUrl, $postParams);
            SmCustomer::create([
                'customer_id' => $result['customer_id'],
                'mpm_customer_id' => $meterInfo->owner->id,
                'hash'=>$smModelHash
            ]);
        }

    }

    public function getSmCustomers($request)
    {
        $perPage = $request->input('per_page') ?? 15;
        return SmCustomer::with('mpmPerson')->paginate($perPage);
    }

    public function getSmCustomersCount()
    {

        return count(SmCustomer::query()->get());
    }

    public function createPerson($data)
    {
        $person = Person::create([
            'name' => $data['name'],
            'is_customer' => 1
        ]);
        $addressService = App::make(AddressService::class);
        $addressParams = [
            'phone' => $data['phone'],
            'street' => $data['street1'],
            'is_primary' => 1,
        ];
        $address = $addressService->instantiate($addressParams);
        $addressService->assignAddressToOwner($person, $address);

        return $person;
    }

    public function createRelatedPerson($model)
    {
        try {
            DB::beginTransaction();
            $sparkCustomerMeterSerial = $model['meters'][0]['serial'];
            $meter = Meter::query()->where('serial_number', $sparkCustomerMeterSerial)->first();
            $person = null;
            if ($meter === null) {
                $meter = new Meter();
                $meterParameter = new MeterParameter();
                $geoLocation = new GeographicalInformation();
            } else {
                $meterParameter = MeterParameter::query()->where('meter_id', $meter->id)->first();
                $geoLocation = $meterParameter->geo()->first();
                if ($geoLocation === null) {
                    $geoLocation = new GeographicalInformation();
                }
                $person = Person::query()->whereHas('meters', static function ($q) use ($meterParameter) {
                    return $q->where('id', $meterParameter->id);
                })->first();

            }
            if ($person === null) {
                $data = [
                    'name' => ($model['name']) ? ($model['name']) : "",
                    'phone' => ($model['phone_number']) ? ($model['phone_number']) : null,
                    'street1' => ($model['meters'][0]['street1']) ? ($model['meters'][0]['street1']) : null,

                ];
                $person = $this->createPerson($data);
            }
            $meter->serial_number = $sparkCustomerMeterSerial;
            $manufacturer = Manufacturer::where('name', 'Spark Meters')->firstOrFail();
            $meter->manufacturer()->associate($manufacturer);
            $meterModelName = explode("-", $model['meters'][0]['serial'])[0];
            $smModel = SmMeterModel::with('meterType')->where('model_name', $meterModelName)->firstOrFail();
            $meter->meterType()->associate($smModel->meterType);
            $meter->updated_at = date('Y-m-d h:i:s');
            $meter->save();

            $geoLocation->points = $model['meters'][0]['coords'];
            $connectionType = App\Models\ConnectionType::first();
            $connectionGroup = App\Models\ConnectionGroup::first();
            $meterParameter->connection_type_id = $connectionType->id;
            $meterParameter->connection_group_id = $connectionGroup->id;

            $meterParameter->meter()->associate($meter);
            $meterParameter->owner()->associate($person);
            $currentTariffName = $model['meters'][0]['current_tariff_name'];

            $smTariff = SmTariff::with(['mpmTariff'])->whereHas('mpmTariff', function ($q) use ($currentTariffName) {
                return $q->where('name', $currentTariffName);
            })->first();
            if ($smTariff) {
                $meterParameter->tariff()->associate($smTariff->mpmTariff);
            }
            $meterParameter->save();
            if ($geoLocation->points == null) {
                $geoLocation->points = config('spark.geoLocation');
            }
            $meterParameter->geo()->save($geoLocation);
            $address = new Address();
            $address = $address->newQuery()->create([
                'city_id' => request()->input('city_id') ?? 1,
            ]);
            $address->owner()->associate($meterParameter);
            $address->geo()->associate($meterParameter->geo);
            $address->save();
            DB::commit();
            return $person->id;
        } catch (Exception $e) {
            DB::rollBack();
            Log::critical('Error while synchronizing spark customers', ['message' => $e->getMessage()]);
            throw  new Exception($e->getMessage());
        }
    }

    public function updateSparkCustomerInfo($customerData)
    {
        try {
            $customerId = $customerData['id'];
            $putParams = [

                'active' => $customerData['active'],
                'meter_tariff_name' => $customerData['meter_tariff_name'],
                'name' => $customerData['name'],
                'code' => $customerData['code'],
                'phone_number' => $customerData['phone_number'],
                'coords' => $customerData['coords'],
                'address'=>$customerData['address']
            ];

            $sparkCustomerId = $this->sparkMeterApiRequests->put('/customers/' . $customerId, $putParams);
            return $sparkCustomerId['customer_id'];
        } catch (Exception $e) {
            Log::critical('updating customer info from spark api failed.', ['Error :' => $e->getMessage()]);
            throw  new Exception ($e->getMessage());
        }

    }

    public function updateRelatedPerson($model, $person)
    {
        $sparkCustomerMeterSerial = $model['meters'][0]['serial'];
        $currentTariffName=$model['meters'][0]['current_tariff_name'];
        $address = $person->addresses()->where('is_primary', 1)->first();
        $address->update([
            'phone'=>$model['phone_number'],
            'street'=>$model['meters'][0]['street1']
        ]);
        $meterParameters =$person->meters()->first();
        $meter=$meterParameters->meter();
        if ($meter) {
            $meter->update([
                'serial_number'=>$sparkCustomerMeterSerial
            ]);
        }
        $smTariff = SmTariff::with(['mpmTariff'])->whereHas('mpmTariff', function ($q) use ($currentTariffName) {
            return $q->where('name', $currentTariffName);
        })->first();

        if ($smTariff) {
            $meterParameters->tariff()->associate($smTariff->mpmTariff);
            $meterParameters->save();
        }
        $geo=$meterParameters->geo()->first();
             if ($geo && array_key_exists('coords',$model['meters'][0])) {
              $geo->points = $model['meters'][0]['coords']===""?config('spark.geoLocation'):$model['meters'][0]['coords'];
              $geo->update();
          }
        $person->update([
            'name' => $model['name'],
            'surname' => "",
            'updated_at' => date('Y-m-d h:i:s')]);
    }

    public function sync()
    {
        try {
            $syncCheck = $this->syncCheck(true);

            if (!$syncCheck['result']) {
                $sparkCustomers = $syncCheck['data'];
                foreach ($sparkCustomers as $key => $model) {
                    if (($model['id']) && ($model['meters'][0]['current_tariff_name'])) {
                        $registeredSmCustomer = SmCustomer::where('customer_id', $model['id'])->first();
                        $smModelHash = $this->smTableEncryption->makeHash([
                            $model['name'],
                            $model['phone_number'],
                            $model['meters'][0]['current_tariff_name'],
                            $model['meters'][0]['serial'],
                        ]);
                        if ($registeredSmCustomer) {
                            $isHashChanged = $registeredSmCustomer->hash===$smModelHash?false:true;

                            $relatedPerson = Person::query()->where('id', $registeredSmCustomer->mpm_customer_id)->first();
                            if (!$relatedPerson) {
                                $this->createRelatedPerson($model);
                                $registeredSmCustomer->update([
                                    'hash'=>$smModelHash
                                ]);
                            } else if($relatedPerson && $isHashChanged){
                                $this->updateRelatedPerson($model, $relatedPerson);
                                $registeredSmCustomer->update([
                                    'hash'=>$smModelHash
                                ]);
                            }else{
                                continue;
                            }
                        } else {
                            $mpmCustomerId = $this->createRelatedPerson($model);
                            SmCustomer::create([
                                'customer_id' => $model['id'],
                                'mpm_customer_id' => $mpmCustomerId,
                                'hash'=>$smModelHash
                            ]);
                        }
                    }
                }

            }
            return SmCustomer::with('mpmPerson')->paginate(config('spark.paginate'));

        } catch (Exception $e) {
            throw  new Exception ($e->getMessage());
        }

    }

    public function syncCheck($returnData = false)
    {
        try {
            $sparkCustomers = $this->sparkMeterApiRequests->get('/customers');
            $sparkCustomersCount = 0;
            foreach ($sparkCustomers['customers'] as $key => $model) {
                if (($model['id']) && ($model['meters'][0]['current_tariff_name'])) {
                    $sparkCustomersCount++;
                }
            }
            $smCustomers = SmCustomer::get();
            $smCustomersCount = count($smCustomers);
            if ($sparkCustomersCount === $smCustomersCount) {
                foreach ($sparkCustomers['customers'] as $key => $model) {
                    if (($model['id']) && ($model['meters'][0]['current_tariff_name'])) {
                        $registeredSmCustomer = SmCustomer::where('customer_id', $model['id'])->first();
                        if ($registeredSmCustomer) {
                            $modelHash = $this->smTableEncryption->makeHash([
                                $model['name'],
                                $model['phone_number'],
                                $model['meters'][0]['current_tariff_name'],
                                $model['meters'][0]['serial'],
                            ]);
                            $smHash = $registeredSmCustomer->hash;
                            if ($modelHash !== $smHash) {
                                break;
                            }
                            $sparkCustomersCount--;
                        }else{
                            break;
                        }
                    }
                }
                if ($sparkCustomersCount === 0) {
                    return $returnData ? ['data' => $sparkCustomers['customers'], 'result' => true] : ['result' => true];
                }
                return $returnData ? ['data' => $sparkCustomers['customers'], 'result' => false] : ['result' => false];
            }
            return $returnData ? ['data' => $sparkCustomers['customers'], 'result' => false] : ['result' => false];
        } catch (Exception $e) {
            if ($returnData){
                return  ['result' => false];
            }
            throw  new Exception ($e->getMessage());
        }
    }
}
