<?php


namespace Inensus\SparkMeter\Services;

use App\Models\AccessRate\AccessRate;
use App\Models\Meter\MeterTariff;
use App\Models\TimeOfUsage;

use Exception;
use Illuminate\Support\Facades\Log;
use Inensus\SparkMeter\Helpers\SmTableEncryption;
use Inensus\SparkMeter\Http\Requests\SparkMeterApiRequests;
use Inensus\SparkMeter\Models\SmMeterModel;
use Inensus\SparkMeter\Models\SmSite;
use Inensus\SparkMeter\Models\SmTariff;

class TariffService implements ISynchronizeService
{

    private $sparkMeterApiRequests;
    private $rootUrl = '/tariffs';
    private $smTableEncryption;
    private $smMeterModel;
    private $smSite;
    private $smTariff;
    private $meterTariff;
    private $timeOfUsage;
    private $accessRate;

    public function __construct(
        SparkMeterApiRequests $sparkMeterApiRequests,
        SmTableEncryption $smTableEncryption,
        SmMeterModel $smMeterModel,
        SmSite $smSite,
        SmTariff $smTariff,
        MeterTariff $meterTariff,
        TimeOfUsage $timeOfUsage,
        AccessRate $accessRate
    ) {
        $this->sparkMeterApiRequests = $sparkMeterApiRequests;
        $this->smTableEncryption = $smTableEncryption;
        $this->smMeterModel = $smMeterModel;
        $this->smSite = $smSite;
        $this->smTariff = $smTariff;
        $this->meterTariff = $meterTariff;
        $this->timeOfUsage = $timeOfUsage;
        $this->accessRate = $accessRate;
    }

    public function createSmTariff($tariff, $siteId)
    {
        $touEnabled = count($tariff->tou) > 0;
        $maxValue = $this->smMeterModel->newQuery()->max('continuous_limit');
        $tous = [];
        $accessRate = $this->accessRate->newQuery()->where('tariff_id', $tariff->id)->first();
        $planEnabled = false;
        $planDuration = '1m';
        $plan_price = 0;
        if ($accessRate) {
            $planDuration = $accessRate->period < 30 ? '1d' : '1m';
            $planEnabled = true;
            $plan_price = $accessRate->amount;
        }
        $sparkTariffs = $this->sparkMeterApiRequests->get($this->rootUrl, $siteId);
        $tariffExists = false;
        foreach ($sparkTariffs['tariffs'] as $key => $value) {
            if ($value['name'] === $tariff->name) {
                $tariffExists = true;

                $modelHash = $this->modelHasher($value,null);
                $this->smTariff->newQuery()->create([
                    'tariff_id' => $value['id'],
                    'mpm_tariff_id' => $tariff->id,
                    'flat_load_limit' => $maxValue,
                    'site_id' => $siteId,
                    'plan_duration' => '1m',
                    'plan_price' => $plan_price,
                    'hash' => $modelHash
                ]);
                break;
            }
        }
        if (!$tariffExists) {

            $modelTouString = '';
            foreach ($tariff->tou as $key => $value) {
                $modelTouString .= $value->start . $value->end . doubleval($value->value);
                $tous[$key] = [
                    'start' => $value->start,
                    'end' => $value->end,
                    'value' => $value->value
                ];
            }

            $postParams = [
                'cycle_start_day_of_month' => 1,
                'name' => $tariff->name,
                'flat_price' => $tariff->price / 100,
                'tariff_type' => 'flat',
                'load_limit_type' => 'flat',
                'flat_load_limit' => $maxValue,
                'daily_energy_limit_enabled' => false,
                'tou_enabled' => $touEnabled,
                'tous' => $tous,
                'plan_enabled' => $planEnabled,
                'plan_duration' => $planDuration,
                'plan_fixed_fee' => $plan_price
            ];
            $result = $this->sparkMeterApiRequests->post($this->rootUrl, $postParams, $siteId);
            $modelHash = $this->smTableEncryption->makeHash([
                $tariff->name,
                (int)$tariff->price,
                $modelTouString
            ]);
            $this->smTariff->newQuery()->create([
                'tariff_id' => $result['tariff']['id'],
                'mpm_tariff_id' => $tariff->id,
                'flat_load_limit' => $maxValue,
                'plan_duration' => '1m',
                'plan_price' => $plan_price,
                'hash' => $modelHash
            ]);
        }

    }

