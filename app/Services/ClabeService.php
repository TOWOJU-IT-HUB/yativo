<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class ClabeService
{
    protected $banks;
    protected $cities;
    protected $citiesMap = [];
    protected $storagePath = 'clabe/last_clabe.txt';
    protected $lastClabe;

    public function __construct()
    {
        $this->banks = config('clabe.banks');
        $this->cities = config('clabe.cities');
        $this->buildCitiesMap();
        $this->loadLastClabe();
    }

    protected function buildCitiesMap()
    {
        foreach ($this->cities as $city) {
            $this->citiesMap[$city[0]][] = $city;
        }
    }

    protected function loadLastClabe()
    {
        if (Storage::exists($this->storagePath)) {
            $this->lastClabe = trim(Storage::get($this->storagePath));
        } else {
            $this->lastClabe = '000000000000000000';
        }
    }

    protected function saveLastClabe($clabe)
    {
        Storage::put($this->storagePath, $clabe);
    }

    public function generateNextClabe()
    {
        $next = str_pad(bcadd($this->lastClabe, '1'), 18, '0', STR_PAD_LEFT);
        $validClabe = $this->createValidClabeFromNumber($next);
        $this->lastClabe = $validClabe;
        $this->saveLastClabe($validClabe);
        return $validClabe;
    }

    protected function createValidClabeFromNumber($number)
    {
        $base = substr($number, 0, 17);
        $checksum = $this->computeChecksum($base);
        return $base . $checksum;
    }

    public function computeChecksum($base)
    {
        $weights = [3, 7, 1];
        $sum = 0;
        for ($i = 0; $i < 17; $i++) {
            $sum += (int)$base[$i] * $weights[$i % 3];
        }
        return (10 - ($sum % 10)) % 10;
    }

    public function validate($clabe)
    {
        if (!preg_match('/^\d{18}$/', $clabe)) {
            return ['ok' => false, 'error' => 'Invalid format'];
        }

        $bankCode = (int)substr($clabe, 0, 3);
        $cityCode = (int)substr($clabe, 3, 3);
        $account = substr($clabe, 6, 11);
        $checksum = (int)substr($clabe, 17, 1);
        $computedChecksum = $this->computeChecksum(substr($clabe, 0, 17));

        $bank = $this->banks[$bankCode] ?? null;
        $cities = $this->citiesMap[$cityCode] ?? [];

        if (!$bank) {
            return ['ok' => false, 'error' => "Invalid bank code: $bankCode"];
        }

        if (!$cities) {
            return ['ok' => false, 'error' => "Invalid city code: $cityCode"];
        }

        if ($checksum !== $computedChecksum) {
            return ['ok' => false, 'error' => "Checksum mismatch. Expected: $computedChecksum"];
        }

        return [
            'ok' => true,
            'clabe' => $clabe,
            'bank' => $bank['name'],
            'tag' => $bank['tag'],
            'city' => implode(", ", array_column($cities, 1)),
            'account' => $account,
            'checksum' => $computedChecksum,
        ];
    }
}
