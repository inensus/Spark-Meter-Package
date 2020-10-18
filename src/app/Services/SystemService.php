<?php


namespace Inensus\SparkMeter\app\Services;


use Exception;
use Illuminate\Support\Facades\Log;
use Inensus\SparkMeter\app\Helpers\SmTableEncryption;
use Inensus\SparkMeter\app\Http\Requests\SparkMeterApiRequests;
use Inensus\SparkMeter\Models\SmCredential;
use Inensus\SparkMeter\Models\SmGrid;

class SystemService
{
    private $sparkMeterApiRequests;
    private $rootUrl = '/system-info';
    private $smTableEncryption;
    public function __construct(SparkMeterApiRequests $sparkMeterApiRequests,SmTableEncryption $smTableEncryption)
    {
        $this->sparkMeterApiRequests = $sparkMeterApiRequests;
        $this->smTableEncryption=$smTableEncryption;
    }

    public function createSystem()
    {
        try {
            $models = $this->sparkMeterApiRequests->get($this->rootUrl);

            foreach ($models['grids'] as $key => $model) {
                $grid = SmGrid::query()->where('grid_id',$model['id'])->first();
                $hash = $this->smTableEncryption->makeHash([$model['id'],$model['name'],$model['serial']]);
                if (!$grid){
                    SmGrid::create([
                        'grid_id' => $model['id'],
                        'grid_name' => $model['name'],
                        'grid_serial' => $model['serial'],
                        'hash'=>$hash
                    ]);
                }

            }
        } catch (Exception $e) {
            Log::critical('Spark meter grid insertion failed.', ['Error :' => $e->getMessage()]);
            throw new Exception ($e->getMessage());
        }
    }

    public function getSystemInfo(){
        try {
            $models = $this->sparkMeterApiRequests->get($this->rootUrl);
            return $models['grids'];

        } catch (Exception $e) {
            Log::critical('Spark meter system info checking failed.', ['Error :' => $e->getMessage()]);
            throw new Exception ($e->getMessage());
        }
    }
}
