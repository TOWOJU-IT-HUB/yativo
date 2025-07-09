<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Beneficiary\app\Models\Beneficiary;
use Modules\Customer\app\Models\Customer;
use Modules\Beneficiary\app\Models\BeneficiaryPaymentMethod;

class TransactionRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        "transaction_payin_details" => 'array',
        "transaction_payout_details" => 'array',
        "transaction_swap_details" => 'array',
        "exchange_data" => 'array',
        "raw_data" => 'array',
    ];

    // protected $appends = ['payment_gateway'];

    protected $hidden = [
        "transaction_payin_details",
        "transaction_payout_details",
        "transaction_swap_details",
        // "transaction_beneficiary_id",
        "raw_data",
        "user_id",
        "gateway_id",
        "updated_at",
        "deleted_at",
    ];

    protected $with = ['checkout_url'];

    public function beneficiary(){
        return $this->belongsTo(BeneficiaryPaymentMethod::class, 'transaction_beneficiary_id');
    }

    public function user(){
        return $this->belongsTo(User::class);
    }

    // Define relationship to PayinMethods for deposits
    public function payinMethod()
    {
        return $this->belongsTo(PayinMethods::class, 'gateway_id');
    }

    // Define relationship to PayoutMethods for payouts
    public function payoutMethod()
    {
        return $this->belongsTo(payoutMethods::class, 'gateway_id');
    }

    public function checkout_url()
    {
        return $this->belongsTo(CheckoutModel::class, 'id', 'transaction_id')->only(['checkouturl', 'expiration_time', 'checkout_status']);
    }

    // Method to retrieve payment gateway dynamically
    public function getPaymentGatewayAttribute()
    {
        $result = [];
        if ($this->transaction_memo === 'payin') {
            $result = $this->payinMethod; 
        }

        if ($this->transaction_memo === 'payout') {
            $result = $this->payoutMethod;
        }

        return $result; // In case neither condition is met
    }

    public function tracking(){
        return $this->hasMany(Track::class);
    }

    public function customer(){
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->customer_id && request()->has('customer_id')) {
                $model->customer_id = request()->customer_id;                
            }
            if (request()->has('payment_method_id')) {
                $model->transaction_beneficiary_id = request()->payment_method_id;
            }
            if (request()->has('debit_wallet')) {
                $model->base_currency = request()->debit_wallet;
            }
        });
    }
}

