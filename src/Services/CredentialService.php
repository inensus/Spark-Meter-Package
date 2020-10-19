<?php


namespace Inensus\SparkMeter\Services;


use App\Http\Resources\ApiResource;
use Inensus\SparkMeter\Exceptions\WrongCredentialsException;
use Inensus\SparkMeter\Helpers\SmTableEncryption;
use Inensus\SparkMeter\Helpers\SmTableHasher;
use Inensus\SparkMeter\Http\Requests\SparkMeterApiRequests;
use Inensus\SparkMeter\Models\SmCredential;

class CredentialService
{
    private $sparkMeterApiRequests;
    private $smCredential;
    private $systemService;
    private $smTableEncryption;
    public function __construct(SparkMeterApiRequests $sparkMeterApiRequests, SmCredential $smCredential,SystemService $systemService,SmTableEncryption $smTableEncryption)
    {
        $this->sparkMeterApiRequests=$sparkMeterApiRequests;
        $this->systemService=$systemService;
        $this->smCredential = $smCredential;
        $this->smTableEncryption=$smTableEncryption;
    }

    public function getCredentials()
    {
        return $this->smCredential::query()->latest()->take(1)->get()->first();
    }
    public function createSmCredentials()
    {
        $credential = $this->smCredential::query()->latest()->take(1)->get()->first();
        if (!$credential) {
            $credential = $this->smCredential::create();
        }
        return $credential;
    }
    public function updateCredentials($data)
    {
        try {

            $smCredentials =SmCredential::find($data['id']);
            $smCredentials->api_url=$data['api_url'];
            $smCredentials->authentication_token=$data['authentication_token'];
            $hash = $this->smTableEncryption->makeHash([$data['id'],$data['api_url'],$data['authentication_token']]);
            $smCredentials->update([
                'api_url'=>$data['api_url'],
                'authentication_token'=>$data['authentication_token'],
                'hash'=>$hash
            ]);
            $this->systemService->createSystem();
            return $smCredentials->fresh();
        }catch (WrongCredentialsException $e){
            $smCredentials->api_url=null;
            $smCredentials->authentication_token=null;
            $smCredentials->update();
            throw  new WrongCredentialsException ($e->getMessage());
        }

    }


}
