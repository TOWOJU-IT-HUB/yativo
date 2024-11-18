<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Beneficiary\app\Models\Beneficiary;

class Withdraw extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded  = [];
    
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'deleted_at',
        'gateway',
        'gateway_id',
        'id'
    ];

    protected $casts =  [
        'raw_data' => 'array',
        'is_send_money' => 'boolean'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->payout_id)) {
                $model->payout_id = generate_uuid();
            }
        });
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(TransactionRecord::class, 'transaction_id', 'id')
            ->where('transaction_type', 'deposit');
    }

       /**
     * Get the payin method associated with the exchange rate.
     */
    public function payoutGateway()
    {
        return $this->belongsTo(payoutMethods::class, 'gateway_id', 'id');
    }

    public function beneficiary()
    {
        return $this->belongsTo(Beneficiary::class);
    }

    // public function beneficiary()
    // {
    //     return $this->belongsTo(Beneficiary::class);
    // }
}
