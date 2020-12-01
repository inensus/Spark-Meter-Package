<?php

namespace Inensus\SparkMeter\Console\Commands;

use Illuminate\Console\Command;
use Inensus\SparkMeter\Helpers\InsertSparkMeterApi;
use Inensus\SparkMeter\Services\CredentialService;
use Inensus\SparkMeter\Services\CustomerService;
use Inensus\SparkMeter\Services\MeterModelService;
use Inensus\SparkMeter\Services\MenuItemService;

class InstallSparkMeterPackage extends Command
{
    protected $signature = 'spark-meter:install';
    protected $description = 'Install the Spark Meter Integration Package';

    private $insertSparkMeterApi;
    private $meterModelService;
    private $credentialService;
    private $menuItemService;
    private $customerService;

    /**
     * Create a new command instance.
     *
     * @param InsertSparkMeterApi $insertSparkMeterApi
     * @param MeterModelService $meterModelService
     * @param CredentialService $credentialService
     * @param MenuItemService $menuItemService
     * @param CustomerService $customerService
     */
    public function __construct(
        InsertSparkMeterApi $insertSparkMeterApi,
        MeterModelService $meterModelService,
        CredentialService $credentialService,
        MenuItemService $menuItemService,
        CustomerService $customerService
    ) {
        parent::__construct();
        $this->insertSparkMeterApi = $insertSparkMeterApi;
        $this->meterModelService = $meterModelService;
        $this->credentialService=$credentialService;
        $this->menuItemService=$menuItemService;
        $this->customerService=$customerService;
    }

    public function handle(): void
    {
        $this->info('Installing Spark Meter Integration Package\n');

        $this->info('Copying migrations\n');
        $this->call('vendor:publish', [
             '--provider' => "Inensus\SparkMeter\Providers\SparkMeterServiceProvider",
             '--tag' => "migrations"
         ]);

         $this->info('Creating database tables\n');
         $this->call('migrate');

        $this->info('Copying vue files\n');
        $this->call('vendor:publish', [
            '--provider' => "Inensus\SparkMeter\Providers\SparkMeterServiceProvider",
            '--tag' => "vue-components"
        ]);

        $this->insertSparkMeterApi->registerSparkMeterManufacturer();
        $this->credentialService->createSmCredentials();

        $this->call('plugin:add', [
            'name' => "SparkMeter",
            'composer_name' => "inensus/spark-meter",
            'description' => "Spark meters integration package for MicroPowerManager",
        ]);
        $this->call('routes:generate');

        $menuItems = $this->menuItemService->createMenuItems();
        $this->call('menu-items:generate', [
            'menuItem' => $menuItems['menuItem'],
            'subMenuItems' => $menuItems['subMenuItems'],
        ]);

        $this->call('sidebar:generate');

        $this->info('Package installed successfully..');

        $connections=$this->customerService->checkConnectionAvailability();
        if(!$this->customerService->checkLocationAvailability()){
            $this->warn('------------------------------');
            $this->warn("Spark Meter package needs least one registered Cluster and one MiniGrid related it.");
            $this->warn("If you have no Cluster, please navigate to #Locations# section and register your locations.");
        }
        if(!$connections['type']||!$connections['group']){
            $this->warn('------------------------------');
            $this->warn("Spark Meter package needs least one Connection Group and one Connection Type.");
            $this->warn("Before you get Customers from Spark Meter please check them in #Connection# section.");
        }


    }
}
