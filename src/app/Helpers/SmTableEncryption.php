<?php


namespace Inensus\SparkMeter\app\Helpers;


class SmTableEncryption
{

  public function makeHash($data) {

        return md5(implode($data,''));
    }
}
