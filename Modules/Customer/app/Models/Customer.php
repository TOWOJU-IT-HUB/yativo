<?php

namespace Modules\Customer\app\Models;

use Crypt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Bitnob\app\Models\VirtualCards;
use Modules\Customer\Database\factories\CustomerFactory;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    // protected $fillable = [
    //     "user_id",
    //     "customer_id",
    //     "customer_name",
    //     "customer_email",
    //     "customer_phone",
    //     "customer_country",
    //     "customer_address",
    //     "customer_idType",
    //     "customer_idNumber",
    //     "customer_idCountry",
    //     "customer_idExpiration",
    //     "customer_idFront",
    //     "customer_idBack",
    //     "can_create_vc", // can_create_virtual_card
    //     "can_create_va", // can_create_virtual_account
    //     "customer_status",
    //     "json_data",
    //     "vc_customer_id",
    //     "customer_kyc_status",
    //     "customer_type",
    // ];

    protected $guarded = [];


    protected $hidden = [
        'id',
        'user_id',
        'customer_idType',
        'customer_idNumber',
        'customer_idCountry',
        'customer_idExpiration',
        'customer_idFront',
        'customer_idBack',
        'json_data',
        'card_user_id',
        "updated_at",
        "deleted_at",
        "vc_customer_id",
        // "customer_kyc_status",
        "customer_type",
        "admin_kyc_reject_reason",
        "customer_kyc_link_id",
        "customer_kyc_link",
        "bridge_customer_id",
        "brla_subaccount_id",
        "customer_kyc_email",
        "yativo_customer_id",
        "manteca_user_id",
        "can_create_vc", // can_create_virtual_card
        "can_create_va", // can_create_virtual_account
    ];

    protected $casts = [
        "json_data" => 'array',
        "customer_address" => "array",
        "can_create_vc" => "boolean",
        // "can_create_va" => "boolean",
    ];


    protected $encryptable = [
        'customer_address',
        'customer_idType',
        'customer_idNumber',
        'customer_idCountry',
        'customer_idExpiration',
        'customer_idFront',
        'customer_idBack'
    ];

    // public function setAttribute($key, $value)
    // {
    //     if (in_array($key, $this->encryptable) && !empty($value)) {
    //         if(is_array($value)) {
    //             $value = json_encode($value);
    //         }
    //         $value = Crypt::encryptString($value);
    //     }
    //     return parent::setAttribute($key, $value);
    // }

    // public function getAttribute($key)
    // {
    //     $value = parent::getAttribute($key);
    //     if (in_array($key, $this->encryptable) && !empty($value)) {
    //         if(is_array($value)) {
    //             $value = json_encode($value);
    //         }
    //         return Crypt::decryptString($value);
    //     }
    //     return $value;
    // }

    protected $keyType = 'string';

    public $incrementing = false;

    // Automatically generate UUID when creating a new model instance
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = generate_uuid();
            }
            if (empty($model->customer_id)) {
                $model->customer_id = generate_uuid();
            }
        });
    }

    public function virtual_cards(): HasMany
    {
        return $this->hasMany(VirtualCards::class, 'customer_id', 'customer_id');
    }

    protected static function newFactory(): CustomerFactory
    {
        //return CustomerFactory::new();
    }

    public function newQuery()
    {
        if (request()->has('customer_status')) {

            $query = parent::newQuery()->where('customer_status', request()->get('customer_status'));
        } else {
            $query = parent::newQuery()->where('customer_status', 'active');
        }

        return $query;
    }
}
