<?php

namespace Inensus\SparkMeter\app\Http\Controllers;
use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

use Illuminate\Routing\Controller;
use Inensus\SparkMeter\app\Http\Requests\SmCredentialRequest;
use Inensus\SparkMeter\app\Services\CredentialService;
use Inensus\SparkMeter\app\Services\SystemService;
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
