<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];


    protected $casts = [
        "shareholders"  => 'array',
        "directors"     => 'array',
        "documents"     => 'array',
        "administrator" => 'array',
        "other_datas"   => 'array',
        "terms_agreed_date" => "string",
        "business_verification_response" => "array",
    ];

    protected $hidden = [
        "created_at",
        "updated_at",
        'deleted_at',
        // 'business_verification_response'
        // 'id'
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function business_ubo()
    {
        return $this->hasMany(BusinessUbo::class);
    }

    public function preference()
    {
        return $this->hasOne(BusinessConfig::class, 'user_id', 'user_id');;
    }
}

