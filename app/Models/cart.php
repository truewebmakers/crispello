<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class cart extends Model
{
    use HasFactory;
    protected $primaryKey = '_id';
    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
       'user_id',
       'address_id',
       'table_no',
       'order_type',
       'payment_method',
       'coupon_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function address()
    {
        return $this->belongsTo(address::class, 'address_id');
    }

    public function cartProducts()
    {
        return $this->hasMany(cart_product::class, 'cart_id');
    }
}
