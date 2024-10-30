<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BatchPayout extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        "id",
        "user_id",
        "payout_ids",
    ];

    protected $casts = [
        "payout_ids" => "array",
    ];


    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) \Str::uuid();
            }
        });
    }
}
