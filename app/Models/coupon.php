<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class coupon extends Model
{
    use HasFactory;
    protected $primaryKey = '_id';
    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'code',
        'coupon_type',
        'title',
        'description',
        'more_details',
        'discount',
        'valid_until',
        'valid_from',
        'threshold_amount'
    ];
}
