<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class extra_setting extends Model
{
    use HasFactory;
    protected $primaryKey = '_id';
    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'delivery_charge',
        'free_upto_km',
        'delivery_coverage_km',
        'added_by'
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
