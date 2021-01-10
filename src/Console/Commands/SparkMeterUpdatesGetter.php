<?php


namespace Inensus\SparkMeter\Console\Commands;


use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Inensus\SparkMeter\Exceptions\CronJobException;
use Inensus\SparkMeter\Services\CustomerService;
use Inensus\SparkMeter\Services\MeterModelService;
use Inensus\SparkMeter\Services\SiteService;
use Inensus\SparkMeter\Services\TariffService;

class SparkMeterUpdatesGetter extends Command
{
    protected $signature = 'spark-meter:updatesGetter';
    protected $description = 'Gets updates from Spark Meter.';

    private $smSiteService;
    private $smMeterModelService;
    private $smTariffService;
    private $smCustomerService;

    public function __construct(
        SiteService $smSiteService,
        MeterModelService $smMeterModelService,
        TariffService $smTariffService,
        CustomerService $smCustomerService
    ) {
        parent::__construct();
        $this->smSiteService = $smSiteService;
        $this->smMeterModelService = $smMeterModelService;
        $this->smTariffService = $smTariffService;
        $this->smCustomerService = $smCustomerService;
    }

    public function handle(): void
    {
        try {
             $this->smSiteService->sync();

             $this->smMeterModelService->sync();

             $this->smTariffService->sync();

             $this->smCustomerService->sync();

        }catch (CronJobException $e){
            Log::critical('spark-meter:updates-getter failed.' ,['message'=>$e->getMessage()]);
        }
    }
}