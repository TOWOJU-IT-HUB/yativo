<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Beneficiary\app\Models\Beneficiary;
use Modules\Customer\app\Models\Customer;

class TransactionRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        "transaction_payin_details" => 'array',
        "transaction_payout_details" => 'array',
        "transaction_swap_details" => 'array',
        "raw_data" => 'array',
    ];

    protected $hidden = [
        'transaction_payin_details',
        'updated_at',
        'deleted_at',
    ];

    public function beneficiary(){
        return $this->belongsTo(Beneficiary::class);
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

    // Method to retrieve payment gateway dynamically
    public function getPaymentGatewayAttribute()
    {
        if ($this->transaction_memo === 'payin') {
            $result = $this->payinMethod; 
        }

        if ($this->transaction_memo === 'payout') {
            $result = $this->payoutMethod;
        }

        return ['payment_gateway' => $result]; // In case neither condition is met
    }

    public function tracking(){
        return $this->hasMany(Track::class);
    }

    public function customer(){
        return $this->belongsTo(Customer::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->customer_id && request()->has('customer_id')) {
                $model->customer_id = request()->customer_id;
            }
        });
    }
}

