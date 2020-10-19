<?php


namespace Inensus\SparkMeter\Helpers;


use Inensus\SparkMeter\Exceptions\SparkAPIResponseException;

class ResultStatusChecker
{
    public function CheckApiResult($result)
    {
        if ($result['status'] !== 'success') {
            throw new SparkAPIResponseException($result['error']);
        }
        return $result;
    }
}
