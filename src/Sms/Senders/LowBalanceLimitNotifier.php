<?php

namespace Inensus\SparkMeter\Sms\Senders;



class LowBalanceLimitNotifier extends SparkSmsSender
{
    protected $references = [
        'header' => 'SparkSmsLowBalanceHeader',
        'body' => 'SparkSmsLowBalanceBody',
        'footer' => 'SparkSmsLowBalanceFooter'
    ];
}
