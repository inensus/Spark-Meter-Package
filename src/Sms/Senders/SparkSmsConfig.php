<?php

namespace Inensus\SparkMeter\Sms\Senders;

use App\Sms\Senders\SmsConfigsCore;
use Inensus\SparkMeter\Sms\SparkSmsTypes;

class SparkSmsConfig extends SmsConfigsCore
{
    public $smsTypes = [
        SparkSmsTypes::LOW_BALANCE_LIMIT_NOTIFIER => 'Inensus\SparkMeter\Sms\Senders\LowBalanceLimitNotifier',
    ];
    public $bodyParsersPath = 'Inensus\\SparkMeter\\Sms\\BodyParsers\\';
    public $servicePath = 'Inensus\SparkMeter\Services\SmSmsBodyService';
}
