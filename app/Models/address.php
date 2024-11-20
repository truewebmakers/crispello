<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class address extends Model
{
    use HasFactory;

    protected $primaryKey = '_id';
    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'save_as',
        'house_no',
        'area',
        'options_to_reach',
        'latitude',
        'longitude',
        'user_id',
        'is_default'
    ];

    public function users()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function carts()
    {
        return $this->hasMany(cart::class, 'address_id');
    }
}
