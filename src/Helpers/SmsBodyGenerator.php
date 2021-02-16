<?php

namespace Inensus\SparkMeter\Helpers;

use App\Models\PaymentHistory;
use Inensus\SparkMeter\Models\SmCustomer;


class SmsBodyGenerator
{
    public static function generateSmsBody($transaction, $smCustomer)
    {

        if (!$transaction) {
            return 'Dear ' . $smCustomer->mpmPerson->name . ' ' . $smCustomer->mpmPerson->surname . ' your credit balance has reduced under ' . $smCustomer->low_balance_limit . ', your currently balance is :' . $smCustomer->credit_balance;
        }
        $body = 'Dear ' . $smCustomer->mpmPerson->name . ' ' . $smCustomer->mpmPerson->surname . ' your payment has received.';
        $payments = $transaction->paymentHistories()->get();

        foreach ($payments as $payment) {
            $body .=  PHP_EOL . self::generateEnergyConfirmationBody($smCustomer, $payment);
        }
        return $body;
    }
    private static function generateEnergyConfirmationBody(SmCustomer $smCustomer, PaymentHistory $paymentHistory): string
    {

        $token = $paymentHistory->paidFor()->first();
        $transaction = $paymentHistory->transaction()->first();

        return 'Meter: {' . $transaction->message . '}, ' . $token->token . ' Unit ' . $token->energy . '. ' . config('spark.currency') . $paymentHistory->amount;
    }
}
