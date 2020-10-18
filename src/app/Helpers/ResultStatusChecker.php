<?php


namespace Inensus\SparkMeter\app\Helpers;


use Inensus\SparkMeter\app\Exceptions\AlreadyExistException;
use Inensus\SparkMeter\app\Exceptions\AutorizationException;
use Inensus\SparkMeter\app\Exceptions\BadRequestException;
use Inensus\SparkMeter\app\Exceptions\InsufficientException;
use Inensus\SparkMeter\app\Exceptions\NotExistException;
use Inensus\SparkMeter\app\Exceptions\SparkAPIResponseException;

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
