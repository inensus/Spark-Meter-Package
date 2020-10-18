<?php

namespace Inensus\SparkMeter\app\Console\Commands;

use Illuminate\Console\Command;
use Inensus\SparkMeter\app\Helpers\InsertSparkMeterApi;
use Inensus\SparkMeter\app\Services\CredentialService;
use Inensus\SparkMeter\app\Services\MeterModelService;
use Inensus\SparkMeter\app\Services\SystemService;
use Inensus\SparkMeter\Models\SmCredential;

class InstallSparkMeterPackage extends Command
{
    protected $signature = 'spark-meter:install';
    protected $description = 'Install the Spark Meter Integration Package';

    private $insertSparkMeterApi;
    private $meterModelService;
    private $credentialService;

    /**
     * Create a new command instance.
     *
     * @param InsertSparkMeterApi $insertSparkMeterApi
     * @param MeterModelService $meterModelService
     * @param CredentialService $credentialService
     */
    public function __construct(
        InsertSparkMeterApi $insertSparkMeterApi,
        MeterModelService $meterModelService,
        CredentialService $credentialService
    ) {
        parent::__construct();
        $this->insertSparkMeterApi = $insertSparkMeterApi;
        $this->meterModelService = $meterModelService;
        $this->credentialService=$credentialService;
    }

    public function handle(): void
    {
        $this->info('Installing Spark Meter Integration Package\n');

        $this->info('Copying migrations\n');
        $this->call('vendor:publish', [
             '--provider' => "Inensus\SparkMeter\providers\SparkMeterServiceProvider",
             '--tag' => "migrations"
         ]);

         $this->info('Creating database tables\n');
         $this->call('migrate');

        $this->info('Copying vue files\n');
        $this->call('vendor:publish', [
            '--provider' => "Inensus\SparkMeter\providers\SparkMeterServiceProvider",
            '--tag' => "vue-components"
        ]);

        $this->insertSparkMeterApi->registerSparkMeterManufacturer();
        $this->credentialService->createSmCredentials();
        $this->info('Package installed successfully..');
    }
}
