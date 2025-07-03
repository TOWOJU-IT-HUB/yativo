<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class CheckoutModel extends Model
{
    use HasFactory;

    // Define the fillable attributes for mass assignment
    protected $fillable = [
        'user_id',
        'transaction_id',
        'deposit_id',
        'checkout_mode',
        'checkout_id',
        'provider_checkout_response',
        'checkouturl',
        'checkout_status',
        'expiration_time'
    ];

    // Cast the provider_checkout_response attribute to array
    protected $casts = [
        'provider_checkout_response' => 'array', // Automatically encode/decode to/from JSON
    ];

    protected $append = ['is_expired'];

    // Optionally, you can add relationships if needed
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transaction()
    {
        return $this->belongsTo(TransactionRecord::class, 'transaction_id');
    }

    public function deposit()
    {
        return $this->belongsTo(Deposit::class);
    }

    public function getIsExpiredAttribute(): bool
    {
        $createdAt = $this->created_at instanceof Carbon
            ? $this->created_at
            : Carbon::parse($this->created_at);

        return now()->diffInMinutes($createdAt) >= (int) $this->expiration_time;
    }
}