    public function getSmTariffs($request)
    {
        $perPage = $request->input('per_page') ?? 15;
        return $this->smTariff->newQuery()->with(['mpmTariff', 'site.mpmMiniGrid'])->paginate($perPage);
    }

    public function getSmTariffsCount()
    {
        return count($this->smTariff->newQuery()->get());
    }

    public function createRelatedTariff($tariff)
    {
        $meterTariff = $this->meterTariff->newQuery()->create([
            'name' => $tariff['name'],
            'price' => $tariff['flat_price'] * 100,
            'currency' => config('spark.currency'),
            'total_price' => $tariff['flat_price'] * 100,
        ]);
        foreach ($tariff['tous'] as $key => $tou) {
            $this->timeOfUsage->newQuery()->create([
                'tariff_id' => $meterTariff->id,
                'start' => $tou['start'],
                'end' => $tou['end'],
                'value' => doubleval($tou['value'])
            ]);
        }
        if ($tariff['plan_enabled'] && $tariff['plan_fixed_fee'] > 0) {
            $this->setAccessRate($tariff, $meterTariff->id, $tariff['plan_enabled']);
        }
        return $meterTariff;
    }

    public function updateRelatedTariff($model, $tariff)
    {
        if (count($model['tous']) === count($tariff->tou)) {
            foreach ($model['tous'] as $key => $tou) {
                $tariff->tou[$key]->start = $tou['start'];
                $tariff->tou[$key]->end = $tou['end'];
                $tariff->tou[$key]->value = doubleval($tou['value']);
                $tariff->tou[$key]->update();
            }
        } else {
            foreach ($tariff->tou as $key => $tou) {
                $tou->delete();
            }
            foreach ($model['tous'] as $key => $tou) {
                $this->timeOfUsage->newQuery()->create([
                    'tariff_id' => $tariff->id,
                    'start' => $tou['start'],
                    'end' => $tou['end'],
                    'value' => doubleval($tou['value']),
                ]);
            }
        }
        if ($model['plan_enabled']) {
            $this->setAccessRate($model, $tariff->id, $model['plan_enabled']);
        }
        $relatedTariffHashString = $this->smTableEncryption->makeHash([
            $tariff->name,
            $tariff->price,
            $tariff->total_price
        ]);
        $modelTariffHashString = $this->smTableEncryption->makeHash([
            $model['name'],
            ($model['flat_price'] * 100),
            $model['flat_price'] * 100
        ]);
        if ($relatedTariffHashString !== $modelTariffHashString) {
            $tariff->update([
                'name' => $model['name'],
                'price' => $model['flat_price'] * 100,
                'total_price' => $model['flat_price'] * 100,
            ]);
        }
    }

    private function setAccessRate($model, $tariffId, $planEnabled)
    {
        $accessRate = $this->accessRate->newQuery()->where('tariff_id', $tariffId)->first();
        $duration = array_key_exists("plan_duration", $model) ? $model['plan_duration'] : '1m';
        if ($accessRate) {
            if ($planEnabled) {
                $accessRate->update([
                    'tariff_id' => $tariffId,
                    'amount' => $model['plan_fixed_fee'],
                    'period' => $duration === '1m' ? 30 : 1,
                ]);
            } else {
                $accessRate->delete();
            }
        } else {
            if ($planEnabled) {
                $this->accessRate->newQuery()->create([
                    'tariff_id' => $tariffId,
                    'amount' => $model['plan_fixed_fee'],
                    'period' => $duration === '1m' ? 30 : 1,
                ]);
            }
        }

    }

