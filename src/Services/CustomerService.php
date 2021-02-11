<?php


namespace Inensus\SparkMeter\Services;


use App\Http\Services\AddressService;
use App\Models\Address\Address;
use App\Models\City;
use App\Models\Cluster;
use App\Models\ConnectionGroup;
use App\Models\ConnectionType;
use App\Models\GeographicalInformation;
use App\Models\Manufacturer;
use App\Models\Meter\Meter;
use App\Models\Meter\MeterParameter;
use Exception;
use App\Models\Person\Person;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inensus\SparkMeter\Exceptions\SparkAPIResponseException;
use Inensus\SparkMeter\Helpers\SmTableEncryption;
use Inensus\SparkMeter\Http\Requests\SparkMeterApiRequests;
use Inensus\SparkMeter\Models\SmCustomer;
use Inensus\SparkMeter\Models\SmMeterModel;
use Inensus\SparkMeter\Models\SmSite;
use Inensus\SparkMeter\Models\SmTariff;
use Inensus\SparkMeter\Models\SyncStatus;


class CustomerService implements ISynchronizeService
{
    private $sparkMeterApiRequests;
    private $rootUrl = '/customer/';
    private $smTableEncryption;
    private $person;
    private $smCustomer;
    private $smMeterModel;
    private $smTariff;
    private $smSite;
    private $meter;
    private $manufacturer;
    private $meterParameter;
    private $connectionType;
    private $connectionGroup;
    private $city;
    private $cluster;

    public function __construct(
        SparkMeterApiRequests $sparkMeterApiRequests,
        SmTableEncryption $smTableEncryption,
        Person $person,
        SmCustomer $smCustomer,
        SmMeterModel $smMeterModel,
        SmTariff $smTariff,
        SmSite $smSite,
        Meter $meter,
        Manufacturer $manufacturer,
        MeterParameter $meterParameter,
        ConnectionType $connectionType,
        ConnectionGroup $connectionGroup,
        City $city,
        Cluster $cluster
    ) {
        $this->sparkMeterApiRequests = $sparkMeterApiRequests;
        $this->smTableEncryption = $smTableEncryption;
        $this->person = $person;
        $this->smCustomer = $smCustomer;
        $this->meter = $meter;
        $this->manufacturer = $manufacturer;
        $this->meterParameter = $meterParameter;
        $this->smMeterModel = $smMeterModel;
        $this->smTariff = $smTariff;
        $this->connectionType = $connectionType;
        $this->connectionGroup = $connectionGroup;
        $this->city = $city;
        $this->smSite = $smSite;
        $this->cluster = $cluster;
    }

    public function createCustomer($meterInfo, $siteId)
    {
        $params = [
            'meter_serial' => $meterInfo->meter->serial_number,
        ];
        $sparkCustomersResult = $this->sparkMeterApiRequests->getByParams('/customers', $params, $siteId);
        if ($sparkCustomersResult['status'] === 'failure') {
            $customerExists = false;
        } else {
            $smCustomerHash = $this->hashCustomerWithMeterSerial($meterInfo->meter->serial_number, $siteId);
            $customerExists = true;
            $this->smCustomer->newQuery()->create([
                'customer_id' => $sparkCustomersResult['customers'][0]['id'],
                'mpm_customer_id' => $meterInfo->owner->id,
                'site_id' => $siteId,
                'credit_balance' => $sparkCustomersResult['credit_balance'],
                'hash' => $smCustomerHash
            ]);
        }
        if (!$customerExists) {
            $rootUrl = '/system-info';
            $result = $this->sparkMeterApiRequests->get($rootUrl, $siteId);
            $grid = $result['grids'][0];

            $postParams = [
                'serial' => $meterInfo->meter->serial_number,
                'ground_serial' => $grid['serial'],
                'meter_tariff_name' => $meterInfo->tariff->name,
                'name' => $meterInfo->owner->name . ' ' . $meterInfo->owner->surname,
                'code' => strval($meterInfo->owner->id),
                'phone_number' => $meterInfo->owner->addresses[0]->phone,
                'operating_mode' => 'on',
                'starting_credit_balance' => "0",
            ];
            $result = $this->sparkMeterApiRequests->post($this->rootUrl, $postParams, $siteId);

            $smCustomerHash = $this->hashCustomerWithMeterSerial($meterInfo->meter->serial_number, $siteId);
            $this->smCustomer->newQuery()->create([
                'customer_id' => $result['customer_id'],
                'mpm_customer_id' => $meterInfo->owner->id,
                'site_id' => $siteId,
                'credit_balance' => $sparkCustomersResult['credit_balance'],
                'hash' => $smCustomerHash
            ]);
        }

    }

