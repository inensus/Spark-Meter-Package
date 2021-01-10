<?php

namespace Inensus\SparkMeter\Http\Controllers;

use Illuminate\Routing\Controller;
use Inensus\SparkMeter\Http\Requests\SmCredentialRequest;
use Inensus\SparkMeter\Http\Resources\SparkResource;
use Inensus\SparkMeter\Services\CredentialService;



class SmCredentialController extends Controller
{
    private $credentialService;
    public function __construct(CredentialService $credentialService )
    {
        $this->credentialService=$credentialService;

    }

    public function show():SparkResource
    {
        return new SparkResource($this->credentialService->getCredentials());
    }

    public function update(SmCredentialRequest $request):SparkResource
    {
        $credentialResponse = $this->credentialService->updateCredentials($request->only([
            'id',
            'api_key',
            'api_secret'
        ]));
        return new SparkResource($credentialResponse);
    }

}
