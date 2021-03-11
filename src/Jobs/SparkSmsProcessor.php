<?php

namespace Inensus\SparkMeter\Jobs;

use App\Exceptions\SmsBodyParserNotExtendedException;
use App\Exceptions\SmsTypeNotFoundException;
use App\Models\Sms;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Inensus\SparkMeter\Sms\Senders\SparkSmsSender;
use Inensus\SparkMeter\Sms\SparkSmsTypes;

class SparkSmsProcessor implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $data;
    public $smsType;
    private $smsTypes = [
        SparkSmsTypes::LOW_BALANCE_LIMIT_NOTIFIER => 'Inensus\SparkMeter\Sms\Senders\LowBalanceLimitNotifier',
    ];

    /**
     * Create a new job instance.
     *
     * @param            $data
     * @param int $smsType
     */
    public function __construct($data, int $smsType)
    {
        $this->data = $data;
        $this->smsType = $smsType;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $smsType = $this->resolveSmsType();
        $receiver = $smsType->getReceiver();
        //dont send sms if debug
        if (config('app.debug')) {
            $sms = Sms::query()->make([
                'body' => $smsType->body,
                'receiver' => $receiver,
                'uuid' => "debug"
            ]);
            $sms->trigger()->associate($this->data);
            $sms->save();
            return;
        }
        try {
            //set the uuid for the callback
            $uuid = $smsType->generateCallback();
            //sends sms or throws exception

            $smsType->sendSms();
        } catch (\Exception $e) {
            //slack failure
            Log::debug(
                'Sms Service failed ' . $receiver,
                ['id' => '58365682988725', 'reason' => $e->getMessage()]
            );
            return;
        }
        $sms = Sms::query()->make([
            'uuid' => $uuid,
            'body' => $smsType->body,
            'receiver' => $receiver
        ]);
        $sms->trigger()->associate($this->data);
        $sms->save();
    }

    private function resolveSmsType()
    {
        if (!array_key_exists($this->smsType, $this->smsTypes)) {
            throw new SmsTypeNotFoundException('SmsType could not resolve.');
        }
        $smsBodyService = resolve('Inensus\SparkMeter\Services\SmSmsBodyService');
        $reflection = new \ReflectionClass($this->smsTypes[$this->smsType]);

        if (!$reflection->isSubclassOf(SparkSmsSender::class)) {
            throw new  SmsBodyParserNotExtendedException('SmsBodyParser has not extended.');
        }
        return $reflection->newInstanceArgs([$this->data, $smsBodyService]);
    }
}
