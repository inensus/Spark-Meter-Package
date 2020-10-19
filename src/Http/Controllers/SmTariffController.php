<?php
namespace Inensus\SparkMeter\Http\Controllers;
use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inensus\SparkMeter\Http\Requests\SmTariffRequest;
use Inensus\SparkMeter\Services\TariffService;

class SmTariffController  extends Controller implements IBaseController
{
    private $tariffService;

    public function __construct(TariffService $tariffService)
    {
        $this->tariffService = $tariffService;
    }

    public function index(Request $request): ApiResource
    {
        return new ApiResource($this->tariffService->getSmTariffs($request));
    }

    public function getInfo($tariffId): ApiResource
    {
        return new ApiResource($this->tariffService->getSparkTariffInfo($tariffId));
    }

    public function updateInfo(SmTariffRequest $request)
    {
        $tariffData=[
            'id'=>$request->input('id'),
            'name'=>$request->input('name'),
            'flat_price'=> $request->input('flatPrice'),
            'flat_load_limit'=>$request->input('flatLoadLimit'),
            'daily_energy_limit_enabled' => $request->input('dailyEnergyLimitEnabled'),
            'daily_energy_limit_value' => $request->input('dailyEnergyLimitValue'),
            'daily_energy_limit_reset_hour' =>$request->input('dailyEnergyLimitResetHour'),
            'tou_enabled' =>$request->input('touEnabled'),
            'tous' =>$request->input('tous'),
            'plan_enabled' => $request->input('planEnabled'),
            'plan_duration' => $request->input('planDuration'),
            'plan_price' => $request->input('planPrice'),
            'planFixedFee'=>$request->input('planFixedFee')
        ];

        return new ApiResource($this->tariffService->updateSparkTariffInfo($tariffData));

    }

    public function sync(): ApiResource
    {
        return new ApiResource($this->tariffService->sync());
    }

    public function checkSync(): ApiResource
    {
        return new ApiResource($this->tariffService->syncCheck());
    }
    public function count()
    {
        return  $this->tariffService->getSmTariffsCount() ;
    }
}
