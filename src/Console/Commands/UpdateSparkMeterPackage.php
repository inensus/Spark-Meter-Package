<?php


namespace Inensus\SparkMeter\Console\Commands;


use Illuminate\Console\Command;
use Inensus\SparkMeter\Helpers\InsertSparkMeterApi;
use Inensus\SparkMeter\Services\CredentialService;
use Inensus\SparkMeter\Services\CustomerService;
use Inensus\SparkMeter\Services\MenuItemService;
use Inensus\SparkMeter\Services\MeterModelService;
use Inensus\SparkMeter\Services\PackageInstallationService;
use Inensus\SparkMeter\Services\SiteService;
use Inensus\SparkMeter\Services\SmSmsBodyService;
use Inensus\SparkMeter\Services\SmSmsFeedbackWordService;
use Inensus\SparkMeter\Services\SmSmsSettingService;
use Inensus\SparkMeter\Services\SmSmsVariableDefaultValueService;
use Inensus\SparkMeter\Services\SmSyncSettingService;

class UpdateSparkMeterPackage extends Command
{
    protected $signature = 'spark-meter:update';
    protected $description = 'Update the Spark Meter Integration Package';

    private $insertSparkMeterApi;
    private $meterModelService;
    private $credentialService;
    private $menuItemService;
    private $customerService;
    private $siteService;
    private $smsSettingService;
    private $syncSettingService;
    private $smsBodyService;
    private $defaultValueService;
    private $smSmsFeedbackWordService;
    private $packageInstallationService;

    /**
     * Create a new command instance.
     *
     * @param InsertSparkMeterApi $insertSparkMeterApi
     * @param MeterModelService $meterModelService
     * @param CredentialService $credentialService
     * @param MenuItemService $menuItemService
     * @param CustomerService $customerService
     * @param SiteService $siteService
     * @param SmSmsSettingService $smsSettingService
     * @param SmSyncSettingService $syncSettingService
     * @param SmSmsBodyService $smsBodyService
     * @param SmSmsVariableDefaultValueService $defaultValueService
     * @param SmSmsFeedbackWordService $smSmsFeedbackWordService
     * @param PackageInstallationService $packageInstallationService
     */
    public function __construct(
        InsertSparkMeterApi $insertSparkMeterApi,
        MeterModelService $meterModelService,
        CredentialService $credentialService,
        MenuItemService $menuItemService,
        CustomerService $customerService,
        SiteService $siteService,
        SmSmsSettingService $smsSettingService,
        SmSyncSettingService $syncSettingService,
        SmSmsBodyService $smsBodyService,
        SmSmsVariableDefaultValueService $defaultValueService,
        SmSmsFeedbackWordService $smSmsFeedbackWordService,
        PackageInstallationService $packageInstallationService
    ) {
        parent::__construct();
        $this->insertSparkMeterApi = $insertSparkMeterApi;
        $this->meterModelService = $meterModelService;
        $this->credentialService = $credentialService;
        $this->menuItemService = $menuItemService;
        $this->customerService = $customerService;
        $this->siteService = $siteService;
        $this->smsSettingService = $smsSettingService;
        $this->syncSettingService = $syncSettingService;
        $this->smsBodyService = $smsBodyService;
        $this->defaultValueService = $defaultValueService;
        $this->smSmsFeedbackWordService = $smSmsFeedbackWordService;
        $this->packageInstallationService = $packageInstallationService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Spark Meter Integration Updating Started\n');
        $this->info('Removing former version of package\n');
        echo shell_exec('COMPOSER_MEMORY_LIMIT=-1 ../composer.phar  remove inensus/spark-meter');
        $this->info('Installing last version of package\n');
        echo shell_exec('COMPOSER_MEMORY_LIMIT=-1 ../composer.phar  require inensus/spark-meter');

        $this->info('Copying migrations\n');

        $this->call('vendor:publish', [
            '--provider' => "Inensus\SparkMeter\Providers\SparkMeterServiceProvider",
            '--tag' => "migrations"
        ]);

        $this->info('Updating database tables\n');
        $this->call('migrate');

        $this->packageInstallationService->createDefaultSettingRecords();
        $this->info('Updating vue files\n');
        $this->call('vendor:publish', [
            '--provider' => "Inensus\SparkMeter\Providers\SparkMeterServiceProvider",
            '--tag' => "vue-components",
            '--force' => true,
        ]);

        $this->call('routes:generate');

        $menuItems = $this->menuItemService->createMenuItems();
        if (array_key_exists('menuItem', $menuItems)) {
            $this->call('menu-items:generate', [
                'menuItem' => $menuItems['menuItem'],
                'subMenuItems' => $menuItems['subMenuItems'],
            ]);
        }
        $this->call('sidebar:generate');
        $this->info('Package updated successfully..');
    }
}