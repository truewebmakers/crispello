<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class customization extends Model
{
    use HasFactory;
    protected $primaryKey = '_id';
    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'name',
        'veg',
        'price',
        'is_available',
        'type',
        'admin_id',
    ];
}
