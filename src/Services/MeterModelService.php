<?php

namespace Inensus\SparkMeter\Services;

use App\Models\Meter\MeterType;
use Exception;
use Illuminate\Support\Facades\Log;
use Inensus\SparkMeter\Helpers\SmTableEncryption;
use Inensus\SparkMeter\Http\Requests\SparkMeterApiRequests;
use Inensus\SparkMeter\Models\SmMeterModel;
use Inensus\SparkMeter\Models\SmSite;

class MeterModelService implements ISynchronizeService
{
    private $sparkMeterApiRequests;
    private $rootUrl = '/meters';
    private $smTableEncryption;
    private $smMeterModel;
    private $smSite;
    private $meterType;

    public function __construct(

        SparkMeterApiRequests $sparkMeterApiRequests,
        SmTableEncryption $smTableEncryption,
        SmMeterModel $smMeterModel,
        SmSite $smSite,
        MeterType $meterType

    ) {

        $this->sparkMeterApiRequests = $sparkMeterApiRequests;
        $this->smTableEncryption = $smTableEncryption;
        $this->smMeterModel = $smMeterModel;
        $this->smSite = $smSite;
        $this->meterType = $meterType;
    }

    public function getSmMeterModels($request)
    {
        $perPage = $request->input('per_page') ?? 15;
        return $this->smMeterModel->newQuery()->with(['meterType', 'site.mpmMiniGrid'])->paginate($perPage);
    }

    public function getSmMeterModelsCount()
    {
        return count($this->smMeterModel->newQuery()->get());
    }

    public function sync()
    {
        try {
            $syncCheck = $this->syncCheck(true);

            foreach ($syncCheck as $k => $check) {
                if ($k !== 'available_site_count') {
                    if (!$check['result']) {
                        $models = $check['site_data'];
                        foreach ($models as $key => $model) {
                            $registeredSmMeterModel = $this->smMeterModel->newQuery()->where('model_name',
                                $model['name'])->first();
                                $smModelHash = $this->modelHasher($model,null);
                            if ($registeredSmMeterModel) {
                                $isHashChanged = $registeredSmMeterModel->hash === $smModelHash ? false : true;

                                $relatedMeterType = $this->meterType->newQuery()->where('id',
                                    $registeredSmMeterModel->mpm_meter_type_id)->first();
                                if (!$relatedMeterType) {
                                    $this->meterType->newQuery()->create([
                                        'online' => 1,
                                        'phase' => $model['phase_count'],
                                        'max_current' => $model['continuous_limit'],
                                    ]);
                                    $registeredSmMeterModel->update([
                                        'model_name' => $model['name'],
                                        'continuous_limit' => $model['continuous_limit'],
                                        'inrush_limit' => $model['inrush_limit'],
                                        'site_id' => $check['site_id'],
                                        'hash' => $smModelHash,
                                    ]);
                                } else {
                                    if ($relatedMeterType && $isHashChanged) {
                                        $relatedMeterType->update([
                                            'phase' => $model['phase_count'],
                                            'max_current' => $model['continuous_limit'],
                                        ]);
                                        $registeredSmMeterModel->update([
                                            'model_name' => $model['name'],
                                            'continuous_limit' => $model['continuous_limit'],
                                            'inrush_limit' => $model['inrush_limit'],
                                            'site_id' => $check['site_id'],
                                            'hash' => $smModelHash,
                                        ]);
                                    } else {
                                        continue;
                                    }
                                }
                            } else {
                                $meterType = $this->meterType->newQuery()->create([
                                    'online' => 1,
                                    'phase' => $model['phase_count'],
                                    'max_current' => $model['continuous_limit']
                                ]);
                                $this->smMeterModel->newQuery()->create([
                                    'model_name' => $model['name'],
                                    'mpm_meter_type_id' => $meterType->id,
                                    'continuous_limit' => $model['continuous_limit'],
                                    'inrush_limit' => $model['inrush_limit'],
                                    'site_id' => $check['site_id'],
                                    'hash' => $smModelHash
                                ]);
                            }
                        }
                    }
                }
            }

            return $this->smMeterModel->newQuery()->with(['meterType','site.mpmMiniGrid'])->paginate(config('paginate.paginate'));

        } catch (Exception $e) {
            Log::critical('Spark meter models sync failed.', ['Error :' => $e->getMessage()]);
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
                $url = $this->rootUrl . '/models';
                $sparkMeterModels = $this->sparkMeterApiRequests->get($url, $site->site_id);
                $sparkMeterModelsCount = count($sparkMeterModels['models']);
                $smMeterModels = $this->smMeterModel->newQuery()->where('site_id', $site->site_id)->get();
                $smMeterModelsCount = count($smMeterModels);

                if ($sparkMeterModelsCount === $smMeterModelsCount) {
                    foreach ($sparkMeterModels['models'] as $model) {
                        $registeredSmMeterModel = $this->smMeterModel->newQuery()->where('model_name',
                            $model['name'])->first();
                        if ($registeredSmMeterModel) {
                            $modelHash = $this->modelHasher($model,null);
                            $smHash = $registeredSmMeterModel->hash;
                            if ($modelHash !== $smHash) {

                                break;
                            } else {
                                $sparkMeterModelsCount--;
                            }
                        } else {
                            break;
                        }
                    }
                    if ($sparkMeterModelsCount === 0) {
                        $returnData ? array_push($returnArray, [
                            'site_id' => $site->site_id,
                            'site_data' => $sparkMeterModels['models'],
                            'result' => true
                        ]) : array_push($returnArray, ['result' => true]);

                    }
                    else {

                        $returnData ? array_push($returnArray, [
                            'site_id' => $site->site_id,
                            'site_data' => $sparkMeterModels['models'],
                            'result' => false
                        ]) : array_push($returnArray, ['result' => false]);
                    }
                }else{
                    $returnData ? array_push($returnArray, [
                        'site_id' => $site->site_id,
                        'site_data' => $sparkMeterModels['models'],
                        'result' => false
                    ]) : array_push($returnArray, ['result' => false]);
                }
            }
            return $returnArray;
        } catch (Exception $e) {

            Log::critical('Spark meter meter-models sync-check failed.', ['Error :' => $e->getMessage()]);
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
        return $smModelHash = $this->smTableEncryption->makeHash([
            $model['name'],
            $model['phase_count'],
            $model['continuous_limit'],
            $model['inrush_limit']
        ]);
    }

