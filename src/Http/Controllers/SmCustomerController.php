<?php


namespace Inensus\SparkMeter\Http\Controllers;
use App\Http\Resources\ApiResource;
use Illuminate\Routing\Controller;
use Inensus\SparkMeter\Services\CustomerService;
use Illuminate\Http\Request;
class SmCustomerController  implements IBaseController
{
    private $customerService;

    public function __construct(CustomerService $customerService)
    {
        $this->customerService = $customerService;
    }

    public function index(Request $request): ApiResource
    {
        $customers = $this->customerService->getSmCustomers($request);
        return new ApiResource($customers);
    }

    public function sync(): ApiResource
    {
        return new ApiResource($this->customerService->sync());
    }
    public function checkSync(): ApiResource
    {
        return new ApiResource($this->customerService->syncCheck());
    }
    public function count()
    {
        return  $this->customerService->getSmCustomersCount() ;
    }

    public function location():ApiResource
    {
        return  new ApiResource($this->customerService->checkLocationAvailability());
    }
    public function connection():ApiResource
    {
        return  new ApiResource($this->customerService->checkConnectionAvailability());
    }
}
