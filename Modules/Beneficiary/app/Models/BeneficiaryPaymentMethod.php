<?php

namespace Modules\Beneficiary\app\Models;

use App\Models\payoutMethods;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Beneficiary\Database\factories\BeneficiaryPaymentMethodFactory;

class BeneficiaryPaymentMethod extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = "beneficiaries_payment_methods";

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        "user_id",
        "gateway_id",
        "nickname",
        "beneficiary_id",
        "currency",
        "payment_data",
    ];

    protected $casts = [
        'payment_data' => 'object',
        'address' => 'object'
    ];


    protected $hidden = [
        'deleted_at', 
        // 'created_at',
        'updated_at',
    ];

    public function beneficiary() {
        return $this->belongsTo(Beneficiary::class);
    }

    public function gateway() {
        return $this->belongsTo(payoutMethods::class);
    }

    public function getBeneficiaryPaymentMethod($id)
    {
        return self::find($id);
    }
    
    protected static function newFactory(): BeneficiaryPaymentMethodFactory
    {
        //return BeneficiaryPaymentMethodFactory::new();
    }
}
