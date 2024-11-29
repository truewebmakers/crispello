<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class order extends Model
{
    use HasFactory;
    protected $primaryKey = '_id';
    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'user_id',
        'driver_id',
        'total',
        'delivery_charge',
        'order_status',
        'order_date',
        'paid',
        'order_type',
        'payment_method',
        'payment_id',
        'table_no',
        'longitude',
        'latitude',
        'house_no',
        'area',
        'options_to_reach',
        'coupon_id',
        'used_loyalty_points',
        'wallet_amount'

    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function orderProducts()
    {
        return $this->hasMany(order_product::class, 'order_id');
    }

    public function deliveryRequests()
    {
        return $this->hasMany(delivery_request::class, 'order_id');
    }
    public function orderCustomization()
    {
        return $this->hasMany(order_customization::class, 'order_id');
    }
}
