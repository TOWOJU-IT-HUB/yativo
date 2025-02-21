<?php

namespace App\Jobs;

use App\Models\Business;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Modules\ShuftiPro\app\Services\ShuftiProServices;

class VerifyBusinessJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public $business;

    public function __construct($business)
    {
        $this->business = $business;
    }


    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $service = new ShuftiProServices();
        $bis = $this->business;
        $response = $service->businessVerification(
            $bis->business_legal_name,
            $bis->business_registration_number,
            $bis->incorporation_country,
            $bis->businessCountry
        );
        // store the response in the DB
        $business = Business::whereId($bis->id)->first();
        $business->update([
            'business_verification_response' => $response
        ]);
    }
}