    public function getSmCustomers($request)
    {
        $perPage = $request->input('per_page') ?? 15;
        return $this->smCustomer->newQuery()->with(['mpmPerson', 'site.mpmMiniGrid'])->paginate($perPage);
    }

    public function getSmCustomersCount()
    {

        return count($this->smCustomer->newQuery()->get());
    }

    public function getSmCustomerByCustomerId($customerId)
    {
        return $this->smCustomer->newQuery()->with([
            'mpmPerson.meters.meter',
            'mpmPerson.addresses'
        ])->where('customer_id', $customerId)->first();
    }

    public function updateSparkCustomerInfo($customerData, $siteId)
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
                'address' => $customerData['address']
            ];

            $sparkCustomerId = $this->sparkMeterApiRequests->put('/customers/' . $customerId, $putParams, $siteId);
            return $sparkCustomerId['customer_id'];
        } catch (Exception $e) {
            Log::critical('updating customer info from spark api failed.', ['Error :' => $e->getMessage()]);
            throw  new Exception ($e->getMessage());
        }

    }

    public function createRelatedPerson($customer, $site_id)
    {
        try {
            DB::beginTransaction();
            $sparkCustomerMeterSerial = $customer['meters'][0]['serial'];
            $meter = $this->meter->newQuery()->where('serial_number', $sparkCustomerMeterSerial)->first();
            $person = null;
            if ($meter === null) {
                $meter = new Meter();
                $meterParameter = new MeterParameter();
                $geoLocation = new GeographicalInformation();
            } else {
                $meterParameter = $this->meterParameter->newQuery()->where('meter_id', $meter->id)->first();
                $geoLocation = $meterParameter->geo()->first();
                if ($geoLocation === null) {
                    $geoLocation = new GeographicalInformation();
                }
                $person = $this->person->newQuery()->whereHas('meters', static function ($q) use ($meterParameter) {
                    return $q->where('id', $meterParameter->id);
                })->first();

            }
            if ($person === null) {
                $data = [
                    'name' => ($customer['name']) ? ($customer['name']) : "",
                    'phone' => ($customer['phone_number']) ? ($customer['phone_number']) : null,
                    'street1' => ($customer['meters'][0]['street1']) ? ($customer['meters'][0]['street1']) : null,

                ];
                $person = $this->createPerson($data);
            }
            $meter->serial_number = $sparkCustomerMeterSerial;
            $manufacturer = $this->manufacturer->newQuery()->where('name', 'Spark Meters')->firstOrFail();
            $meter->manufacturer()->associate($manufacturer);
            $meterModelName = explode("-", $customer['meters'][0]['serial'])[0];
            $smModel = $this->smMeterModel->newQuery()->with('meterType')->where('model_name',
                $meterModelName)->firstOrFail();
            $meter->meterType()->associate($smModel->meterType);
            $meter->updated_at = date('Y-m-d h:i:s');
            $meter->save();

            $geoLocation->points = $customer['meters'][0]['coords'];
            $connectionType = $this->connectionType->newQuery()->first();
            $connectionGroup = $this->connectionGroup->newQuery()->first();
            $meterParameter->connection_type_id = $connectionType->id;
            $meterParameter->connection_group_id = $connectionGroup->id;

            $meterParameter->meter()->associate($meter);
            $meterParameter->owner()->associate($person);
            $currentTariffName = $customer['meters'][0]['current_tariff_name'];

            $smTariff = $this->smTariff->newQuery()->with('mpmTariff')->whereHas('mpmTariff',
                function ($q) use ($currentTariffName) {
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

            $site = $this->smSite->newQuery()->with('mpmMiniGrid')->where('site_id', $site_id)->firstOrFail();

            $sparkCity = $site->mpmMiniGrid->cities[0];
            $address = new Address();
            $address = $address->newQuery()->create([
                'city_id' => request()->input('city_id') ?? $sparkCity->id,
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

    public function createPerson($data)
    {
        $person = $this->person->newQuery()->create([
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

    public function updateRelatedPerson($customer, $person, $site_id)
    {
        $sparkCustomerMeterSerial = $customer['meters'][0]['serial'];
        $currentTariffName = $customer['meters'][0]['current_tariff_name'];
        $address = $person->addresses()->where('is_primary', 1)->first();
        $address->update([
            'phone' => $customer['phone_number'],
            'street' => $customer['meters'][0]['street1']
        ]);
        $meterParameters = $person->meters()->first();
        $meter = $meterParameters->meter();
        if ($meter) {
            $meter->update([
                'serial_number' => $sparkCustomerMeterSerial
            ]);
        }
        $smTariff = $this->smTariff->newQuery()->with(['mpmTariff'])->whereHas('mpmTariff',
            function ($q) use ($currentTariffName) {
                return $q->where('name', $currentTariffName);
            })->first();

        if ($smTariff) {
            $meterParameters->tariff()->associate($smTariff->mpmTariff);
            $meterParameters->save();
        }
        $geo = $meterParameters->geo()->first();
        if ($geo && array_key_exists('coords', $customer['meters'][0])) {
            $geo->points = $customer['meters'][0]['coords'] === "" ? config('spark.geoLocation') : $customer['meters'][0]['coords'];
            $geo->update();
        }
        $person->update([
            'name' => $customer['name'],
            'surname' => "",
            'updated_at' => date('Y-m-d h:i:s')
        ]);
        $site = $this->smSite->newQuery()->with('mpmMiniGrid')->where('site_id', $site_id)->firstOrFail();

        $sparkCity = $site->mpmMiniGrid->cities[0];
        $address = new Address();
        $address = $address->newQuery()->create([
            'city_id' => $sparkCity->id,
        ]);
        $address->owner()->associate($meterParameters);
        $address->geo()->associate($meterParameters->geo);
        $address->save();
    }

    public function checkConnectionAvailability()
    {

        $connectionType = $this->connectionType->newQuery()->first();

        $connectionGroup = $this->connectionGroup->newQuery()->first();

        $result = ['type' => false, 'group' => false];
        if ($connectionType) {
            $result['type'] = true;
        }
        if ($connectionGroup) {
            $result['group'] = true;
        }
        return $result;
    }

    public function hashCustomerWithMeterSerial($meterSerial, $siteId)
    {
        try {
            $params = [
                'meter_serial' => $meterSerial,
            ];
            $sparkCustomersResult = $this->sparkMeterApiRequests->getByParams('/customers', $params, $siteId);
            $phone = $sparkCustomersResult['phone_number'] == null ? 'NA' : $sparkCustomersResult['phone_number'];
            return $this->modelHasher($sparkCustomersResult, $phone);
        } catch (SparkAPIResponseException $e) {
            throw new SparkAPIResponseException($e->getMessage());
        }

    }

    public function updateCustomerLowBalanceLimit($customerId, $data)
    {
        $customer = $this->smCustomer->newQuery()->find($customerId);
        $customer->update([
            'low_balance_limit' => $data['low_balance_limit']
        ]);
        return $customer->fresh();
    }

    public function searchCustomer($searchTerm, $paginate)
    {

        if ($paginate === 1) {
            return $this->smCustomer->newQuery()->with(['mpmPerson', 'site.mpmMiniGrid'])
                ->WhereHas('site.mpmMiniGrid', function ($q) use ($searchTerm) {
                    $q->where('name', 'LIKE', '%' . $searchTerm . '%');
                })->orWhereHas('mpmPerson', function ($q) use ($searchTerm) {
                    $q->where('name', 'LIKE', '%' . $searchTerm . '%');
                })->paginate(15);
        }
        return $this->smCustomer->newQuery()->with(['mpmPerson', 'site.mpmMiniGrid'])
            ->WhereHas('site.mpmMiniGrid', function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', '%' . $searchTerm . '%');
            })->orWhereHas('mpmPerson', function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', '%' . $searchTerm . '%');
            })->get();

    }

    public function getLowBalancedCustomers()
    {

        return $this->smCustomer->newQuery()->with([
            'mpmPerson.addresses' => function ($q) {
                return $q->where('is_primary', 1);
            }
        ])
            ->whereNotNull('low_balance_limit')
            ->where('low_balance_limit', '>', 0)
            ->where('low_balance_limit', '>', 'credit_balance')->get();
    }


    public function sync()
    {
        try {
            $syncCheck = $this->syncCheck(true);
            $customersCollection = collect($syncCheck)->except('available_site_count');
            $customersCollection->each(function ($customers) {

                $customers['site_data']->filter(function ($customer) {
                    return $customer['syncStatus'] === 3;
                })->each(function ($customer) use ($customers) {
                    $mpmCustomerId = $this->createRelatedPerson($customer, $customers['site_id']);
                    $this->smCustomer->newQuery()->create([
                        'customer_id' => $customer['id'],
                        'mpm_customer_id' => $mpmCustomerId,
                        'site_id' => $customers['site_id'],
                        'credit_balance' => $customer['credit_balance'],
                        'hash' => $customer['hash']
                    ]);

                });
                $customers['site_data']->filter(function ($customer) {
                    return $customer['syncStatus'] === 2;
                })->each(function ($customer) use ($customers) {
                    is_null($customer['relatedPerson']) ? $this->createRelatedPerson($customer,
                        $customers['site_id']) : $this->updateRelatedPerson($customer, $customer['relatedPerson'],
                        $customers['site_id']);

                    $customer['registeredSparkCustomer']->update([
                        'hash' => $customer['hash'],
                        'site_id' => $customers['site_id'],
                        'credit_balance' => $customer['credit_balance'],
                    ]);
                });
            });

            return $this->smCustomer->newQuery()->with([
                'mpmPerson',
                'site.mpmMiniGrid'
            ])->paginate(config('spark.paginate'));

        } catch (Exception $e) {
            throw  new Exception ($e->getMessage());
        }

    }

    public function syncCheck($returnData = false)
    {
        $returnArray = ['available_site_count' => 0];
        $sites = $this->smSite->newQuery()->where('is_authenticated', 1)->where('is_online', 1)->get();
        foreach ($sites as $key => $site) {
            $returnArray['available_site_count'] = $key + 1;
            try {
                $sparkCustomers = $this->sparkMeterApiRequests->get('/customers', $site->site_id);
            } catch (SparkAPIResponseException $e) {
                Log::critical('Spark meter customers sync-check failed.', ['Error :' => $e->getMessage()]);
                if ($returnData) {
                    array_push($returnArray,
                        ['result' => false]);
                }
                throw  new Exception ($e->getMessage());
            }

            $sparkCustomersCollection = collect($sparkCustomers['customers'])->filter(function ($customer) {
                return $customer['id'] && $customer['meters'][0]['current_tariff_name'];
            });
            $sparkCustomers = $this->smCustomer->newQuery()->where('site_id', $site->site_id)->get();
            $people = $this->person->newQuery()->get();

            $sparkCustomersCollection->transform(function ($customer) use ($sparkCustomers, $people) {

                $registeredSparkCustomer = $sparkCustomers->firstWhere('customer_id', $customer['id']);
                $relatedPerson = null;
                $phone = $customer['phone_number'] == null ? 'NA' : $customer['phone_number'];
                $customerHash = $this->modelHasher($customer, $phone);
                if ($registeredSparkCustomer) {
                    $customer['syncStatus'] = $customerHash === $registeredSparkCustomer->hash ? SyncStatus::Synced : SyncStatus::Modified;
                    $relatedPerson = $people->find($registeredSparkCustomer->mpm_customer_id);
                } else {
                    $customer['syncStatus'] = SyncStatus::NotRegisteredYet;
                }
                $customer['hash'] = $customerHash;
                $customer['relatedPerson'] = $relatedPerson;
                $customer['registeredSparkCustomer'] = $registeredSparkCustomer;
                return $customer;
            });
            $customerSyncStatus = $sparkCustomersCollection->whereNotIn('syncStatus', 1)->count();

            if ($customerSyncStatus) {

                $returnData ? array_push($returnArray, [
                    'site_id' => $site->site_id,
                    'site_data' => $sparkCustomersCollection,
                    'result' => false
                ]) : array_push($returnArray, ['result' => false]);

            } else {
                $returnData ? array_push($returnArray, [
                    'site_id' => $site->site_id,
                    'site_data' => $sparkCustomersCollection,
                    'result' => true
                ]) : array_push($returnArray, ['result' => true]);
            }


        }
        return $returnArray;

    }

    public function modelHasher($model, ...$params): string
    {

        return $this->smTableEncryption->makeHash([
            $model['name'],
            $params[0],
            $model['meters'][0]['current_tariff_name'],
            $model['meters'][0]['serial'],

        ]);
    }

    public function syncCheckBySite($siteId)
    {
        try {
            $sparkCustomers = $this->sparkMeterApiRequests->get('/customers', $siteId);
        } catch (SparkAPIResponseException $e) {
            Log::critical('Spark meter customers sync-check-by-site failed.', ['Error :' => $e->getMessage()]);

            throw  new SparkAPIResponseException ($e->getMessage());
        }

        $sparkCustomersCollection = collect($sparkCustomers['customers'])->filter(function ($customer) {
            return $customer['id'] && $customer['meters'][0]['current_tariff_name'];
        });

        $sparkCustomers = $this->smCustomer->newQuery()->where('site_id', $siteId)->get();
        $people = $this->person->newQuery()->get();

        $sparkCustomersCollection->transform(function ($customer) use ($sparkCustomers, $people) {
            $registeredSparkCustomer = $sparkCustomers->firstWhere('customer_id', $customer['id']);
            $relatedPerson = null;
            $phone = $customer['phone_number'] == null ? 'NA' : $customer['phone_number'];
            $customerHash = $this->modelHasher($customer, $phone);
            if ($registeredSparkCustomer) {
                $customer['syncStatus'] = $customerHash === $registeredSparkCustomer->hash ? SyncStatus::Synced : SyncStatus::Modified;
                $relatedPerson = $people->find($registeredSparkCustomer->mpm_customer_id);
            } else {
                $customer['syncStatus'] = SyncStatus::NotRegisteredYet;
            }
            $customer['hash'] = $customerHash;
            $customer['relatedPerson'] = $relatedPerson;
            $customer['registeredSparkCustomer'] = $registeredSparkCustomer;
            return $customer;
        });
        $customerSyncStatus = $sparkCustomersCollection->whereNotIn('syncStatus', 1)->count();
        if ($customerSyncStatus) {
            return ['result' => false, 'message' => 'customers are not updated for site ' . $siteId];
        } else {
            return ['result' => true, 'message' => 'Records are updated'];
        }

    }
}
