<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GiftCard extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'transaction_id',
        'status',
        'currency_code',
        'amount',
        'fee',
        'total_fee',
        'recipient_email',
        'custom_identifier',
        'pre_ordered',
        'purchase_data',
        'redeem_instructions',
        'transaction_created_at',
    ];

    protected $casts = [
        'purchase_data' => 'array',
        'pre_ordered' => 'boolean',
        'transaction_created_at' => 'datetime',
    ];

    /**
     * Relationship: GiftCard belongs to a user.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Accessor to get the product name from the purchase_data.
     */
    public function getProductNameAttribute()
    {
        return $this->purchase_data['product']['productName'] ?? null;
    }

    /**
     * Accessor to get the brand name from the purchase_data.
     */
    public function getBrandNameAttribute()
    {
        return $this->purchase_data['product']['brand']['brandName'] ?? null;
    }

    public function transaction_records()
    {
        return $this->morphMany(TransactionRecord::class, 'transaction_model');
    }
}
