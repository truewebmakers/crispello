<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralCampaign extends Model
{
    use HasFactory;
    protected $fillable = [
        'title', 'loyalty_points', 'currency','points_equal_to',
        'condition_install_app', 'condition_make_purchase',
        'minimum_purchase', 'status' , 'added_by','code'
    ];

    public function ReferralCode(){
        return $this->hasMany(ReferralCode::class);
    }
}
