<?php


namespace Inensus\SparkMeter\Models;


class SmCredential extends BaseModel
{
    protected $table = 'sm_api_credentials';

    public function generate()
    {
        return SmCredential::create();
    }
}
