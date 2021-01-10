<?php

namespace Inensus\SparkMeter\Providers;

use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Inensus\SparkMeter\Console\Commands\InstallSparkMeterPackage;
use Inensus\SparkMeter\Console\Commands\SparkMeterLowBalanceLimitNotifier;
use Inensus\SparkMeter\Console\Commands\SparkMeterTransactionStatusCheck;
use Inensus\SparkMeter\Console\Commands\SparkMeterTransactionSync;
use Inensus\SparkMeter\Console\Commands\SparkMeterUpdatesGetter;
use Inensus\SparkMeter\Console\Kernel;


use Inensus\SparkMeter\Models\SmTransaction;
use Inensus\SparkMeter\SparkMeterApi;

class SparkMeterServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {

           $this->app->register(SparkMeterRouteServiceProvider::class);
           if ($this->app->runningInConsole()) {
                $this->publishConfigFiles();
                $this->publishVueFiles();
                $this->publishMigrations();
                $this->commands([
                    InstallSparkMeterPackage::class,
                    SparkMeterTransactionStatusCheck::class,
                    SparkMeterUpdatesGetter::class,
                    SparkMeterLowBalanceLimitNotifier::class,
                    SparkMeterTransactionSync::class]);

            }
        Relation::morphMap(
            [
                'spark_transaction'=>SmTransaction::class
            ]);

    }

    public function register()
    {

            $this->mergeConfigFrom(__DIR__ . '/../../config/spark-meter-integration.php', 'spark');

            $this->app->register(EventServiceProvider::class);
            $this->app->register(ObserverServiceProvider::class);

            $this->app->singleton('SparkMeterApi', static function ($app) {
                return new SparkMeterApi(new Client());
            });

            $this->app->singleton('Kernel', function ($app) {
                $dispatcher = $app->make(\Illuminate\Contracts\Events\Dispatcher::class);
                return new Kernel($app, $dispatcher);
            });
            $this->app->make('Kernel');

    }


    public function publishVueFiles()
    {
        $this->publishes([
            __DIR__ . '/../resources/assets' => resource_path('assets/js/plugins/spark-meter'
            ),
        ], 'vue-components');
    }

    public function publishConfigFiles()
    {
        $this->publishes([
            __DIR__ . '/../../config/spark-meter-integration.php' => config_path('spark-meter-integration.php'),
        ]);
    }

    public function publishMigrations()
    {
        if (!class_exists('CreateSmOrganizations')) {
            $timestamp = date('Y_m_d_His');
            $this->publishes([
                __DIR__ . '/../../database/migrations/create_sm_api_credentials.php.stub' => $this->app->databasePath() . "/migrations/{$timestamp}_create_sm_api_credentials.php",
                __DIR__ . '/../../database/migrations/create_sm_customers.php.stub' => $this->app->databasePath() . "/migrations/{$timestamp}_create_sm_customers.php",
                __DIR__ . '/../../database/migrations/create_sm_meter_models.php.stub' => $this->app->databasePath() . "/migrations/{$timestamp}_create_sm_meter_models.php",
                __DIR__ . '/../../database/migrations/create_sm_tariffs.php.stub' => $this->app->databasePath() . "/migrations/{$timestamp}_create_sm_tariffs.php",
                __DIR__ . '/../../database/migrations/create_sm_transactions.php.stub' => $this->app->databasePath() . "/migrations/{$timestamp}_create_sm_transactions.php",
                __DIR__ . '/../../database/migrations/create_sm_sites.php.stub' => $this->app->databasePath() . "/migrations/{$timestamp}_create_sm_sites.php",
                __DIR__ . '/../../database/migrations/create_sm_organizations.php.stub' => $this->app->databasePath() . "/migrations/{$timestamp}_create_sm_organizations.php",

            ], 'migrations');
        }
    }
}