    public function getSparkTariffInfo($tariffId)
    {
        try {

            $smTariff = $this->smTariff->newQuery()->with(['mpmTariff.accessRate', 'site'])->where('tariff_id',
                $tariffId)->first();
            $sparkTariff = $this->sparkMeterApiRequests->getInfo('/tariff/', $tariffId, $smTariff->site->site_id);
            return $sparkTariff['tariff'];
        } catch (Exception $e) {
            Log::critical('Getting tariff info from spark api failed.', ['Error :' => $e->getMessage()]);
            throw  new Exception ($e->getMessage());
        }
    }

    public function updateSparkTariffInfo($tariffData)
    {
        try {
            $tariffId = $tariffData['id'];
            $smTariff = $this->smTariff->newQuery()->where('tariff_id', $tariffId)->first();
            $putParams = array(
                'cycle_start_day_of_month' => 1,
                'name' => $tariffData['name'],
                'flat_price' => $tariffData['flat_price'],
                'tariff_type' => 'flat',
                'load_limit_type' => 'flat',
                'flat_load_limit' => $tariffData['flat_load_limit'],
                'daily_energy_limit_enabled' => $tariffData['daily_energy_limit_enabled'],
                'daily_energy_limit_value' => $tariffData['daily_energy_limit_value'],
                'daily_energy_limit_reset_hour' => $tariffData['daily_energy_limit_reset_hour'],
                'tou_enabled' => $tariffData['tou_enabled'],
                'tous' => $tariffData['tous'],
                'plan_enabled' => $tariffData['plan_enabled'],
                'plan_duration' => array_key_exists("plan_duration", $tariffData) ? $tariffData['plan_duration'] : '1m',
                'plan_price' => $tariffData['plan_price'],
                'plan_fixed_fee' => $tariffData['planFixedFee']
            );

            $sparkTariff = $this->sparkMeterApiRequests->put('/tariff/' . $tariffId, $putParams, $smTariff->site_id);
            return $sparkTariff['tariff'];
        } catch (Exception $e) {

            Log::critical('updating tariff info from spark api failed.',
                ['Error :' => $e->getMessage(), 'data :' => $tariffData]);
            throw  new Exception ($e->getMessage());
        }

    }

    public function singleSync($smTariff)
    {
        $tariff = $this->getSparkTariffInfo($smTariff->tariff_id);

        $modelHash = $this->modelHasher($tariff,null);
        $isHashChanged = $smTariff->hash === $modelHash ? false : true;
        $relatedTariff = $this->meterTariff->newQuery()->where('id',
            $smTariff->mpm_tariff_id)->first();
        if ($relatedTariff && $isHashChanged) {
            $this->updateRelatedTariff($tariff, $relatedTariff);
            $smTariff->update([
                'flat_load_limit' => array_key_exists("flat_load_limit",
                    $tariff) ? $tariff['flat_load_limit'] : $smTariff->flat_load_limit,
                'plan_duration' => array_key_exists("plan_duration",
                    $tariff) ? $tariff['plan_duration'] : $smTariff->plan_duration,
                'plan_price' => array_key_exists("plan_price",
                    $tariff) ? $tariff['plan_price'] : $smTariff->plan_price,
                'site_id' => $smTariff->site_id,
                'hash' => $modelHash,
            ]);
        }
        return $relatedTariff;
    }