    public function syncCheckBySite($siteId)
    {
        try {
                $url = $this->rootUrl . '/models';
                $sparkMeterModels = $this->sparkMeterApiRequests->get($url, $siteId);
                $sparkMeterModelsCount = count($sparkMeterModels['models']);
                $smMeterModels = $this->smMeterModel->newQuery()->where('site_id',$siteId)->get();
                $smMeterModelsCount = count($smMeterModels);
                if ($sparkMeterModelsCount === $smMeterModelsCount) {
                    foreach ($sparkMeterModels['models'] as $model) {
                        $registeredSmMeterModel = $this->smMeterModel->newQuery()->where('model_name',
                            $model['name'])->first();
                        if ($registeredSmMeterModel) {
                            $modelHash = $this->modelHasher($model,null);
                            $smHash = $registeredSmMeterModel->hash;
                            if ($modelHash !== $smHash) {

                                break;
                            } else {
                                $sparkMeterModelsCount--;
                            }
                        } else {
                            break;
                        }
                    }
                    if ($sparkMeterModelsCount === 0) {
                        return  ['result' => true,'message' => 'Records are updated'];
                    }
                    else {
                        return  ['result' => false,'message'=>'meter models are not updated for site '.$siteId];
                    }
                }else{
                    return  ['result' => false,'message'=>'meter models are not updated for site '.$siteId];
                }

        } catch (Exception $e) {

            Log::critical('Spark meter meter-models sync-check-by-site failed.', ['Error :' => $e->getMessage()]);
            throw  new Exception ($e->getMessage());
        }
    }
}
