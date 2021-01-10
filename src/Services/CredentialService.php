<?php


namespace Inensus\SparkMeter\Services;



use Inensus\SparkMeter\Helpers\SmTableEncryption;
use Inensus\SparkMeter\Http\Requests\SparkMeterApiRequests;
use Inensus\SparkMeter\Models\SmCredential;

class CredentialService
{
    private $sparkMeterApiRequests;
    private $smCredential;
    private $smTableEncryption;
    private $rootUrl = '/organizations';
    private $organizationService;
    public function __construct(
        SparkMeterApiRequests $sparkMeterApiRequests,
        SmCredential $smCredential,
        SmTableEncryption $smTableEncryption,
        OrganizationService $organizationService
    ) {
        $this->sparkMeterApiRequests=$sparkMeterApiRequests;
        $this->smCredential = $smCredential;
        $this->smTableEncryption=$smTableEncryption;
        $this->organizationService=$organizationService;
    }

    public function getCredentials()
    {
        return  $this->smCredential->newQuery()->latest()->take(1)->get()->first();
    }
    public function createSmCredentials()
    {
        $credential = $this->smCredential->newQuery()->latest()->take(1)->get()->first();
        if (!$credential) {
            $credential = $this->smCredential->newQuery()->create();
        }
        return $credential;
    }

    public function updateCredentials($data)
    {
        $smCredentials =$this->smCredential->newQuery()->find($data['id']);
        $smCredentials->update([
            'api_key'=>$data['api_key'],
            'api_secret'=>$data['api_secret'],
        ]);
        try {
            $result = $this->sparkMeterApiRequests->getFromKoios($this->rootUrl);
            $smCredentials->is_authenticated=true;
            $smCredentials->save();
            $this->organizationService->createOrganization($result['organizations'][0]);
            return $smCredentials->fresh();
        }catch (\Exception $e){
            $this->organizationService->deleteOrganization();
            $smCredentials->is_authenticated=false;
            $smCredentials->save();
            return $smCredentials;
        }

    }

}
