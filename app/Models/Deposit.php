<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Scopes\GlobalModelActivityLog;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;


class Deposit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'currency_from',
        'currency_to',
        'amount_from',
        'amount_to',
        'exchange_rate',
        'payment_gateway_id',
        'meta',
        'deposit_id',
        'deposit_currency',
        'status',
        'credit_wallet' // wallet to be credited
    ];

    protected $casts = [
        'meta' => 'array'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'deleted_at',
        'exchange_rate',
        'payment_gateway_id',
        'meta',
        'credit_wallet',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Get the payin method associated with the exchange rate.
     */
    public function depositGateway()
    {
        return $this->belongsTo(PayinMethods::class, 'gateway');
    }

    public function transaction()
    {
        return $this->hasMany(Transaction::class)
            ->where('meta_id', $this->getKey())
            ->where('meta_type', 'deposit');
    }

    public function transactions()
    {
        return $this->hasMany(TransactionRecord::class, 'transaction_id', 'id')
            ->where('transaction_type', 'deposit');
    }

    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) generate_uuid();
            }
        });
    }

}
