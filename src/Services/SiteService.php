<?php


namespace Inensus\SparkMeter\Services;


use App\Models\City;
use App\Models\Cluster;
use App\Models\GeographicalInformation;
use App\Models\MiniGrid;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Inensus\SparkMeter\Helpers\SmTableEncryption;
use Inensus\SparkMeter\Http\Requests\SparkMeterApiRequests;
use Inensus\SparkMeter\Models\SmSite;

class SiteService implements ISynchronizeService
{
    private $site;
    private $sparkMeterApiRequests;
    private $rootUrl = '/organizations';
    private $smTableEncryption;
    private $organizationService;
    private $cluster;
    private $miniGrid;
    private $city;
    private $geographicalInformation;
    public function __construct(
        SmSite $site,
        SparkMeterApiRequests $sparkMeterApiRequests,
        SmTableEncryption $smTableEncryption,
        OrganizationService $organizationService,
        Cluster $cluster,
        MiniGrid $miniGrid,
        City $city,
        GeographicalInformation $geographicalInformation
    ) {
        $this->site = $site;
        $this->sparkMeterApiRequests = $sparkMeterApiRequests;
        $this->smTableEncryption=$smTableEncryption;
        $this->organizationService=$organizationService;
        $this->cluster=$cluster;
        $this->miniGrid=$miniGrid;
        $this->city=$city;
        $this->geographicalInformation=$geographicalInformation;
    }
    public function getSmSites($request)
    {
        $perPage = $request->input('per_page') ?? 15;
        $sites = $this->site->newQuery()->with('mpmMiniGrid')->paginate($perPage);

        foreach ($sites as $site){

            if ($site->thundercloud_token){
                $data= [
                    'thundercloud_token'=>$site->thundercloud_token
                ];
                $this->update($site->id,$data);
            }
        }
        return $sites;
    }
    public function getSmSitesCount()
    {

        return count($this->site->newQuery()->get());
    }

    public function creteRelatedMiniGrid($site)
    {

        $cluster = $this->cluster->newQuery()->latest('created_at')->first();
        $miniGrid= $this->miniGrid->newQuery()->create([
            'name' => $site['name'],
            'cluster_id' => $cluster->id
        ]);

        $cityName= explode('-', $site['name'])[1].'-city';
        $this->city->newQuery()->create([
            'name'=>$cityName,
            'mini_grid_id'=>$miniGrid->id,
            'cluster_id'=>$miniGrid->cluster_id
        ]);

        return $miniGrid;
    }

    public function updateGeographicalInformation($miniGridId)
    {
        $geographicalInformation = $this->geographicalInformation->newQuery()->whereHasMorph('owner',
            [MiniGrid::class],
            static function ($q) use ($miniGridId) {
                $q->where('id', $miniGridId);
            })->first();
        $points= explode(',', config('spark.geoLocation'));
        $latitude= strval(doubleval($points[0])+(mt_rand(10,10000)/ 10000)) ;
        $longitude=strval(doubleval($points[1])+(mt_rand(10,10000)/ 10000)) ;
        $points=$latitude.','.$longitude;
        $geographicalInformation->update([
            'points'=>$points
        ]);
    }

    public function updateRelatedMiniGrid($site,$miniGrid)
    {
        return $miniGrid->newQuery()->update([
            'name' => $site['name'],
        ]);
    }

    public function update($siteId,$data)
    {

        $site= $this->site->newQuery()->find($siteId);
        $site->update([
           'thundercloud_token'=>$data['thundercloud_token']
       ]);

        try {
            $rootUrl = '/system-info';
            $result = $this->sparkMeterApiRequests->get($rootUrl,$site->site_id);

            $system=$result['grids'][0];


            $site->is_authenticated=true;
            $site->is_online = Carbon::parse($system['last_sync_date'])->toDateTimeString()  > Carbon::now()->utc()->subMinutes(15)->toDateTimeString();

        } catch (Exception $e) {
            $site->is_authenticated=false;
            $site->is_online = false;
        }
        $site->update();
        return $site->fresh();
    }

    public function getThunderCloudInformation($siteId)
    {
        return $this->site->newQuery()->where('site_id',$siteId)->first();
    }

    public function checkLocationAvailability()
    {
        return $this->cluster->newQuery()->latest('created_at')->first();
    }

