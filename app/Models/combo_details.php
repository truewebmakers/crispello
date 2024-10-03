<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class combo_details extends Model
{
    use HasFactory;
    protected $primaryKey = ['combo_id', 'product_id'];
    public $incrementing = false;
    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'combo_id',
        'product_id',
        'quantity',
        'size',
    ];

    // Define how Eloquent should interpret the primary key columns
    protected $keyType = 'integer';

    // Optionally, define the cast types for the primary key fields
    protected $casts = [
        'combo_id' => 'int',
        'product_id' => 'int',
    ];

    public function product()
    {
        return $this->belongsTo(product::class, 'product_id');
    }

    public function combo()
    {
        return $this->belongsTo(combo::class, 'combo_id');
    }
}
