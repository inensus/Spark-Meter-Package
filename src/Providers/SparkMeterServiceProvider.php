<?php

namespace Inensus\SparkMeter\Providers;

use GuzzleHttp\Client;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Inensus\SparkMeter\Console\Commands\InstallSparkMeterPackage;
use Inensus\SparkMeter\Console\Commands\SparkMeterDataSynchronizer;
use Inensus\SparkMeter\Console\Commands\SparkMeterSmsNotifier;
use Inensus\SparkMeter\Console\Commands\SparkMeterTransactionStatusCheck;
use Illuminate\Support\Collection;
use Illuminate\Filesystem\Filesystem;

use Inensus\SparkMeter\Models\SmSmsSetting;
use Inensus\SparkMeter\Models\SmSyncSetting;
use Inensus\SparkMeter\Models\SmTransaction;
use Inensus\SparkMeter\SparkMeterApi;

class SparkMeterServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(Filesystem $filesystem)
    {

        $this->app->register(SparkMeterRouteServiceProvider::class);
        if ($this->app->runningInConsole()) {
            $this->publishConfigFiles();
            $this->publishVueFiles();
            $this->publishMigrations($filesystem);
            $this->commands([
                InstallSparkMeterPackage::class,
                SparkMeterTransactionStatusCheck::class,
                SparkMeterDataSynchronizer::class,
                SparkMeterSmsNotifier::class
            ]);

        }
        $this->app->booted(function ($app) {
            $app->make(Schedule::class)->command('spark-meter:dataSync')->withoutOverlapping(50)
                ->appendOutputTo(storage_path('logs/cron.log'));
            $app->make(Schedule::class)->command('spark-meter:smsNotifier')->withoutOverlapping(50)
                ->appendOutputTo(storage_path('logs/cron.log'));
        });

        Relation::morphMap(
            [
                'spark_transaction' => SmTransaction::class,
                'sync_setting' => SmSyncSetting::class,
                'sms_setting' => SmSmsSetting::class,
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

    public function publishMigrations($filesystem)
    {
        $this->publishes([
            __DIR__ . '/../../database/migrations/create_spark_tables.php.stub' => $this->getMigrationFileName($filesystem),
        ], 'migrations');
    }

    protected function getMigrationFileName(Filesystem $filesystem): string
    {
        $timestamp = date('Y_m_d_His');
        return Collection::make($this->app->databasePath() . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR)
            ->flatMap(function ($path) use ($filesystem) {
                return $filesystem->glob($path . '*_create_spark_tables.php');
            })->push($this->app->databasePath() . "/migrations/{$timestamp}_create_spark_tables.php")
            ->first();
    }
}
