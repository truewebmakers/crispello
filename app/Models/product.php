<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class product extends Model
{
    use HasFactory;
    protected $primaryKey = '_id';
    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'name',
        'description',
        'veg',
        // 'actual_price',
        // 'selling_price',
        'best_seller',
        'recommended',
        'only_combo',
        'is_available',
        'product_category_id',
        'image',
        'disable',
        'delivery_actual_price',
        'delivery_selling_price',
        'pickup_actual_price',
        'pickup_selling_price',
        'dinein_actual_price',
        'dinein_selling_price',
        'customization'
    ];

    public function productCategory()
    {
        return $this->belongsTo(product_category::class, 'product_category_id');
    }

    public function productSizes()
    {
        return $this->hasMany(product_size::class, 'product_id');
    }

    public function comboDetails()
    {
        return $this->hasMany(combo_details::class, 'product_id');
    }

    public function cartProducts()
    {
        return $this->hasMany(cart_product::class, 'product_id');
    }

    public function orderProducts()
    {
        return $this->hasMany(order_product::class, 'product_id');
    }

    public function feedbacks()
    {
        return $this->hasMany(feedback::class, 'product_id');
    }
    // public function relatedProducts() {
    //     return $this->belongsToMany(RelatedProducts::class);
    // }

    public function relatedProducts()
    {
        return $this->belongsToMany(product::class, 'related_products', 'product_id', 'related_product_id');
    }
}
