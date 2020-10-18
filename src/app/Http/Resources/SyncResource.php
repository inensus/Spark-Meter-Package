<?php

namespace Inensus\SparkMeter\app\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class SyncResource extends JsonResource
{
    private $isSynced;

    public function __construct($resource,$isSynced=0)
    {
        parent::__construct($resource);
    }

    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request)
    {
        $data= parent::toArray($request);
        $data['sync']=$this->isSynced;
        return $data;
    }
}
