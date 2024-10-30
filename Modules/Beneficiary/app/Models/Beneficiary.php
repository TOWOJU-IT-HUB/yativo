<?php

namespace Modules\Beneficiary\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Beneficiary\Database\factories\BeneficiaryFactory;

class Beneficiary extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = [];

    protected $casts = [
        "customer_address" => "object"
    ];

    protected $hidden = [
        "deleted_at"
    ];

    public function payment_object()
    {
        return $this->hasMany(BeneficiaryPaymentMethod::class, "beneficiary_id", "id")->withOnly(['gateway' => function ($query) {
            $query->select(['method_name', 'currency', 'country', 'payment_mode', 'minimum_withdrawal', 'maximum_withdrawal']);
        }]);
    }

    public function scopePaymentMethods($query, $id)
    {
        return $query->whereHas('payment_object', function ($query) use ($id) {
            $query->where('beneficiary_id', $id);
        })->orderBy('created_at', 'desc')->get();
    }


    protected static function newFactory(): BeneficiaryFactory
    {
        //return BeneficiaryFactory::new();
    }
}