    public function sync()
    {
        try {
            $syncCheck = $this->syncCheck(true);

            foreach ($syncCheck as $k => $check) {
                if ($k !== 'available_site_count') {
                    if (!$check['result']) {
                        $tariffs = $check['site_data'];

                        foreach ($tariffs as $tariff) {
                            if ($tariff['tariff_type'] == 'flat') {
                                $registeredSmTariff = $this->smTariff->newQuery()->where('tariff_id',
                                    $tariff['id'])->first();
                                $modelHash =  $this->modelHasher($tariff,null);
                                if ($registeredSmTariff) {
                                    $isHashChanged = $registeredSmTariff->hash === $modelHash ? false : true;
                                    $relatedTariff = $this->meterTariff->newQuery()->where('id',
                                        $registeredSmTariff->mpm_tariff_id)->first();
                                    if (!$relatedTariff) {
                                        $this->createRelatedTariff($tariff);
                                        $registeredSmTariff->update([
                                            'flat_load_limit' => array_key_exists("flat_load_limit",
                                                $tariff) ? $tariff['flat_load_limit'] : $registeredSmTariff->flat_load_limit,
                                            'plan_duration' => array_key_exists("plan_duration",
                                                $tariff) ? $tariff['plan_duration'] : $registeredSmTariff->plan_duration,
                                            'plan_price' => array_key_exists("plan_price",
                                                $tariff) ? $tariff['plan_price'] : $registeredSmTariff->plan_price,
                                            'site_id' => $check['site_id'],
                                            'hash' => $modelHash,
                                        ]);
                                    } else {
                                        if ($relatedTariff && $isHashChanged) {
                                            $this->updateRelatedTariff($tariff, $relatedTariff);
                                            $registeredSmTariff->update([
                                                'flat_load_limit' => array_key_exists("flat_load_limit",
                                                    $tariff) ? $tariff['flat_load_limit'] : $registeredSmTariff->flat_load_limit,
                                                'plan_duration' => array_key_exists("plan_duration",
                                                    $tariff) ? $tariff['plan_duration'] : $registeredSmTariff->plan_duration,
                                                'plan_price' => array_key_exists("plan_price",
                                                    $tariff) ? $tariff['plan_price'] : $registeredSmTariff->plan_price,
                                                'site_id' => $check['site_id'],
                                                'hash' => $modelHash,
                                            ]);
                                        } else {
                                            continue;
                                        }
                                    }
                                } else {
                                    $meterTariff = $this->createRelatedTariff($tariff);
                                    if (!$meterTariff) {
                                        continue;
                                    }
                                    $maxValue = $this->smMeterModel->newQuery()->max('continuous_limit');
                                    $this->smTariff->newQuery()->create([
                                        'tariff_id' => $tariff['id'],
                                        'mpm_tariff_id' => $meterTariff->id,
                                        'flat_load_limit' => array_key_exists("flat_load_limit",
                                            $tariff) ? $tariff['flat_load_limit'] : $maxValue,
                                        'plan_duration' => array_key_exists("plan_duration",
                                            $tariff) ? $tariff['plan_duration'] : null,
                                        'plan_price' => array_key_exists("plan_price",
                                            $tariff) ? $tariff['plan_price'] : 0,
                                        'site_id' => $check['site_id'],
                                        'hash' => $modelHash
                                    ]);
                                }

                            }
                        }

                    }

                }
            }

            return $this->smTariff->newQuery()->with([
                'mpmTariff',
                'site.mpmMiniGrid'
            ])->paginate(config('spark.paginate'));
        } catch (Exception $e) {
            Log::critical('Spark meter tariffs sync failed.', ['Error :' => $e->getMessage()]);
            throw  new Exception ($e->getMessage());
        }
    }

