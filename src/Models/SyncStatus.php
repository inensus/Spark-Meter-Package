<?php


namespace Inensus\SparkMeter\Models;


abstract class SyncStatus
{

    const Synced = 1;
    const Modified = 2;
    const NotRegisteredYet = 3;

}