    public function sync()
    {
        try {
            $syncCheck = $this->syncCheck(true);

            if (!$syncCheck['result']){
                $sites = $syncCheck['data'];

                foreach ($sites as $key => $site) {
                    $registeredSmSite = $this->site->newQuery()->where('site_id', $site['id'])->first();

                    $smSiteHash =$this->modelHasher($site,null);
                    if ($registeredSmSite){
                        $isHashChanged = $registeredSmSite->hash === $smSiteHash ?? false;
                        $relatedMiniGrid = $this->miniGrid->newQuery()->find($registeredSmSite->mpm_mini_grid_id);
                        if (!$relatedMiniGrid) {
                            $miniGrid = $this->creteRelatedMiniGrid($site);
                            $registeredSmSite->update([
                                'site_id'=>$site['id'],
                                'thundercloud_url'=>$site['thundercloud_url'].'api/v0',
                                'mpm_mini_grid_id'=>$miniGrid->id,
                                'hash'=>$smSiteHash
                            ]);
                            $this->updateGeographicalInformation($miniGrid->id);
                        } else if ($relatedMiniGrid && $isHashChanged) {
                            $miniGrid = $this->updateRelatedMiniGrid($site,$relatedMiniGrid);
                            $this->updateGeographicalInformation($miniGrid->id);
                            $registeredSmSite->update([
                                'site_id'=>$site['id'],
                                'thundercloud_url'=>$site['thundercloud_url'].'api/v0',
                                'mpm_mini_grid_id'=>$miniGrid->id,
                                'hash'=>$smSiteHash,
                            ]);
                        } else {
                            continue;
                        }
                    }else{
                        $miniGrid = $this->creteRelatedMiniGrid($site);
                        $this->site->newQuery()->create([
                            'site_id'=>$site['id'],
                            'mpm_mini_grid_id'=>$miniGrid->id,
                            'thundercloud_url'=>$site['thundercloud_url'].'api/v0',
                            'hash'=>$smSiteHash,
                        ]);
                        $this->updateGeographicalInformation($miniGrid->id);
                    }

                }
            }
            return $this->site->newQuery()->with('mpmMiniGrid')->paginate(config('spark.paginate'));
        }catch (Exception $e) {
            Log::critical('Spark sites sync failed.', ['Error :' => $e->getMessage()]);
            throw  new Exception ($e->getMessage());
        }
    }

    public function syncCheck($returnData = false)
    {
        try {
            $organizations = $this->organizationService->getOrganizations();
            $sites = [];

            foreach ($organizations as $organization) {

                $url = $this->rootUrl . '/' . $organization->organization_id . '/sites';

                $result = $this->sparkMeterApiRequests->getFromKoios($url);
                $organizationSites = $result['sites'];

                foreach ($organizationSites as $site) {
                    array_push($sites, $site);
                }
            }
            $smSites = $this->site->newQuery()->get();
            $smSitesCount = count($smSites);
            $sitesCount = count($sites);
            if ($smSitesCount === $sitesCount) {
                foreach ($sites as $site) {
                    $registeredSmSite = $this->site->newQuery()->where('site_id', $site['id'])->first();
                    if ($registeredSmSite) {
                        $siteHash = $this->modelHasher($site,null);
                        $stmSiteHash = $registeredSmSite->hash;
                        if ($siteHash !== $stmSiteHash) {
                            break;
                        } else {
                            $sitesCount--;
                        }
                    } else {
                        break;
                    }
                }
                if ($sitesCount === 0) {
                    return $returnData ? ['data' => $sites, 'result' => true] : ['result' => true];
                }
                return $returnData ? ['data' => $sites, 'result' => false] : ['result' => false];
            } else {
                return $returnData ? ['data' => $sites, 'result' => false] : ['result' => false];
            }

        } catch (Exception $e) {
            Log::critical('Spark meter sites sync-check failed.', ['Error :' => $e->getMessage()]);
            if ($returnData) {
                return ['result' => false];
            }
            throw  new Exception ($e->getMessage());
        }
    }

    public function modelHasher($model,...$params): string
    {
      return  $this->smTableEncryption->makeHash([
            $model['name'],
            $model['display_name'],
            $model['thundercloud_url']
        ]);
    }

    public function syncCheckBySite($siteId)
    {

    }
}