    public function syncCheck($returnData = false)
    {
        $returnArray = ['available_site_count' => 0];
        try {
            $sites = $this->smSite->newQuery()->where('is_authenticated', 1)->where('is_online', 1)->get();
            foreach ($sites as $key => $site) {
                $returnArray['available_site_count'] = $key + 1;
                $tariffs = $this->sparkMeterApiRequests->get($this->rootUrl, $site->site_id);
                $sparkTariffsCount = 0;
                foreach ($tariffs['tariffs'] as $model) {
                    if ($model['tariff_type'] == 'flat') {
                        $sparkTariffsCount++;
                    }
                }
                $smTariffs = $this->smTariff->newQuery()->where('site_id', $site->site_id)->get();
                $smTariffsCount = count($smTariffs);
                if ($sparkTariffsCount === $smTariffsCount) {
                    foreach ($tariffs['tariffs'] as $tariff) {

                        if ($tariff['tariff_type'] == 'flat') {

                            $registeredSmTariff = $this->smTariff->newQuery()->where('tariff_id',
                                $tariff['id'])->first();
                            if ($registeredSmTariff) {
                                $modelHash = $this->modelHasher($tariff,null);
                                $smHash = $registeredSmTariff->hash;
                                if ($modelHash !== $smHash) {
                                    break;
                                }
                                $sparkTariffsCount--;
                            } else {
                                break;
                            }
                        }
                    }
                    if ($sparkTariffsCount === 0) {
                        $returnData ? array_push($returnArray, [
                            'site_id' => $site->site_id,
                            'site_data' => $tariffs['tariffs'],
                            'result' => true
                        ]) : array_push($returnArray, ['result' => true]);
                    } else {
                        $returnData ? array_push($returnArray, [
                            'site_id' => $site->site_id,
                            'site_data' => $tariffs['tariffs'],
                            'result' => false
                        ]) : array_push($returnArray, ['result' => false]);
                    }

                } else {
                    $returnData ? array_push($returnArray, [
                        'site_id' => $site->site_id,
                        'site_data' => $tariffs['tariffs'],
                        'result' => false
                    ]) : array_push($returnArray, ['result' => false]);
                }
            }
            return $returnArray;
        } catch (Exception $e) {
            Log::critical('Spark meter tariffs sync-check failed.', ['Error :' => $e->getMessage()]);
            if ($returnData) {
                array_push($returnArray,
                    ['result' => false]);
                return $returnArray;
            }
            throw  new Exception ($e->getMessage());
        }
    }

    public function modelHasher($model,...$params): string
    {
        $modelTouString = '';
        foreach ($model['tous'] as $item) {
            $modelTouString .= $item['start'] . $item['end'] . doubleval($item['value']);
        }
        return $this->smTableEncryption->makeHash([
            $model['name'],
            (int)$model['flat_price'],
            $modelTouString
        ]);
    }

    public function syncCheckBySite($siteId)
    {
        try {
            $tariffs = $this->sparkMeterApiRequests->get($this->rootUrl, $siteId);
            $sparkTariffsCount = 0;
            foreach ($tariffs['tariffs'] as $model) {
                if ($model['tariff_type'] == 'flat') {
                    $sparkTariffsCount++;
                }
            }
            $smTariffs = $this->smTariff->newQuery()->where('site_id', $siteId)->get();
            $smTariffsCount = count($smTariffs);
            if ($sparkTariffsCount === $smTariffsCount) {
                foreach ($tariffs['tariffs'] as $tariff) {

                    if ($tariff['tariff_type'] == 'flat') {

                        $registeredSmTariff = $this->smTariff->newQuery()->where('tariff_id',
                            $tariff['id'])->first();
                        if ($registeredSmTariff) {
                            $modelHash = $this->modelHasher($tariff,null);
                            $smHash = $registeredSmTariff->hash;
                            if ($modelHash !== $smHash) {
                                break;
                            }
                            $sparkTariffsCount--;
                        } else {
                            break;
                        }
                    }
                }
                if ($sparkTariffsCount === 0) {
                    return  ['result' => true,'message' => 'Records are updated'];
                } else {
                    return  ['result' => false,'message'=>'tariffs are not updated for site '.$siteId];
                }

            } else {
                return  ['result' => false,'message'=>'tariffs are not updated for site '.$siteId];
            }
        } catch (Exception $e) {

            Log::critical('Spark meter tariffs sync-check-by-site failed.', ['Error :' => $e->getMessage()]);
            throw  new Exception ($e->getMessage());
        }

    }
}
