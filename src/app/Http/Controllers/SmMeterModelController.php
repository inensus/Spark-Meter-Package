<?php


namespace Inensus\SparkMeter\app\Http\Controllers;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inensus\SparkMeter\app\Services\MeterModelService;


class SmMeterModelController extends Controller implements IBaseController
{
    private $meterModelService;

    public function __construct(MeterModelService $meterModelService)
    {
        $this->meterModelService = $meterModelService;
    }

    public function index(Request $request): ApiResource
    {
        $meterModels = $this->meterModelService->getSmMeterModels($request);
        return new ApiResource($meterModels);
    }

    public function sync(): ApiResource
    {
        return new ApiResource($this->meterModelService->sync());
    }
    public function checkSync(): ApiResource
    {
        return new ApiResource($this->meterModelService->syncCheck());
    }
    public function count()
    {
        return  $this->meterModelService->getSmMeterModelsCount() ;
    }
}
