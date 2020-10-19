<?php

namespace Inensus\SparkMeter\Http\Requests;

use GuzzleHttp\Client;
use Inensus\SparkMeter\Helpers\ResultStatusChecker;
use Inensus\SparkMeter\Models\SmCredential;
use Matrix\Exception;

class SparkMeterApiRequests
{

    private $client;
    private $resultStatusChecker;

    public function __construct(
        Client $httpClient,
        ResultStatusChecker $resultStatusChecker
    ) {
        $this->client = $httpClient;
        $this->resultStatusChecker = $resultStatusChecker;
    }

    public function get($url)
    {
        $smCredential = $this->getCredentials();
        $request = $this->client->get(
            $smCredential->api_url . $url,
            [
                'headers' => [
                    'Content-Type' => 'application/json;charset=utf-8',
                    'Authentication-Token' => $smCredential->authentication_token
                ],
            ]
        );
        return $this->resultStatusChecker->CheckApiResult(json_decode((string)$request->getBody(), true));
    }

    public function post($url, $postParams)
    {
        $smCredential = $this->getCredentials();
        $request = $this->client->post(
            $smCredential->api_url . $url,
            [
                'body' => json_encode($postParams),
                'headers' => [
                    'Content-Type' => 'application/json;charset=utf-8',
                    'Authentication-Token' => $smCredential->authentication_token
                ],
            ]
        );

        return $this->resultStatusChecker->CheckApiResult(json_decode((string)$request->getBody(), true));
    }

    public function put($url, $putParams)
    {
        $smCredential = $this->getCredentials();
        $request = $this->client->put(
            $smCredential->api_url . $url,
            [
                'body' => json_encode($putParams),
                'headers' => [
                    'Content-Type' => 'application/json;charset=utf-8',
                    'Authentication-Token' => $smCredential->authentication_token
                ],
            ]
        );

        return $this->resultStatusChecker->CheckApiResult(json_decode((string)$request->getBody(), true));

    }

    public function getByParams($url, $params)
    {
        try {
            $smCredential = $this->getCredentials();
            $apiUrl = $smCredential->api_url . $url . '?';
            foreach ($params as $key => $value) {
                $apiUrl .= $key . "=" . $value . "&";
            }
            $apiUrl=substr($apiUrl,0,-1);

            $request = $this->client->get(
                $apiUrl,
                [
                    'headers' => [
                        'Content-Type' => 'application/json;charset=utf-8',
                        'Authentication-Token' => $smCredential->authentication_token
                    ],
                ]
            );
            return json_decode((string)$request->getBody(), true);
        }catch (\Exception $e){
            return [
                'status'=>'failure'
            ];
        }

    }

    public function getInfo($url, $id)
    {
            $smCredential = $this->getCredentials();
            $apiUrl = $smCredential->api_url . $url.$id;
            $request = $this->client->get(
                $apiUrl,
                [
                    'headers' => [
                        'Content-Type' => 'application/json;charset=utf-8',
                        'Authentication-Token' => $smCredential->authentication_token
                    ],
                ]
            );
            return  $this->resultStatusChecker->CheckApiResult(json_decode((string)$request->getBody(), true));
    }

    private function getCredentials()
    {
        return SmCredential::query()->first();
    }
}
