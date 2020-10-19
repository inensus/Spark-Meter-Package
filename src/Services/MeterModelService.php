<?php
namespace Inensus\SparkMeter\Services;

use App\Models\Meter\MeterType;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Inensus\SparkMeter\Helpers\SmTableEncryption;
use Inensus\SparkMeter\Http\Requests\SparkMeterApiRequests;

use Inensus\SparkMeter\Models\SmCredential;
use Inensus\SparkMeter\Models\SmMeterModel;

class MeterModelService implements ISynchronizeService
{
    private $sparkMeterApiRequests;
    private $rootUrl = '/meters';
    private $smTableEncryption;

    public function __construct(SparkMeterApiRequests $sparkMeterApiRequests, SmTableEncryption $smTableEncryption)
    {
        $this->sparkMeterApiRequests = $sparkMeterApiRequests;
        $this->smTableEncryption = $smTableEncryption;
    }

    public function getSmMeterModels($request)
    {
        $perPage = $request->input('per_page') ?? 15;
        return SmMeterModel::with('meterType')->paginate($perPage);
    }
    public function getSmMeterModelsCount()
    {
        return count(SmMeterModel::query()->get());
    }
    public function sync()
    {
        try {
            $syncCheck = $this->syncCheck(true);
            if (!$syncCheck['result']) {
                $models = $syncCheck['data'];

                foreach ($models as $key => $model) {
                    $registeredSmMeterModel = SmMeterModel::where('model_name', $model['name'])->first();
                    $smModelHash = $this->smTableEncryption->makeHash([$model['name'],$model['phase_count'],$model['continuous_limit'],$model['inrush_limit']]);
                    if ($registeredSmMeterModel) {
                        $isHashChanged = $registeredSmMeterModel->hash===$smModelHash?false:true;

                        $relatedMeterType = MeterType::where('id', $registeredSmMeterModel->mpm_meter_type_id)->first();
                        if (!$relatedMeterType) {
                            MeterType::create([
                                'online' => 1,
                                'phase' => $model['phase_count'],
                                'max_current' => $model['continuous_limit'],
                            ]);
                            $registeredSmMeterModel->update([
                                'model_name'=>$model['name'],
                                'continuous_limit'=>$model['continuous_limit'],
                                'inrush_limit'=>$model['inrush_limit'],
                                'hash'=>$smModelHash,
                            ]);
                        } else if($relatedMeterType && $isHashChanged){
                            $relatedMeterType->update([
                                'phase' => $model['phase_count'],
                                'max_current' => $model['continuous_limit'],
                            ]);
                            $registeredSmMeterModel->update([
                                'model_name'=>$model['name'],
                                'continuous_limit'=>$model['continuous_limit'],
                                'inrush_limit'=>$model['inrush_limit'],
                                'hash'=>$smModelHash,
                            ]);
                        }else{
                            continue;
                        }
                    } else {
                        $meterType = MeterType::create([
                            'online' => 1,
                            'phase' => $model['phase_count'],
                            'max_current' => $model['continuous_limit']
                        ]);
                        SmMeterModel::create([
                            'model_name' => $model['name'],
                            'mpm_meter_type_id' => $meterType->id,
                            'continuous_limit' => $model['continuous_limit'],
                            'inrush_limit' => $model['inrush_limit'],
                            'hash'=>$smModelHash
                        ]);
                    }
                }
            }
            return SmMeterModel::with('meterType')->paginate(config('paginate.paginate'));

        } catch (Exception $e) {
            Log::critical('Spark meter models sync failed.', ['Error :' => $e->getMessage()]);
            throw  new Exception ($e->getMessage());
        }
    }

    public function syncCheck($returnData=false)
    {
        try {
            $url = $this->rootUrl . '/models';
            $models = $this->sparkMeterApiRequests->get($url);
            $sparkMeterModelsCount = count($models['models']);
            $smMeterModels = SmMeterModel::get();
            $smMeterModelsCount = count($smMeterModels);
            if ($sparkMeterModelsCount === $smMeterModelsCount) {
                foreach ($models['models'] as $key => $model) {
                    $registeredSmMeterModel = SmMeterModel::where('model_name', $model['name'])->first();
                    if ($registeredSmMeterModel) {
                        $modelHash = $this->smTableEncryption->makeHash([
                            $model['name'],
                            $model['phase_count'],
                            $model['continuous_limit'],
                            $model['inrush_limit']
                        ]);
                        $smHash = $registeredSmMeterModel->hash;
                        if ($modelHash !== $smHash) {

                            break;
                        }else{
                            $sparkMeterModelsCount--;
                        }
                    }else{
                        break;
                    }
                }
                if ($sparkMeterModelsCount === 0) {
                        return $returnData?['data'=>$models['models'],'result' => true]:['result' => true];
                }
                return $returnData?['data'=>$models['models'],'result' => false]:['result' => false];
            }
            return $returnData?['data'=>$models['models'],'result' => false]:['result' => false];

        } catch (Exception $e) {
            if ($returnData){
                return  ['result' => false];
            }
            throw  new Exception ($e->getMessage());
        }
    }
}
