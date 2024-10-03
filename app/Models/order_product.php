<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class order_product extends Model
{
    use HasFactory;
    use HasFactory;
    protected $primaryKey = '_id';
    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'order_id',
        'name',
        'size',
        'price',
        'quantity',
        'veg',
        'product_id',
        'combo_id',
        'size_id',
        'coupon_id'
    ];

    public function order()
    {
        return $this->belongsTo(order::class, 'order_id');
    }

    public function product()
    {
        return $this->belongsTo(product::class, 'product_id');
    }

    public function combo()
    {
        return $this->belongsTo(combo::class, 'combo_id');
    }
}
