<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryPartnerFareLogs extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_partner_id',
        'order_id',
        'pickup_lat',
        'pickup_long',
        'destination_lat',
        'destination_long',
        'total_km',
        'total_fare',
        'status',
        'currency'
    ];
    public function order()
    {
        return $this->belongsTo(order::class, 'order_id');
    }

}
