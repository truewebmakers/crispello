<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class order_customization extends Model
{
    use HasFactory;
    protected $primaryKey = '_id';
    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'name',
        'arabic_name',
        'veg',
        'price',
        'type',
        'order_product_id',
        'order_id',
    ];

    public function orderProdut()
    {
        return $this->belongsTo(order_product::class, 'order_product_id');
    }
    public function order()
    {
        return $this->belongsTo(order::class, 'order_id');
    }
}
