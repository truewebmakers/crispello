<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryPartnerFareSetting extends Model
{
    use HasFactory;
    protected $fillable = [
        'fare_per_km',
        'currency',
        'added_by',
    ];

}
