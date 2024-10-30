<?php

namespace Modules\Customer\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Customer\Database\factories\CustomerVirtualCardsFactory;

class CustomerVirtualCards extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        "id",
        "business_id",
        "customer_id",
        "customer_card_id",
        "card_number",
        "expiry_date",
        "cvv",
        "card_id",
        "raw_data"
    ];
    
    protected $casts = [
        "raw_data"
    ];
    
    // Use UUID as the primary key type
    protected $keyType = 'string';

    // Disable auto-incrementing
    public $incrementing = false;

    // Automatically generate UUID when creating a new model instance
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = generate_uuid();
            }
        });
    }
}
