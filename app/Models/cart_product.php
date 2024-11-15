<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class cart_product extends Model
{
    use HasFactory;

    protected $primaryKey = '_id';
    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'cart_id',
        'product_id',
        'combo_id',
        'quantity',
        'customization',
        'size'
    ];

    public function cart()
    {
        return $this->belongsTo(cart::class, 'cart_id');
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
