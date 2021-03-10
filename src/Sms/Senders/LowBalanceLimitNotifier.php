<?php

namespace Inensus\SteamaMeter\Sms\Senders;

use Inensus\SparkMeter\Sms\Senders\SparkSmsSender;

class LowBalanceLimitNotifier extends SparkSmsSender
{
    protected $references = [
        'header' => 'SparkSmsLowBalanceHeader',
        'body' => 'SparkSmsLowBalanceBody',
        'footer' => 'SparkSmsLowBalanceFooter'
    ];
}
