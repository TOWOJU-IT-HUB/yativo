<?php

namespace Modules\Customer\App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use GuzzleHttp\Promise\Utils;
use Illuminate\Support\Facades\Http;


class DojahServices
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => getenv("DOJA_BASE_URL"),
            'headers' => [
                'AppId' => getenv("DOJA_APP_ID"),
                'Authorization' => getenv("DOJA_PRIVATE_KEY")
            ]
        ]);
    }

    public function businessGlobalDetails($document)
    {
        return $this->curl('kyb/business/detail', "get", $document);
    }

    public function documentAnalysis($document)
    {
        $arr = [
            "input_type" => "base64",
            "imageFrontSide" => $document['imageFrontSide'],
            "imageBackSide"  => $document['imageBackSide'],
        ];

        $result = $this->curl('document/analysis', "post", $arr);
        return $result;
    }

    public function selfieIdVerification($selfie)
    {
        $selfie = [
            "photoid_image" => str_replace('data:image/jpeg;base64,', '', $selfie['photoidimage']),
            "selfie_image" => str_replace('data:image/jpeg;base64,', '', $selfie['selfieimage'])
        ];

        // var_dump($selfie); exit;

        return $this->curl('kyc/photoid/verify',  "post", $selfie);
    }

    public function livenessCheck($livenessData)
    {
        return $this->curl('ml/liveness', "post", $livenessData);
    }

    public function individualAddressVerification($address)
    {
        $data = [
            "longitude" => $address['longitude'],
            "latitude" => $address['latitude']
        ];
        return $this->curl('kyc/address/reverse_geocode',  "post", $data);
    }

    public function businessAddressVerification($address)
    {
        return $this->curl('kyc/address/business',  "post", $address);
    }

    public function amlScreeningIndividual($amlData)
    {
        $data = [
            "first_name" => $amlData['first_name'],
            "middle_name" => $amlData['middle_name'],
            "last_name" => $amlData['last_name'],
            "date_of_birth" => $amlData['dob'] ?? $amlData['date_of_birth']
        ];

        return $this->curl("aml/screening", "post", $data);
    }

    public function amlScreeningBusiness($amlData)
    {
        return $this->curl("aml/screening/organization", "post", $amlData);
    }

    public function userScreening($userData)
    {
        $data = [
            "first_name" => $userData['first_name'],
            "middle_name" => $userData['middle_name'],
            "last_name" => $userData['last_name'],
            "date_of_birth" => $userData['dob'] ?? $userData['date_of_birth']
        ];
        return $this->curl('fraud/user', "get", $data);
    }

    public function handleRequests(array $requests)
    {
        $promises = [];

        foreach ($requests as $key => $request) {
            $promises[$key] = $request;
        }

        $results = Utils::settle($promises)->wait();

        return $results;
    }

    public function verifyCustomer(array $data)
    {
        $document = $data;
        $selfie = $data;
        $livenessData = $data;
        $address = $data;
        $amlData = $data;
        $userData = $data;


        $requests = [
            'document_analysis' => $this->documentAnalysis($document),
            'selfie_verification' => $this->selfieIdVerification($selfie),
            'liveness_check' => $this->livenessCheck($livenessData),
            'address_verification' => $this->individualAddressVerification($address),
            'aml_screening' => $this->amlScreeningIndividual($amlData),
            'user_screening' => $this->userScreening($userData),
        ];

        $results = $this->handleRequests($requests);
        
        return $results;
    }

    public function verifyBusiness($data)
    {
        $requests = [
            'business_details' => $this->businessGlobalDetails($data),
            'address_verification' => $this->businessAddressVerification($data),
            'aml_screening' => $this->amlScreeningBusiness($data),
            'user_screening' => $this->userScreening($data),
        ];

        $results = $this->handleRequests($requests);

        return $results;
    }

    private function curl($uri, $method, $payload)
    {
        if (!is_array($payload)) {
            $payload = (array)$payload;
        }

        $curl = Http::withHeaders([
            'AppId' => getenv("DOJA_APP_ID"),
            'Authorization' => getenv("DOJA_PRIVATE_KEY")
        ])->$method(getenv("DOJA_BASE_URL") . $uri, $payload)->json();

        return $curl;
    }
}
