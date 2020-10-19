<?php

namespace Inensus\SparkMeter\Http\Controllers;
use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

use Illuminate\Routing\Controller;
use Inensus\SparkMeter\Http\Requests\SmCredentialRequest;
use Inensus\SparkMeter\Services\CredentialService;
use Inensus\SparkMeter\Services\SystemService;
use Inensus\SparkMeter\Models\SmCredential;

class SmCredentialController extends Controller
{
    private $credentialService;
    private $systemService;
    public function __construct(CredentialService $credentialService,SystemService $systemService )
    {
        $this->credentialService=$credentialService;
        $this->systemService=$systemService;
    }

    public function show():ApiResource
    {
        return new ApiResource($this->credentialService->getCredentials());
    }

    public function update(SmCredentialRequest $request):ApiResource
    {
        $credentialResponse = $this->credentialService->updateCredentials($request->only([
            'id',
            'api_url',
            'authentication_token'
        ]));
        return new ApiResource($credentialResponse);
    }

}
