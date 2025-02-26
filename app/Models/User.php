<?php

namespace App\Models;

use Bavix\Wallet\Traits\HasWallet;
use Bavix\Wallet\Traits\HasWalletFloat;
use Bavix\Wallet\Traits\HasWallets;
use Creatydev\Plans\Models\PlanSubscriptionModel;
use Creatydev\Plans\Traits\HasPlans;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Yadahan\AuthenticationLog\AuthenticationLogable;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens,
        HasFactory,
        HasProfilePhoto,
        Notifiable,
        TwoFactorAuthenticatable,
        AuthenticationLogable,
        HasRoles,
        HasWallet,
        HasWalletFloat,
        HasWallets,
        SoftDeletes,
        HasPlans;

    protected $fillable = [
        'name',
        'email',
        'businessName',
        'firstName',
        'lastName',
        'phoneNumber',
        'city',
        'state',
        'country',
        'zipCode',
        'street',
        'additionalInfo',
        'houseNumber',
        'is_business',
        'user_type',
        'raw_data',
        'google2fa_secret',
        'transaction_pin',
        'wallet_balance',
        'login_token',
        'idNumber',
        'idType',
        'idIssuedAt',
        'idExpiryDate',
        'idIssueDate',
        'verificationDocument',
        'use_cases',
        'occupation',
        'how_much_to_move_monthly',
        'monthly_payment_quantity',
        'registration_country',
        'profile_photo_path',
        'email_verified_at',
        'two_factor_confirmed_at',
        'current_team_id',
        'login_token_created_at',
        'google2fa_enabled',
        'membership_id',
        'kyc_status',
        'is_kyc_submitted',
        'last_login_at',
        'is_pnd'
    ];

    protected $with = [
        'balances',
        'businessConfig',
        'subscriptions',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
        'created_at',
        'updated_at',
        'deleted_at',
        'raw_data',
        'google2fa_secret',
        'transaction_pin',
        'wallet_balance',
        'login_token',
        'idIssuedAt',
        'idExpiryDate',
        'idIssueDate',
        'verificationDocument',
        'brla_subaccount_id',
        // 'kyc_status',
        // 'is_kyc_submitted',
        'idNumber',
        'idType',
        'is_pnd',
        'bussinessName'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'raw_data' => 'array',
        'use_cases' => 'array',
        'is_kyc_submitted' => 'boolean'
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    // Properly defined scope for balance relationships
    public function scopeBalance($query)
    {
        return $query->whereHas('balances');
    }

    // Properly defined scope for withdrawals relationships
    public function scopeWithdrawals($query)
    {
        return $query->whereHas('withdrawals');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function deposits()
    {
        return $this->hasMany(Deposit::class);
    }

    public function balances()
    {
        return $this->hasMany(Balance::class);
    }

    public function withdrawals()
    {
        return $this->hasMany(Withdraw::class);
    }

    public function scopeMyWallets($query)
    {
        return $query->with('wallets')
            ->withCount('wallets')
            ->addSelect([
                'total_balance' => $this->wallets()->sum('balance')
            ]);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'id', 'user_id');
    }

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function ($user) {
            if ($user->exists && (is_null($user->membership_id) || $user->membership_id === '')) {
                $user->membership_id = ($user->account_type === 'company') ? uuid(9, "B") : uuid(9, 'P');
            }
        });
    }

    public function businessConfig()
    {
        return $this->hasOne(BusinessConfig::class);
    }

    public function subscriptions()
    {
        return $this->morphMany(PlanSubscriptionModel::class, 'model');
    }

    public function customPricing()
    {
        return $this->hasOne(CustomPricing::class);
    }
}
