<?php

namespace Inensus\SparkMeter\Services;

use Inensus\SparkMeter\Models\SmSmsBody;

class SmSmsBodyService
{
    private $smsBody;

    public function __construct(SmSmsBody $smsBody)
    {
        $this->smsBody = $smsBody;
    }

    public function getSmsBodyByReference($reference)
    {
        return $this->smsBody->newQuery()->where('reference', $reference)->first();
    }

    public function getSmsBodies()
    {
        return $this->smsBody->newQuery()->get();
    }

    public function updateSmsBodies($smsBodiesData)
    {
        $smsBodies = $this->smsBody->newQuery()->get();
        collect($smsBodiesData)->each(function ($smsBody) use ($smsBodies) {
            $smsBodies->filter(function ($body) use ($smsBody) {
                return $body['id'] === $smsBody['id'];
            })->first()->update([
                'body' => $smsBody['body']
            ]);
        });
        return $smsBodies;
    }

    public function getNullBodies()
    {
        return $this->smsBody->newQuery()->whereNull('body')->get();
    }

    public function createSmsBodies()
    {
        $smsBodies = [
            [
                'reference' => 'SparkSmsLowBalanceHeader',
                'place_holder' => 'Dear [name] [surname],',
                'variables' => 'name,surname',
                'title' => 'Sms Header'
            ],
            [
                'reference' => 'SparkSmsLowBalanceBody',
                'place_holder' => 'your credit balance has reduced under [low_balance_limit],'
                    . 'your currently balance is [credit_balance]',
                'variables' => 'low_balance_limit,credit_balance',
                'title' => 'Low Balance Limit Notify'
            ],
            [
                'reference' => 'SparkSmsLowBalanceFooter',
                'place_holder' => 'Your Company etc.',
                'variables' => '',
                'title' => 'Sms Footer'
            ]
        ];
        collect($smsBodies)->each(function ($smsBody) {
             $this->smsBody->newQuery()->create($smsBody);
        });
    }
}
