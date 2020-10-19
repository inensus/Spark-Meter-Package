<?php

namespace Inensus\SparkMeter\Helpers;
use App\Models\Manufacturer;
use GuzzleHttp\Client;

class InsertSparkMeterApi
{


    public function __construct()
    {

    }
    public function registerSparkMeterManufacturer()
    {
      $api = Manufacturer::where('api_name','SparkMeterApi')->first();
      if (!$api){
          Manufacturer::create([
              'name'=>'Spark Meters',
              'website'=>'https://www.sparkmeter.io/',
              'api_name'=>'SparkMeterApi'
          ]);
      }
    }


}
