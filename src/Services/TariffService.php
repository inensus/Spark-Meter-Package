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
use Inensus\SparkMeter\Models\SmTariff;
use function GuzzleHttp\Promise\queue;

class TariffService implements ISynchronizeService
{

    private $sparkMeterApiRequests;
    private $rootUrl = '/tariffs';
    private $smTableEncryption;

    public function __construct(SparkMeterApiRequests $sparkMeterApiRequests, SmTableEncryption $smTableEncryption)
    {
        $this->sparkMeterApiRequests = $sparkMeterApiRequests;
        $this->smTableEncryption = $smTableEncryption;
    }

    public function createSmTariff($tariff)
    {
        $touEnabled = count($tariff->tou) > 0;
        $maxValue = SmMeterModel::max('continuous_limit');
        $tous = [];
        $sparkTariffs = $this->sparkMeterApiRequests->get($this->rootUrl);
        $tariffExists = false;
        foreach ($sparkTariffs['tariffs'] as $key => $value) {
            if ($value['name'] === $tariff->name) {
                $tariffExists = true;
                $modelTouString = '';
                foreach ($value['tous'] as $item) {
                    $modelTouString .= $item['start'] . $item['end'] . doubleval($item['value']);
                }
                $modelHash = $this->smTableEncryption->makeHash([
                    $value['name'],
                    (int)$value['flat_price'],
                    $modelTouString
                ]);
                SmTariff::create([
                    'tariff_id' => $value['id'],
                    'mpm_tariff_id' => $tariff->id,
                    'flat_load_limit' => $maxValue,
                    'plan_duration' => '1m',
                    'plan_price' => 0,
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

            $accessRate = AccessRate::where('tariff_id', $tariff->id)->first();
            $planEnabled = false;
            $planDuration = '1m';
            if ($accessRate) {
                $planDuration = $accessRate->period < 30 ? '1d' : '1m';
                $planEnabled = true;
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
                'plan_fixed_fee' => 0
            ];
            $result = $this->sparkMeterApiRequests->post($this->rootUrl, $postParams);
            $modelHash = $this->smTableEncryption->makeHash([
                $tariff->name,
                (int)$tariff->price,
                $modelTouString
            ]);
            SmTariff::create([
                'tariff_id' => $result['tariff']['id'],
                'mpm_tariff_id' => $tariff->id,
                'flat_load_limit' => $maxValue,
                'plan_duration' => '1m',
                'plan_price' => 0,
                'hash' => $modelHash
            ]);
        }

    }

    public function getSmTariffs($request)
    {
        $perPage = $request->input('per_page') ?? 15;
        return SmTariff::with('mpmTariff')->paginate($perPage);
    }

    public function getSmTariffsCount()
    {

        return count(SmTariff::query()->get());
    }

    public function createRelatedTariff($model)
    {
        $meterTariff = MeterTariff::create([
            'name' => $model['name'],
            'price' => $model['flat_price'] * 100,
            'currency' => config('spark.currency'),
            'total_price' => $model['flat_price'] * 100,
        ]);
        foreach ($model['tous'] as $key => $tou) {
            TimeOfUsage::create([
                'tariff_id' => $meterTariff->id,
                'start' => $tou['start'],
                'end' => $tou['end'],
                'value' => doubleval($tou['value'])
            ]);
        }
        if ($model['plan_enabled'] && $model['plan_fixed_fee'] > 0) {
            Log::debug('create',
                ['relatedtarfii'=>$model]);
            $this->setAccessRate($model,$meterTariff->id,$model['plan_enabled']);
            $this->updateSparkTariffInfo($model);
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
                TimeOfUsage::create([
                    'tariff_id' => $tariff->id,
                    'start' => $tou['start'],
                    'end' => $tou['end'],
                    'value' => doubleval($tou['value']),
                ]);
            }
        }
        if ($model['plan_enabled'] && $model['plan_fixed_fee'] > 0) {

            $this->setAccessRate($model,$tariff->id,$model['plan_enabled']);
            $this->updateSparkTariffInfo($model);
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
    private function setAccessRate($model,$tariffId,$planEnabled){
        $accessRate = AccessRate::where('tariff_id', $tariffId)->first();
        if ($accessRate) {
            if ($planEnabled){
                $accessRate->update([
                    'tariff_id' => $tariffId,
                    'amount' => $model['plan_fixed_fee'],
                    'period' => $model['plan_duration'] === '1m' ? 30 : 1,
                ]);
            }else{
                $accessRate->delete();
            }
        }else{
            if ($planEnabled){
                AccessRate::create([
                    'tariff_id' => $tariffId,
                    'amount' => $model['plan_fixed_fee'],
                    'period' => $model['plan_duration'] === '1m' ? 30 : 1,
                ]);
            }
        }

    }
    public function getSparkTariffInfo($tariffId)
    {
        try {
            $sparkTariff = $this->sparkMeterApiRequests->getInfo('/tariff/', $tariffId);

            $smTariff = SmTariff::with(['mpmTariff.accessRate'])->whereHas('mpmTariff.accessRate', function ($q) {
                $q->whereNotNull('id');
            })->where('tariff_id', $tariffId)->first();
            if ($smTariff) {
                $sparkTariff['tariff']['access_rate_amount'] = $smTariff->mpmTariff->accessRate->amount;
                $sparkTariff['tariff']['access_rate_period'] = $smTariff->mpmTariff->accessRate->period;
            }
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
                'plan_duration' => $tariffData['plan_duration'],
                'plan_price' => $tariffData['plan_price'],
                'plan_fixed_fee' => 0
            );

            $sparkTariff = $this->sparkMeterApiRequests->put('/tariff/' . $tariffId, $putParams);

            if (array_key_exists("planFixedFee",$tariffData)) {
                $smTariff =SmTariff::query()->where('tariff_id', $tariffId)->first();
                $tariffData['plan_fixed_fee']=$tariffData['planFixedFee'];
                $this->setAccessRate($tariffData,$smTariff->mpm_tariff_id,$tariffData['plan_enabled']);
            }
            return $sparkTariff['tariff'];
        } catch (Exception $e) {

            Log::critical('updating tariff info from spark api failed.',
                ['Error :' => $e->getMessage(), 'data :' => $tariffData]);
            throw  new Exception ($e->getMessage());
        }

    }

    public function sync()
    {
        try {
            $syncCheck = $this->syncCheck(true);
            if (!$syncCheck['result']) {
                $tariffs = $syncCheck['data'];

                foreach ($tariffs as $key => $model) {
                    if ($model['tariff_type'] == 'flat') {
                        $registeredSmTariff = SmTariff::where('tariff_id', $model['id'])->first();

                        $modelTouString = '';
                        foreach ($model['tous'] as $item) {
                            $modelTouString .= $item['start'] . $item['end'] . doubleval($item['value']);
                        }
                        $modelHash = $this->smTableEncryption->makeHash([
                            $model['name'],
                            (int)$model['flat_price'],
                            $modelTouString
                        ]);
                        if ($registeredSmTariff) {
                            $isHashChanged = $registeredSmTariff->hash === $modelHash ? false : true;
                            $relatedTariff = MeterTariff::where('id', $registeredSmTariff->mpm_tariff_id)->first();
                            if (!$relatedTariff) {
                                $this->createRelatedTariff($model);
                                $registeredSmTariff->update([
                                    'flat_load_limit' => array_key_exists("flat_load_limit",
                                        $model) ? $model['flat_load_limit'] : $registeredSmTariff->flat_load_limit,
                                    'plan_duration' => array_key_exists("plan_duration",
                                        $model) ? $model['plan_duration'] : $registeredSmTariff->plan_duration,
                                    'plan_price' => array_key_exists("plan_price",
                                        $model) ? $model['plan_price'] : $registeredSmTariff->plan_price,
                                    'hash' => $modelHash,
                                ]);
                            }else if ($relatedTariff && $isHashChanged) {
                                $this->updateRelatedTariff($model, $relatedTariff);
                                $registeredSmTariff->update([
                                    'flat_load_limit' => array_key_exists("flat_load_limit",
                                        $model) ? $model['flat_load_limit'] : $registeredSmTariff->flat_load_limit,
                                    'plan_duration' => array_key_exists("plan_duration",
                                        $model) ? $model['plan_duration'] : $registeredSmTariff->plan_duration,
                                    'plan_price' => array_key_exists("plan_price",
                                        $model) ? $model['plan_price'] : $registeredSmTariff->plan_price,
                                    'hash' => $modelHash,
                                ]);
                            }else{
                                continue;
                            }
                        }
                        else {
                            $meterTariff = $this->createRelatedTariff($model);
                            if (!$meterTariff) {
                                continue;
                            }
                            $maxValue = SmMeterModel::max('continuous_limit');
                            SmTariff::create([
                                'tariff_id' => $model['id'],
                                'mpm_tariff_id' => $meterTariff->id,
                                'flat_load_limit' => array_key_exists("flat_load_limit",
                                    $model) ? $model['flat_load_limit'] : $maxValue,
                                'plan_duration' => array_key_exists("plan_duration",
                                    $model) ? $model['plan_duration'] : null,
                                'plan_price' => array_key_exists("plan_price",
                                    $model) ? $model['plan_price'] : 0,
                                'hash' => $modelHash
                            ]);
                        }

                    }
                }

            }
            return SmTariff::with('mpmTariff')->paginate(config('spark.paginate'));
        } catch (Exception $e) {
            Log::critical('Spark meter tariffs sync failed.', ['Error :' => $e->getMessage()]);
            throw  new Exception ($e->getMessage());
        }
    }

    public function syncCheck($returnData = false)
    {
        try {
            $tariffs = $this->sparkMeterApiRequests->get($this->rootUrl);
            $sparkTariffsCount = 0;
            foreach ($tariffs['tariffs'] as $key => $model) {
                if ($model['tariff_type'] == 'flat') {
                    $sparkTariffsCount++;
                }
            }
            $smTariffs = SmTariff::get();
            $smTariffsCount = count($smTariffs);
            if ($sparkTariffsCount === $smTariffsCount) {
                foreach ($tariffs['tariffs'] as $key => $model) {

                    if ($model['tariff_type'] == 'flat' && $model['plan_fixed_fee'] == 0) {

                        $registeredSmTariff = SmTariff::where('tariff_id', $model['id'])->first();
                        if ($registeredSmTariff) {
                            $modelTouString = '';
                            foreach ($model['tous'] as $item) {
                                $modelTouString .= $item['start'] . $item['end'] . $item['value'];
                            }
                            $modelHash = $this->smTableEncryption->makeHash([
                                $model['name'],
                                (int)$model['flat_price'],
                                $modelTouString
                            ]);

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
                    return $returnData ? ['data' => $tariffs['tariffs'], 'result' => true] : ['result' => true];
                }
                return $returnData ? ['data' => $tariffs['tariffs'], 'result' => false] : ['result' => false];
            }
            return $returnData ? ['data' => $tariffs['tariffs'], 'result' => false] : ['result' => false];
        } catch (Exception $e) {
            Log::critical('Spark meter tariffs sync-check failed.', ['Error :' => $e->getMessage()]);
            if ($returnData) {
                return ['result' => false];
            }
            throw  new Exception ($e->getMessage());
        }
    }
}
