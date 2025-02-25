<?php

namespace Modules\Beneficiary\app\Models;

use App\Casts\PaymentDataAddressCast;
use App\Models\payoutMethods;
use App\Models\User;
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
        "bridge_id",
        "bridge_customer_id",
        "bridge_response"
    ];

    protected $casts = [
        "address" => 'array',
        'payment_data' => 'array',
        'bridge_response' => 'array',
        'payment_data.address' => PaymentDataAddressCast::class,
    ];
    


    protected $hidden = [
        'deleted_at',
        "user_id",
        'bridge_id',
        "beneficiary_id",
        'bridge_response',
        'bridge_customer_id',
        'updated_at',
        "address",
    ];


    // Accessor for payment_data's address
    public function getPaymentDataAttribute($value)
    {
        $data = json_decode($value, true); // Ensure it's an array
        if (isset($data['address']) && is_array($data['address'])) {
            $data['address'] = (array) $data['address']; // Cast address explicitly if necessary
        }
        return $data;
    }

    public function beneficiary()
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function gateway()
    {
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
